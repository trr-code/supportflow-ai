<?php

namespace App\Services;

use App\Enums\AiRunFeature;
use App\Enums\AiRunStatus;
use App\Models\AiRun;
use App\Models\Ticket;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\AgentResponse;

class AiUsageRecorder
{
    public function __construct(private readonly CostEstimator $costs) {}

    public function start(AiRunFeature $feature, ?Ticket $ticket, string $model, ?string $hash = null): AiRun
    {
        $run = $ticket === null ? null : AiRun::query()
            ->where('ticket_id', $ticket->id)
            ->where('feature', $feature)
            ->where('status', AiRunStatus::Queued)
            ->latest('id')
            ->first();

        if ($run instanceof AiRun) {
            $run->forceFill([
                'status' => AiRunStatus::Running,
                'started_at' => now(),
                'model' => $model,
                'request_hash' => $hash,
            ])->save();

            return $run;
        }

        return AiRun::query()->create([
            'feature' => $feature,
            'ticket_id' => $ticket?->id,
            'provider' => 'openai',
            'model' => $model,
            'status' => AiRunStatus::Running,
            'started_at' => now(),
            'request_hash' => $hash,
        ]);
    }

    public function queue(AiRunFeature $feature, Ticket $ticket, string $model, ?string $hash = null): AiRun
    {
        $existing = AiRun::query()
            ->where('ticket_id', $ticket->id)
            ->where('feature', $feature)
            ->where('status', AiRunStatus::Queued)
            ->latest('id')
            ->first();

        if ($existing instanceof AiRun) {
            $existing->forceFill([
                'model' => $model,
                'request_hash' => $hash,
            ])->save();

            return $existing;
        }

        return AiRun::query()->create([
            'feature' => $feature,
            'ticket_id' => $ticket->id,
            'provider' => 'openai',
            'model' => $model,
            'status' => AiRunStatus::Queued,
            'request_hash' => $hash,
        ]);
    }

    public function discardQueued(AiRunFeature $feature, Ticket $ticket): void
    {
        AiRun::query()
            ->where('ticket_id', $ticket->id)
            ->where('feature', $feature)
            ->where('status', AiRunStatus::Queued)
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<int>  $retrievedChunkIds
     */
    public function complete(AiRun $run, ?AgentResponse $response = null, array $payload = [], array $retrievedChunkIds = []): AiRun
    {
        $input = $response?->usage->promptTokens;
        $output = $response?->usage->completionTokens;
        $total = $input !== null ? $input + (int) $output : null;

        $run->forceFill([
            'status' => AiRunStatus::Completed,
            'completed_at' => now(),
            'input_tokens' => $input,
            'output_tokens' => $output,
            'total_tokens' => $total,
            'estimated_cost' => $this->costs->estimate((string) $run->model, $input, $output),
            'retrieved_chunk_ids' => $retrievedChunkIds === [] ? null : $retrievedChunkIds,
            'payload' => $payload === [] ? $run->payload : $payload,
        ])->save();

        $this->discardQueuedFor($run);

        return $run;
    }

    public function fail(AiRun $run, string $error): AiRun
    {
        $run->forceFill([
            'status' => AiRunStatus::Failed,
            'completed_at' => now(),
            'error' => Str::limit($error, 2000),
        ])->save();

        $this->discardQueuedFor($run);

        return $run;
    }

    protected function discardQueuedFor(AiRun $run): void
    {
        if ($run->ticket_id === null) {
            return;
        }

        AiRun::query()
            ->where('ticket_id', $run->ticket_id)
            ->where('feature', $run->feature)
            ->where('status', AiRunStatus::Queued)
            ->where('id', '!=', $run->id)
            ->delete();
    }
}
