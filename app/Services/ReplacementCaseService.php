<?php

namespace App\Services;

use App\Ai\Agents\ReplacementAgent;
use App\Enums\AiRunFeature;
use App\Enums\ReplacementProposalStatus;
use App\Jobs\RunReplacementAgent;
use App\Models\DemoSession;
use App\Models\ReplacementCase;
use App\Models\ReplacementProposal;
use App\Models\ReplacementRequest;
use App\Models\ReplacementStock;
use App\Support\ChatInjectionGate;
use App\Support\HarborReplacementCatalog;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Exceptions\ApprovalMismatchException;
use Laravel\Ai\Responses\AgentResponse;
use Throwable;

class ReplacementCaseService
{
    public function __construct(
        private readonly DamageReplacementPolicy $policy,
        private readonly AiUsageRecorder $runs,
        private readonly ConversationStore $conversations,
    ) {}

    public function forSession(DemoSession $session): ReplacementCase
    {
        $case = ReplacementCase::query()->firstOrCreate(
            ['demo_session_id' => $session->id],
            ['generation' => 1, 'visitor_messages' => 0],
        );

        if ($case->wasRecentlyCreated || $case->stock()->doesntExist()) {
            $this->copyStock($case);
        }

        return $case;
    }

    public function queueVisitorMessage(ReplacementCase $case, string $message): string
    {
        $message = trim($message);
        $case = $case->fresh() ?? $case;

        if ($case->isBusy()) {
            return 'busy';
        }

        $this->expirePending($case);

        $waiting = $case->proposals()
            ->where('generation', $case->generation)
            ->where('status', ReplacementProposalStatus::Pending)
            ->where('expires_at', '>', now())
            ->exists();

        if ($waiting) {
            return 'pending';
        }

        $cap = (int) config('supportflow.replacement.message_cap');

        if ($case->visitor_messages >= $cap) {
            return 'capped';
        }

        $case->messages()->create([
            'generation' => $case->generation,
            'role' => 'user',
            'body' => $message,
        ]);
        $case->increment('visitor_messages');

        if (ChatInjectionGate::blocks($message)) {
            $case->messages()->create([
                'generation' => $case->generation,
                'role' => 'assistant',
                'body' => ChatInjectionGate::REFUSAL,
            ]);

            return 'refused';
        }

        $case->forceFill(['run_state' => 'queued'])->save();
        RunReplacementAgent::dispatch($case->id, $case->generation, 'message', $message);

        return 'queued';
    }

    public function beginConfirm(ReplacementProposal $proposal): bool
    {
        $started = false;
        $caseId = null;
        $generation = null;
        $toolCallId = null;

        DB::transaction(function () use ($proposal, &$started, &$caseId, &$generation, &$toolCallId): void {
            $locked = ReplacementProposal::query()->lockForUpdate()->find($proposal->id);

            if (! $locked instanceof ReplacementProposal) {
                return;
            }

            $case = ReplacementCase::query()->lockForUpdate()->find($locked->replacement_case_id);

            if (! $case instanceof ReplacementCase || $case->isBusy()) {
                return;
            }

            if ($locked->generation !== $case->generation || ! $locked->isOpen()) {
                return;
            }

            $locked->forceFill(['status' => ReplacementProposalStatus::Confirming])->save();
            $case->forceFill(['run_state' => 'queued'])->save();
            $started = true;
            $caseId = $case->id;
            $generation = $case->generation;
            $toolCallId = $locked->tool_call_id;
        });

        if ($started && is_string($caseId) && is_int($generation) && is_string($toolCallId)) {
            RunReplacementAgent::dispatch($caseId, $generation, 'approve', '', $toolCallId);
        }

        return $started;
    }

    public function beginCancel(ReplacementProposal $proposal): bool
    {
        $started = false;
        $caseId = null;
        $generation = null;
        $toolCallId = null;

        DB::transaction(function () use ($proposal, &$started, &$caseId, &$generation, &$toolCallId): void {
            $locked = ReplacementProposal::query()->lockForUpdate()->find($proposal->id);

            if (! $locked instanceof ReplacementProposal) {
                return;
            }

            $case = ReplacementCase::query()->lockForUpdate()->find($locked->replacement_case_id);

            if (! $case instanceof ReplacementCase || $case->isBusy()) {
                return;
            }

            if ($locked->generation !== $case->generation || $locked->status !== ReplacementProposalStatus::Pending) {
                return;
            }

            $locked->forceFill(['status' => ReplacementProposalStatus::Cancelled])->save();
            $case->forceFill(['run_state' => 'queued'])->save();
            $this->activity($case, $case->generation, 'Nothing was saved.');
            $started = true;
            $caseId = $case->id;
            $generation = $case->generation;
            $toolCallId = $locked->tool_call_id;
        });

        if ($started && is_string($caseId) && is_int($generation) && is_string($toolCallId)) {
            RunReplacementAgent::dispatch($caseId, $generation, 'reject', '', $toolCallId);
        }

        return $started;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function commit(string $proposalId, int $generation, array $arguments): ?ReplacementRequest
    {
        return DB::transaction(function () use ($proposalId, $generation, $arguments): ?ReplacementRequest {
            $proposal = ReplacementProposal::query()->lockForUpdate()->find($proposalId);

            if (! $proposal instanceof ReplacementProposal) {
                return null;
            }

            $case = ReplacementCase::query()->lockForUpdate()->find($proposal->replacement_case_id);

            if (! $case instanceof ReplacementCase || $case->generation !== $generation || $proposal->generation !== $generation) {
                return null;
            }

            $existing = ReplacementRequest::query()->where('proposal_id', $proposal->id)->first();

            if ($existing instanceof ReplacementRequest) {
                return $existing;
            }

            if ($proposal->status !== ReplacementProposalStatus::Confirming || $proposal->expires_at->isPast()) {
                return null;
            }

            if ($proposal->argument_hash !== self::hashArguments($arguments)) {
                $this->cancelLocked($proposal, $case, 'Nothing was saved.');

                return null;
            }

            $order = HarborReplacementCatalog::find($proposal->order_number);

            if ($order === null || $order['sku'] !== $proposal->sku || $order['item'] !== $proposal->item_name) {
                $this->cancelLocked($proposal, $case, 'Nothing was saved.');

                return null;
            }

            $stock = ReplacementStock::query()
                ->where('replacement_case_id', $case->id)
                ->where('sku', $order['sku'])
                ->lockForUpdate()
                ->first();

            $onHand = $stock instanceof ReplacementStock ? $stock->on_hand : 0;

            if (! $this->policy->assess($order, $onHand)['eligible']) {
                $this->cancelLocked($proposal, $case, 'Nothing was saved.');

                return null;
            }

            $request = $this->createRequest($proposal, $case);
            $stock?->decrement('on_hand');
            $proposal->forceFill(['status' => ReplacementProposalStatus::Confirmed])->save();
            $this->activity($case, $generation, 'Your replacement request is saved.');

            return $request;
        });
    }

    public function reset(ReplacementCase $case): ReplacementCase
    {
        return DB::transaction(function () use ($case): ReplacementCase {
            $locked = ReplacementCase::query()->lockForUpdate()->findOrFail($case->id);
            $conversationId = $locked->conversation_id;

            $locked->forceFill([
                'generation' => $locked->generation + 1,
                'conversation_id' => null,
                'visitor_messages' => 0,
                'run_state' => null,
            ])->save();

            $locked->requests()->delete();
            $locked->proposals()->delete();
            $locked->activities()->delete();
            $locked->messages()->delete();
            $locked->stock()->delete();
            $this->copyStock($locked);
            $this->deleteSdkConversation($conversationId);

            return $locked->refresh();
        });
    }

    /**
     * @param  list<string>  $sessionIds
     */
    public function invalidateSessions(array $sessionIds): void
    {
        if ($sessionIds === []) {
            return;
        }

        $cases = ReplacementCase::query()->whereIn('demo_session_id', $sessionIds)->get();

        foreach ($cases as $case) {
            DB::transaction(function () use ($case): void {
                $locked = ReplacementCase::query()->lockForUpdate()->find($case->id);

                if (! $locked instanceof ReplacementCase) {
                    return;
                }

                $conversationId = $locked->conversation_id;
                $locked->forceFill([
                    'generation' => $locked->generation + 1,
                    'conversation_id' => null,
                    'run_state' => null,
                ])->save();
                $this->deleteSdkConversation($conversationId);
            });
        }
    }

    public function expirePending(ReplacementCase $case): void
    {
        $case->proposals()
            ->where('generation', $case->generation)
            ->where('status', ReplacementProposalStatus::Pending)
            ->where('expires_at', '<=', now())
            ->update(['status' => ReplacementProposalStatus::Expired->value]);
    }

    public function execute(string $caseId, int $generation, string $mode, string $prompt, ?string $toolCallId): void
    {
        $case = ReplacementCase::query()->find($caseId);

        if (! $case instanceof ReplacementCase || $case->generation !== $generation) {
            return;
        }

        if (in_array($mode, ['approve', 'reject'], true)) {
            $this->executeVisitorDecision($case, $generation, $mode, $toolCallId);

            return;
        }

        $conversationId = $this->ensureConversation($case);
        $case = $case->fresh();

        if (! $case instanceof ReplacementCase || $case->generation !== $generation) {
            $this->deleteSdkConversation($conversationId);

            return;
        }

        $case->forceFill(['run_state' => 'running'])->save();

        $model = (string) config('supportflow.models.chat');
        $run = $this->runs->start(AiRunFeature::Replacement, null, $model);
        $agent = (new ReplacementAgent($caseId, $generation))->continue($conversationId);

        try {
            $response = $agent->prompt($prompt, provider: Lab::OpenAI, model: $model, timeout: 45);
            $response = $this->settleApprovals($agent, $conversationId, $caseId, $generation, $response, $model);

            $this->remember($caseId, $generation, $conversationId, $response);
            $this->runs->complete($run, $response, ['replacement_case_id' => $caseId]);
        } catch (ApprovalMismatchException) {
            $this->runs->fail($run, 'That confirmation was already resolved.');
            $this->remember($caseId, $generation, $conversationId, null);
        } catch (Throwable $exception) {
            $this->runs->fail($run, $exception->getMessage());
            $this->deleteIfStale($caseId, $generation, $conversationId);
        } finally {
            ReplacementCase::query()
                ->whereKey($caseId)
                ->where('generation', $generation)
                ->update(['run_state' => null]);
        }
    }

    public function holdProposal(string $caseId, int $generation, PendingApproval $approval): bool
    {
        $case = ReplacementCase::query()->find($caseId);

        if (! $case instanceof ReplacementCase || $case->generation !== $generation) {
            return false;
        }

        if (! $this->acceptsProposal($case, $approval)) {
            return false;
        }

        $this->storeProposal($case, $generation, $approval);

        return true;
    }

    /**
     * Confirm or cancel without resuming OpenAI. Stored pauses replay reasoning
     * blocks without the matching function_call items, so Decisions::approve()
     * returns 400 (No tool call found for function call output) after PHP already saved the request.
     */
    private function executeVisitorDecision(ReplacementCase $case, int $generation, string $mode, ?string $toolCallId): void
    {
        $case->forceFill(['run_state' => 'running'])->save();

        $model = (string) config('supportflow.models.chat');
        $run = $this->runs->start(AiRunFeature::Replacement, null, $model);
        $conversationId = $case->conversation_id;

        try {
            $text = $mode === 'approve'
                ? $this->commitApprovedProposal($case, $generation, $toolCallId)
                : 'Nothing was saved.';

            if ($case->fresh()?->generation === $generation) {
                $case->messages()->create([
                    'generation' => $generation,
                    'role' => 'assistant',
                    'body' => $text,
                ]);
                $case->forceFill(['conversation_id' => null])->save();
            }

            $this->deleteSdkConversation($conversationId);
            $this->runs->complete($run, null, [
                'replacement_case_id' => $case->id,
                'decision' => $mode,
            ]);
        } catch (Throwable $exception) {
            $this->runs->fail($run, $exception->getMessage());
            $this->deleteIfStale($case->id, $generation, (string) $conversationId);
        } finally {
            ReplacementCase::query()
                ->whereKey($case->id)
                ->where('generation', $generation)
                ->update(['run_state' => null]);
        }
    }

    private function commitApprovedProposal(ReplacementCase $case, int $generation, ?string $toolCallId): string
    {
        if (! is_string($toolCallId) || $toolCallId === '') {
            return 'Nothing was saved.';
        }

        $proposal = ReplacementProposal::query()
            ->where('replacement_case_id', $case->id)
            ->where('generation', $generation)
            ->where('tool_call_id', $toolCallId)
            ->first();

        if (! $proposal instanceof ReplacementProposal) {
            return 'Nothing was saved.';
        }

        $arguments = $proposal->snapshot['tool_arguments'] ?? [
            'order_number' => $proposal->order_number,
            'quantity' => $proposal->quantity,
            'reason' => $proposal->reason,
        ];

        $saved = $this->commit($proposal->id, $generation, $arguments);

        if (! $saved instanceof ReplacementRequest) {
            return 'Nothing was saved.';
        }

        return 'Your replacement request is saved. Reference '.$saved->reference.'. Nothing is shipped.';
    }

    private function providerCallId(AgentResponse $response, string $approvalId): ?string
    {
        $toolCall = $response->toolCalls->first(
            fn ($toolCall): bool => $toolCall->id === $approvalId,
        );

        $callId = $toolCall?->resultId;

        return is_string($callId) && $callId !== '' ? $callId : null;
    }

    public function recordActivity(string $caseId, int $generation, string $summary): void
    {
        $case = ReplacementCase::query()->find($caseId);

        if (! $case instanceof ReplacementCase || $case->generation !== $generation) {
            return;
        }

        $this->activity($case, $generation, $summary);
    }

    public function onHand(string $caseId, string $sku): int
    {
        return (int) ReplacementStock::query()
            ->where('replacement_case_id', $caseId)
            ->where('sku', $sku)
            ->value('on_hand');
    }

    public function deleteSdkConversation(?string $conversationId): void
    {
        if (! is_string($conversationId) || $conversationId === '') {
            return;
        }

        $messages = (string) config('ai.conversations.tables.messages', 'agent_conversation_messages');
        $conversations = (string) config('ai.conversations.tables.conversations', 'agent_conversations');

        DB::table($messages)->where('conversation_id', $conversationId)->delete();
        DB::table($conversations)->where('id', $conversationId)->delete();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public static function hashArguments(array $arguments): string
    {
        $normalized = [
            'order_number' => strtoupper(trim((string) ($arguments['order_number'] ?? ''))),
            'quantity' => (int) ($arguments['quantity'] ?? 0),
            'reason' => trim((string) ($arguments['reason'] ?? '')),
        ];

        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
    }

    public function ensureConversation(ReplacementCase $case): string
    {
        $locked = ReplacementCase::query()->findOrFail($case->id);

        if (is_string($locked->conversation_id) && $locked->conversation_id !== '' && $this->conversationExists($locked->conversation_id)) {
            return $locked->conversation_id;
        }

        $id = $this->conversations->storeConversation(null, null, 'Replacement request');
        $locked->forceFill(['conversation_id' => $id])->save();

        return $id;
    }

    private function settleApprovals(
        ReplacementAgent $agent,
        string $conversationId,
        string $caseId,
        int $generation,
        AgentResponse $response,
        string $model,
    ): AgentResponse {
        $guard = 0;

        while ($response->hasPendingApprovals() && $guard < 2) {
            $case = ReplacementCase::query()->find($caseId);

            if (! $case instanceof ReplacementCase || $case->generation !== $generation) {
                break;
            }

            $pending = $response->pendingApprovals;
            $only = $pending->count() === 1 ? $pending->first() : null;

            if ($only instanceof PendingApproval && $this->acceptsProposal($case, $only)) {
                $this->storeProposal($case, $generation, $only, $this->providerCallId($response, $only->id));

                break;
            }

            $decisions = [];

            foreach ($pending as $approval) {
                if (! $approval instanceof PendingApproval) {
                    continue;
                }

                $decisions[$approval->id] = Decision::reject($this->rejectionReason($case, $approval));
            }

            if ($decisions === []) {
                break;
            }

            $response = $agent->continue($conversationId)->prompt(
                Decisions::from($decisions),
                provider: Lab::OpenAI,
                model: $model,
                timeout: 45,
            );
            $guard++;
        }

        return $response;
    }

    private function remember(string $caseId, int $generation, string $conversationId, ?AgentResponse $response): void
    {
        $case = ReplacementCase::query()->find($caseId);

        if (! $case instanceof ReplacementCase || $case->generation !== $generation) {
            $this->deleteSdkConversation($conversationId);

            if ($response?->conversationId !== null && $response->conversationId !== $conversationId) {
                $this->deleteSdkConversation($response->conversationId);
            }

            return;
        }

        $text = $response instanceof AgentResponse ? trim($response->text) : '';

        if ($text !== '') {
            $case->messages()->create([
                'generation' => $generation,
                'role' => 'assistant',
                'body' => $text,
            ]);
        }
    }

    private function acceptsProposal(ReplacementCase $case, PendingApproval $approval): bool
    {
        if ($approval->tool !== 'CreateReplacementRequest') {
            return false;
        }

        $order = HarborReplacementCatalog::find((string) ($approval->arguments['order_number'] ?? ''));
        $quantity = (int) ($approval->arguments['quantity'] ?? 0);
        $reason = trim((string) ($approval->arguments['reason'] ?? ''));

        if ($order === null || $quantity !== 1 || ! str_contains(strtolower($reason), 'damage')) {
            return false;
        }

        return $this->policy->assess($order, $this->onHand($case->id, $order['sku']))['eligible'];
    }

    private function rejectionReason(ReplacementCase $case, PendingApproval $approval): string
    {
        $order = HarborReplacementCatalog::find((string) ($approval->arguments['order_number'] ?? ''));

        if ($order === null) {
            return 'We could not find that order. Nothing was saved.';
        }

        return $this->policy->assess($order, $this->onHand($case->id, $order['sku']))['summary'];
    }

    private function storeProposal(ReplacementCase $case, int $generation, PendingApproval $approval, ?string $providerCallId = null): void
    {
        $order = HarborReplacementCatalog::find((string) ($approval->arguments['order_number'] ?? ''));

        if ($order === null) {
            return;
        }

        $case->proposals()
            ->where('generation', $generation)
            ->where('status', ReplacementProposalStatus::Pending)
            ->update(['status' => ReplacementProposalStatus::Superseded->value]);

        $onHand = $this->onHand($case->id, $order['sku']);

        $case->proposals()->create([
            'generation' => $generation,
            'tool_call_id' => $approval->id,
            'argument_hash' => self::hashArguments($approval->arguments),
            'status' => ReplacementProposalStatus::Pending,
            'order_number' => $order['number'],
            'sku' => $order['sku'],
            'item_name' => $order['item'],
            'quantity' => 1,
            'reason' => 'Arrived damaged',
            'action' => 'Save a replacement request',
            'snapshot' => [
                'order_number' => $order['number'],
                'item' => $order['item'],
                'sku' => $order['sku'],
                'quantity' => 1,
                'reason' => 'Arrived damaged',
                'stock' => $onHand,
                'tool_arguments' => [
                    'order_number' => strtoupper(trim((string) ($approval->arguments['order_number'] ?? ''))),
                    'quantity' => (int) ($approval->arguments['quantity'] ?? 0),
                    'reason' => trim((string) ($approval->arguments['reason'] ?? '')),
                ],
                'provider_call_id' => $providerCallId,
            ],
            'expires_at' => now()->addMinutes((int) config('supportflow.replacement.proposal_minutes')),
        ]);
    }

    private function createRequest(ReplacementProposal $proposal, ReplacementCase $case): ReplacementRequest
    {
        try {
            return ReplacementRequest::query()->create([
                'replacement_case_id' => $case->id,
                'proposal_id' => $proposal->id,
                'reference' => $this->reference(),
                'order_number' => $proposal->order_number,
                'sku' => $proposal->sku,
                'item_name' => $proposal->item_name,
                'quantity' => $proposal->quantity,
                'reason' => $proposal->reason,
            ]);
        } catch (UniqueConstraintViolationException) {
            $existing = ReplacementRequest::query()->where('proposal_id', $proposal->id)->first();

            if ($existing instanceof ReplacementRequest) {
                return $existing;
            }

            return ReplacementRequest::query()->create([
                'replacement_case_id' => $case->id,
                'proposal_id' => $proposal->id,
                'reference' => $this->reference(),
                'order_number' => $proposal->order_number,
                'sku' => $proposal->sku,
                'item_name' => $proposal->item_name,
                'quantity' => $proposal->quantity,
                'reason' => $proposal->reason,
            ]);
        }
    }

    private function cancelLocked(ReplacementProposal $proposal, ReplacementCase $case, string $summary): void
    {
        $proposal->forceFill(['status' => ReplacementProposalStatus::Cancelled])->save();
        $this->activity($case, $case->generation, $summary);
    }

    private function activity(ReplacementCase $case, int $generation, string $summary): void
    {
        if ($case->generation !== $generation) {
            return;
        }

        $case->activities()->create([
            'generation' => $generation,
            'summary' => $summary,
        ]);
    }

    private function copyStock(ReplacementCase $case): void
    {
        foreach (HarborReplacementCatalog::stock() as $sku => $onHand) {
            ReplacementStock::query()->create([
                'replacement_case_id' => $case->id,
                'sku' => $sku,
                'on_hand' => $onHand,
            ]);
        }
    }

    private function reference(): string
    {
        return 'HR-'.strtoupper(bin2hex(random_bytes(3)));
    }

    private function conversationExists(string $conversationId): bool
    {
        $conversations = (string) config('ai.conversations.tables.conversations', 'agent_conversations');

        return DB::table($conversations)->where('id', $conversationId)->exists();
    }

    private function deleteIfStale(string $caseId, int $generation, string $conversationId): void
    {
        $case = ReplacementCase::query()->find($caseId);

        if (! $case instanceof ReplacementCase || $case->generation !== $generation) {
            $this->deleteSdkConversation($conversationId);
        }
    }
}
