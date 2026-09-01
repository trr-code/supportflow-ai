<?php

use App\Models\Ticket;
use App\Services\TicketIntakeService;

test('optional live OpenAI smoke for triage', function () {
    $ticket = Ticket::factory()->create([
        'subject' => 'Return an unused Harbor Trail Pack',
        'description' => 'The pack is unused with tags. What is the return window?',
    ]);

    app(TicketIntakeService::class)->process($ticket);

    $ticket->refresh();
    expect($ticket->ai_summary)->not->toBeEmpty();
})->group('openai')->skip(fn (): bool => ! filter_var(env('OPENAI_SMOKE'), FILTER_VALIDATE_BOOL));
