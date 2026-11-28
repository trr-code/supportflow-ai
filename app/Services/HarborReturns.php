<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\DemoSession;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HarborReturns
{
    public const int AUTO_APPROVAL_CENTS = 10000;

    public const string CHOOSE_ORDER = 'What’s your order number?';

    public const string CHAT_POLICY = "## Start a return in chat\nFor fictional orders, Harbor chat can approve an eligible single-item return after you confirm. Eligible items must be unused with tags, within 30 days of delivery, and cost $100 or less at purchase. Final-sale items are excluded from demo returns. Higher-priced items can be sent to support for review. The demo saves a return reference without issuing a refund or sending a shipping label.";

    /** @return array<string, array{item: string, cents: int, days: int, finalSale: bool}> */
    public function orders(): array
    {
        return [
            'HB-51001' => ['item' => 'Harbor Trail Pack', 'cents' => 7900, 'days' => 12, 'finalSale' => false],
            'HB-51002' => ['item' => 'Harbor Trail Pack', 'cents' => 7900, 'days' => 40, 'finalSale' => false],
            'HB-51003' => ['item' => 'Ridgeline 2P Tent', 'cents' => 24900, 'days' => 12, 'finalSale' => false],
            'HB-51004' => ['item' => 'Summit Trekking Pole', 'cents' => 4900, 'days' => 8, 'finalSale' => false],
            'HB-51005' => ['item' => 'Gale Rain Shell', 'cents' => 10000, 'days' => 30, 'finalSale' => false],
            'HB-51006' => ['item' => 'Driftwood Duffel', 'cents' => 8900, 'days' => 5, 'finalSale' => false],
            'HB-51007' => ['item' => 'Camp Lantern', 'cents' => 2900, 'days' => 10, 'finalSale' => true],
            'HB-51008' => ['item' => 'Insulated Trail Bottle', 'cents' => 2400, 'days' => 14, 'finalSale' => false],
            'HB-51009' => ['item' => 'Harbor Day Duffel', 'cents' => 5900, 'days' => 6, 'finalSale' => false],
            'HB-51010' => ['item' => 'Cedar Sleeping Bag', 'cents' => 12900, 'days' => 21, 'finalSale' => false],
        ];
    }

    public function requestedOrder(string $question, ?string $previousReply = null): ?string
    {
        if ($previousReply !== null && str_contains($previousReply, self::CHOOSE_ORDER) && preg_match('/^\s*(HB-\d{3,})[.!]?\s*$/i', $question, $matches) === 1) {
            return strtoupper($matches[1]);
        }

        $request = '/^\s*(?:(?:please|can you|could you|would you|i (?:want|need|would like) to|i[\x27’]d like to)\s+)?(?:start|submit|create|initiate)\s+(?:(?:a|my|the)\s+)?return\b|^\s*(?:please|can you|could you|would you|i (?:want|need|would like) to|i[\x27’]d like to)\s+(?:return|send back)\b/iu';

        if (preg_match($request, $question) !== 1 && ! $this->asksAboutPackagingAndReturn($question)) {
            return null;
        }

        return preg_match('/\bHB-\d{3,}\b/i', $question, $matches) === 1
            ? strtoupper($matches[0])
            : '';
    }

    public function asksAboutPackagingAndReturn(string $question): bool
    {
        return preg_match('/\b(?:original (?:shipping )?box|original packaging)\b/i', $question) === 1
            && preg_match('/\band\s+(?:can|could|would)\s+you\s+(?:start|initiate|submit)\s+(?:a|the|my)\s+return\b/i', $question) === 1;
    }

    public function mentionsSampleOrder(string $question): bool
    {
        return preg_match('/\bHB-\d{3,}\b/i', $question, $matches) === 1
            && isset($this->orders()[strtoupper($matches[0])]);
    }

    /**
     * @param  Collection<int, ChatMessage>  $messages
     * @return array{order: string|null, status: string|null}
     */
    public function currentReply(DemoSession $session, Collection $messages): array
    {
        $latest = $messages->last();
        $question = $messages->last(fn (ChatMessage $message): bool => $message->role === 'user');
        if ($latest?->role !== 'assistant' || $question === null) {
            return ['order' => null, 'status' => null];
        }

        $previous = $messages->last(fn (ChatMessage $message): bool => $message->id < $question->id && $message->role === 'assistant');
        $order = $this->requestedOrder($question->body, $previous?->body);
        $result = $order === null ? null : $this->assess($session, $order, $question->body);

        return ['order' => $order, 'status' => $result !== null && $this->matchesReply($latest->body, $result['message'], $question->body) ? $result['status'] : null];
    }

    public function matchesReply(string $body, string $expected, string $question): bool
    {
        return $body === $expected || ($this->asksAboutPackagingAndReturn($question) && str_ends_with($body, "\n\n".$expected));
    }

    public function orderPrompt(string $question): string
    {
        $hint = match (true) {
            preg_match('/\btrail pack\b/i', $question) === 1 => ' To try this with the sample Trail Pack, reply with HB-51001.',
            preg_match('/\b(?:ridgeline 2p tent|my tent|the tent)\b|\$249(?:\.00)?\s+tent\b/i', $question) === 1 => ' To try this with the sample tent, reply with HB-51003.',
            default => '',
        };

        return self::CHOOSE_ORDER.$hint;
    }

    /** @return array{status: string, message: string} */
    public function assess(DemoSession $session, string $number, string $question = ''): array
    {
        $order = $this->orders()[$number] ?? null;

        if ($order === null) {
            return ['status' => 'choose', 'message' => $this->orderPrompt($question)];
        }

        $reference = $this->requests($session, $number)->value('reference');

        if (is_string($reference)) {
            return ['status' => 'saved', 'message' => 'Your return is confirmed in this demo. Your reference is '.$reference.'. Keep it for your records. You’re all set here. No refund or shipping label is issued.'];
        }

        $item = $order['item'].' ($'.number_format($order['cents'] / 100, 2).')';

        return match (true) {
            $order['finalSale'] => ['status' => 'blocked', 'message' => $item.' is final sale. Final-sale items are excluded from returns.'],
            $order['days'] > 30 => ['status' => 'blocked', 'message' => $item.' is outside the return window. Eligible items must be unused with tags, within 30 days of delivery, and cost $100 or less at purchase.'],
            $order['cents'] > self::AUTO_APPROVAL_CENTS => ['status' => 'review', 'message' => $item.' exceeds the $100 auto-approval limit. Higher-priced items can be sent to support for review.'],
            default => ['status' => 'eligible', 'message' => 'Eligible items must be unused with tags, within 30 days of delivery, and cost $100 or less at purchase. Confirm that your '.$item.' is unused with tags attached to save the return.'],
        };
    }

    /** @return array{status: string, message: string} */
    public function confirm(DemoSession $session, string $number): array
    {
        return DB::transaction(function () use ($session, $number): array {
            DemoSession::query()->lockForUpdate()->findOrFail($session->id);

            $result = $this->assess($session, $number);

            if ($result['status'] !== 'eligible') {
                return $result;
            }

            $reference = 'RT-'.strtoupper((string) Str::ulid());
            $this->requests($session, $number)->insert([
                'demo_session_id' => $session->id,
                'order_number' => $number,
                'reference' => $reference,
                'created_at' => now(),
            ]);

            return ['status' => 'saved', 'message' => 'Your return is confirmed in this demo. Your reference is '.$reference.'. Keep it for your records. You’re all set here. No refund or shipping label is issued.'];
        });
    }

    private function requests(DemoSession $session, string $number): Builder
    {
        return DB::table('harbor_returns')
            ->where('demo_session_id', $session->id)
            ->where('order_number', $number);
    }

    public function restart(DemoSession $session): void
    {
        DB::table('harbor_returns')->where('demo_session_id', $session->id)->delete();
    }
}
