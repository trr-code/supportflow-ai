<?php

use App\Enums\MessageAuthorType;
use App\Enums\MessageVisibility;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Pest\Browser\Playwright\Playwright;

test('ticket link copying has local feedback and a usable failure fallback', function (bool $embedded, int $width) {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::AwaitingReview]);
    if ($embedded) {
        $this->withSession(['supportflow.harbor.center' => 'status', 'supportflow.harbor.status_token' => $ticket->public_token]);
    }
    $page = visit($embedded ? route('demo.harbor') : route('tickets.status', $ticket->public_token), ['viewport' => ['width' => $width, 'height' => 900]]);
    $page->script("Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText: async text => { window.copiedTicketLink = text; } } })");
    $page->click('Copy ticket link')->assertSeeIn('[data-ticket-copy-feedback]', 'Link copied.');
    expect($page->script('window.copiedTicketLink'))->toBe(route('tickets.status', $ticket->public_token));
    expect($page->script("document.querySelector('[data-ticket-copy-feedback] span').classList.contains('bg-emerald-200')"))->toBeTrue();
    $page->wait(6)->assertSeeIn('[data-ticket-copy-feedback]', 'Link copied.');
    $page->wait(3)->assertDontSee('Link copied.');
    $page->click('Copy ticket link')->assertSeeIn('[data-ticket-copy-feedback]', 'Link copied.');
    $page->script("void (navigator.clipboard.writeText = async () => { throw new Error('Clipboard blocked'); })");
    $page->click('Copy ticket link')->assertSee('Could not copy the link. Select it below and copy it manually.');
    $page->assertVisible('[data-ticket-copy] input');
    expect($page->script("document.querySelector('[data-ticket-copy] input').value"))->toBe(route('tickets.status', $ticket->public_token));
    $page->assertNoJavaScriptErrors();
})->with([[false, 1440], [false, 390], [true, 1440], [true, 390]]);

test('customer status polling follows controlled updates until a reply is sent', function () {
    $ticket = Ticket::factory()->create([
        'subject' => 'Where is the trail pack',
        'status' => TicketStatus::Submitted,
    ]);

    TicketMessage::query()->create([
        'ticket_id' => $ticket->id,
        'visibility' => MessageVisibility::Public,
        'author_type' => MessageAuthorType::Customer,
        'body' => 'The customer already described the missing pack.',
        'approved_at' => now(),
    ]);

    $page = visit(route('tickets.status', $ticket->public_token));

    $page->assertSee('The AI is still working.')
        ->assertSee('The customer already described the missing pack.');

    $ticket->update(['status' => TicketStatus::AwaitingReview]);

    Playwright::setTimeout(12000);

    $page->assertSee('AI prepared a reply. A support agent still has to review and send it.')
        ->assertDontSee('The AI is still working.');

    TicketMessage::query()->create([
        'ticket_id' => $ticket->id,
        'visibility' => MessageVisibility::Public,
        'author_type' => MessageAuthorType::Agent,
        'body' => 'Your prepaid label is ready to print.',
        'approved_at' => now(),
    ]);

    $page->assertSee('Your prepaid label is ready to print.');

    $polling = $page->script(<<<'JS'
        (() => document.querySelector('[wire\\:poll\\.5s\\.visible]') !== null)()
    JS);

    expect($polling)->toBeFalse();

    $ticket->update(['status' => TicketStatus::Escalated]);

    $page->script(<<<'JS'
        new Promise((resolve) => setTimeout(resolve, 6000))
    JS);

    $page->assertSee('Your prepaid label is ready to print.')
        ->assertDontSee('A person has this ticket.');

    Playwright::setTimeout(5000);
});
