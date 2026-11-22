<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Support\ChatInjectionGate;
use Illuminate\Support\Collection;

class HarborChatHandoff
{
    public const ASK_ORDER = 'What’s your order number?';

    public const REVIEW = 'This return request needs staff review. Check the details below, then select Send support request. Your return has not been approved.';

    /**
     * @param  Collection<int, ChatMessage>  $messages
     * @return array{order: string, description: string}|null
     */
    public function proposal(Collection $messages): ?array
    {
        $users = $messages->where('role', 'user')->values();
        $last = $users->last();
        if ($last === null || ChatInjectionGate::blocks($last->body)) {
            return null;
        }
        $request = $last->body;
        $orderText = $request;
        $previousAssistant = $messages->last(fn (ChatMessage $message): bool => $message->id < $last->id && $message->role === 'assistant');
        $returns = app(HarborReturns::class);
        $sampleNumber = $returns->requestedOrder($request, $previousAssistant?->body);
        $sample = $sampleNumber === null ? null : ($returns->orders()[$sampleNumber] ?? null);
        if ($sample !== null && $sample['cents'] > HarborReturns::AUTO_APPROVAL_CENTS) {
            return ['order' => $sampleNumber, 'description' => $request."\nItem: ".$sample['item'].' ($'.number_format($sample['cents'] / 100, 2).')'];
        }
        $order = '';
        if (preg_match('/^\s*([A-Z0-9][A-Z0-9_-]{1,59})[.!]?\s*$/i', $request, $number) === 1) {
            $previous = $users->get($users->count() - 2);
            $assistant = $messages->last(fn (ChatMessage $message): bool => $message->id < $last->id && $message->role === 'assistant');
            if ($previous === null || $assistant?->body !== self::ASK_ORDER) {
                return null;
            }
            $order = strtoupper($number[1]);
            $request = $previous->body;
        }
        if (ChatInjectionGate::blocks($request)
            || preg_match('/\b(?:i want to|i would like to|i[\x27’]d like to|please)\s+(?:start a return|return|send back)\b/iu', $request) !== 1
            || preg_match('/\$\s*(\d+(?:\.\d{1,2})?)/', $request, $price) !== 1
            || (float) $price[1] <= HarborReturns::AUTO_APPROVAL_CENTS / 100) {
            return null;
        }
        if ($order === '' && preg_match('/\border\s*(?:number\s*)?(?:is\s*)?[:#]?\s*([A-Z0-9][A-Z0-9_-]{1,59})\b/i', $orderText, $number) === 1) {
            $order = strtoupper($number[1]);
        }

        return ['order' => $order, 'description' => $request.($order === '' ? '' : "\nOrder number: ".$order)];
    }
}
