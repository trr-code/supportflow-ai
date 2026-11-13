<?php

use App\Enums\MessageAuthorType;
use App\Enums\MessageVisibility;
use App\Enums\SuggestedReplyStatus;
use App\Enums\TicketCategory;
use App\Enums\TicketEventType;
use App\Enums\TicketPriority;
use App\Enums\TicketSentiment;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Jobs\GenerateSuggestedReply;
use App\Models\KnowledgeArticle;
use App\Models\SuggestedReply;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Pest\Browser\Playwright\Client;
use Pest\Browser\Playwright\Page;

test('reply history expands and collapses inline', function (int $width) {
    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create(['status' => TicketStatus::AwaitingReview]);
    for ($index = 1; $index <= 30; $index++) {
        $ticket->messages()->create(['visibility' => MessageVisibility::Public, 'author_type' => MessageAuthorType::Customer, 'body' => 'Preview message '.$index.'.', 'approved_at' => now()]);
    }
    $page = visit(route('agent.tickets.show', $ticket), ['viewport' => ['width' => $width, 'height' => 900]]);
    $page->click('#workspace-conversation')->assertSee('Preview message 30.');
    expect($page->script("document.querySelectorAll('[data-reply-message]').length"))->toBe(6);
    $page->click('[data-earlier-replies]');
    expect(waitUntilScriptIsTrue($page, "document.querySelectorAll('[data-reply-message]').length === 12"))->toBeTrue();
    foreach ([18, 24, 30] as $count) {
        $page->click('[data-earlier-replies]');
        expect(waitUntilScriptIsTrue($page, "document.querySelectorAll('[data-reply-message]').length === ".$count))->toBeTrue();
        expect($page->script("(() => { const r = document.querySelector('[data-reply-message]').getBoundingClientRect(); return r.bottom > 0 && r.top < innerHeight; })()"))->toBeTrue();
    }
    $page->assertSee('All 30 messages are shown.');
    expect($page->script("document.querySelectorAll('dialog[open]').length"))->toBe(0);
    $page->screenshot(filename: 'reply-history-inline-'.$width);
    $page->click('[data-recent-replies-bottom]')->assertSee('Showing 6 most recent messages.');
    expect($page->script("document.querySelectorAll('[data-reply-message]').length"))->toBe(6);
    expect($ticket->messages()->count())->toBe(30);
    $page->assertNoJavaScriptErrors();
})->with([1440, 390]);

test('ticket save feedback stays by its action without a first-save jump', function (int $width) {
    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create(['status' => TicketStatus::AwaitingReview, 'priority' => TicketPriority::Low]);
    SuggestedReply::query()->create(['ticket_id' => $ticket->id, 'body' => 'A reply to review.', 'grounded' => true, 'status' => SuggestedReplyStatus::Pending, 'cited_chunk_ids' => []]);
    $page = visit(route('agent.tickets.show', $ticket), ['viewport' => ['width' => $width, 'height' => 900]]);
    $page->click('#tools-activity')->click('#tools-details');
    foreach (['medium', 'low'] as $priority) {
        $page->select('select[wire\\:model="priority"]', $priority);
        $page->script("document.querySelector('.showroom-ticket-tools form button').scrollIntoView({ block: 'center', behavior: 'instant' })");
        $measure = "(() => { const button = document.querySelector('.showroom-ticket-tools form button'); const panel = document.querySelector('.showroom-page-body'); return [button.getBoundingClientRect().top, panel.scrollTop, window.scrollY]; })()";
        $before = $page->script($measure);
        $page->click('Save details')->assertSee('Details saved. No reply was sent.');
        expect(waitUntilScriptIsTrue($page, "!document.querySelector('.showroom-ticket-tools form button').disabled"))->toBeTrue();
        $after = $page->script($measure);
        foreach ($before as $index => $value) {
            expect(abs($after[$index] - $value))->toBeLessThan(2);
        }
        expect($ticket->fresh()->priority->value)->toBe($priority);
        expect($page->script("(() => { const button = document.querySelector('.showroom-ticket-tools form button').getBoundingClientRect(); const feedback = document.querySelector('[data-details-feedback]').getBoundingClientRect(); return Math.abs(button.top - feedback.top) < 50 && feedback.bottom > 0 && feedback.top < innerHeight; })()"))->toBeTrue();
    }
    $page->click('#workspace-review')->fill('#ticket-draft', 'My updated private draft.')->click('Save edits')->assertSee('Draft saved.');
    expect($page->script("(() => { const feedback = document.querySelector('[data-reply-feedback]'); const pill = feedback.querySelector('span'); return pill.classList.contains('bg-emerald-50') && pill.classList.contains('text-emerald-950') && pill.classList.contains('ring-emerald-200') && pill.getBoundingClientRect().right <= feedback.getBoundingClientRect().right + 1; })()"))->toBeTrue();
    $page->script("document.querySelector('[data-reply-feedback]').scrollIntoView({ block: 'center', behavior: 'instant' })");
    $page->screenshot(filename: 'reply-save-pill-'.$width);
    expect($page->script("document.querySelector('[data-reply-feedback]').previousElementSibling.hasAttribute('data-manual-reply')"))->toBeTrue();
    $page->click('#workspace-notes')->fill('#ticket-note', 'A private team note.')->click('[aria-label="Add note"]')->assertSee('Internal note saved');
    expect($page->script("document.querySelector('[data-note-feedback]').closest('form') !== null"))->toBeTrue();
    expect($page->script("document.querySelector('[data-note-feedback] span').classList.contains('bg-emerald-50')"))->toBeTrue();
    $page->assertNoJavaScriptErrors();
})->with([1440, 390]);

test('routine save confirmation expires without a jump and returns on an identical save', function (int $width) {
    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create(['status' => TicketStatus::AwaitingReview, 'priority' => TicketPriority::Low]);
    $page = visit(route('agent.tickets.show', $ticket), ['viewport' => ['width' => $width, 'height' => 900]]);
    $page->click('#tools-details')->click('Save details')->assertSee('Details saved. No reply was sent.');
    expect($page->script("(() => { const pill = document.querySelector('[data-details-feedback] span'); return pill.classList.contains('bg-emerald-50') && pill.classList.contains('text-emerald-950') && pill.classList.contains('ring-emerald-200') && parseFloat(getComputedStyle(pill).borderRadius) > 100; })()"))->toBeTrue();
    $page->screenshot(filename: 'details-save-pill-'.$width);
    $measure = "(() => { const button = document.querySelector('.showroom-ticket-tools form button'); return [button.getBoundingClientRect().top, document.querySelector('.showroom-page-body').scrollTop, window.scrollY]; })()";
    $before = $page->script($measure);
    if ($width === 1440) {
        $page->hover('[data-details-feedback]')->wait(9)->assertSee('Details saved. No reply was sent.');
        $page->hover('.showroom-ticket-tools form button');
    }
    $page->wait(9)->assertDontSee('Details saved. No reply was sent.');
    expect($page->script($measure))->toBe($before);
    $page->click('#tools-activity')->click('#tools-details')->assertDontSee('Details saved. No reply was sent.');
    $page->click('Save details')->assertSee('Details saved. No reply was sent.')->assertNoJavaScriptErrors();
    expect($ticket->fresh()->priority)->toBe(TicketPriority::Low);
})->with([1440, 390]);

test('regeneration warning stays visible instead of expiring like a success', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $ticket = Ticket::factory()->create(['status' => TicketStatus::AwaitingReview]);
    SuggestedReply::query()->create(['ticket_id' => $ticket->id, 'body' => 'A reply to review.', 'grounded' => true, 'status' => SuggestedReplyStatus::Pending, 'cited_chunk_ids' => []]);
    config(['supportflow.rate_limits.regenerate.max_attempts' => 1]);
    RateLimiter::hit('regenerate|'.$user->id.'|'.$ticket->id, 60);
    $page = visit(route('agent.tickets.show', $ticket));
    $page->click('Regenerate')->assertSee('Regeneration limit reached.');
    $page->wait(9)->assertSee('Regeneration limit reached.')->assertNoJavaScriptErrors();
    expect($page->script("document.querySelector('[data-reply-feedback]').dataset.feedbackTone"))->toBe('warning');
    expect($ticket->fresh()->status)->toBe(TicketStatus::AwaitingReview);
});

test('ticket details and activity have focused guides without technical diagnostics', function (int $width) {
    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create(['status' => TicketStatus::AwaitingReview]);
    SuggestedReply::query()->create(['ticket_id' => $ticket->id, 'body' => 'A reply to review.', 'grounded' => true, 'status' => SuggestedReplyStatus::Pending, 'cited_chunk_ids' => []]);
    for ($index = 0; $index < 9; $index++) {
        $event = $ticket->events()->make(['type' => $index === 0 ? TicketEventType::Created : TicketEventType::TriageCompleted, 'actor' => 'system']);
        $event->created_at = now()->subMinutes(9 - $index);
        $event->save();
    }
    $page = visit(route('agent.tickets.show', $ticket), ['viewport' => ['width' => $width, 'height' => 900]]);
    $page->click('#tools-details')->assertVisible('[data-details-guide]')->assertMissing('[data-workspace-guide]')
        ->assertSee('Update ticket details')->assertSee('Edit how this request is categorized and routed.')
        ->assertSee('Status changes apply immediately. No reply is sent.');
    expect($page->script("document.querySelector('.showroom-ticket-tools form').parentElement.textContent.includes('AI confidence')"))->toBeFalse();
    $page->script("document.querySelector('.showroom-ticket-tools').scrollIntoView({ block: 'start' })");
    $page->screenshot(filename: 'ticket-details-focused-guide-'.$width);
    $page->script("document.querySelector('#demo-guide .showroom-guide-scroll').scrollTop = 10000");
    $page->click('#tools-activity')->assertVisible('[data-activity-guide]')->assertMissing('[data-details-guide]')
        ->assertSee('Review ticket activity')->assertSee('AI assessment completed')->assertSee('System')
        ->assertSee('Select Review reply at the top of the ticket to bring back the reply walkthrough in this sidebar.')
        ->assertDontSee('Technical details')->assertDontSee('Ticket created');
    expect($page->script("document.querySelector('#demo-guide .showroom-guide-scroll').scrollTop"))->toBe(0);
    $page->script("document.querySelector('.showroom-ticket-tools').scrollIntoView({ block: 'start' })");
    $page->screenshot(filename: 'ticket-activity-focused-guide-'.$width);
    $page->keys('[data-earlier-activity]', 'Enter')->assertSee('Earlier activity')->assertSee('Ticket created')->assertMissing('dialog[open]')
        ->assertSee('All 9 activity entries are shown.')->assertMissing('[data-earlier-activity]');
    $page->click('#workspace-notes')->assertVisible('[data-workspace-guide]')->assertSee('Keep private team notes');
    $page->click('#tools-details')->assertVisible('[data-details-guide]');
    $page->click('#workspace-review')->assertSee('Review and send a reply')->assertMissing('[data-details-guide]');
    $page->click('#tools-activity')->assertVisible('[data-activity-guide]')->click('#ticket-manual-open')->assertVisible('[data-manual-reply-guide]')->assertMissing('[data-activity-guide]')
        ->assertSee('Write a reply to the customer');
    $page->click('#tools-details')->assertVisible('[data-details-guide]')->click('#ticket-suggested-open')->assertVisible('[data-suggested-reply-guide]')->assertMissing('[data-details-guide]')
        ->assertSee('Review and send a reply')->assertNoJavaScriptErrors();
    expect($ticket->fresh()->status)->toBe(TicketStatus::AwaitingReview);
    expect($ticket->messages()->count())->toBe(0);
})->with([1440, 390]);

test('earlier activity appends inline without duplicates or a modal', function (int $width) {
    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Resolved]);
    for ($index = 0; $index < 17; $index++) {
        $event = $ticket->events()->make(['type' => TicketEventType::StatusChanged, 'actor' => 'Walkthrough actor '.$index]);
        $event->created_at = now()->subMinutes(17 - $index);
        $event->save();
    }
    $page = visit(route('agent.tickets.show', $ticket), ['viewport' => ['width' => $width, 'height' => 900]]);
    $page->click('#tools-activity')->assertSee('Showing 8 recent activity entries.');
    $initial = $page->script("[...document.querySelectorAll('[data-activity-event]')].map(el => el.getAttribute('wire:key'))");
    expect(count($initial))->toBe(8);
    $position = $page->script("(() => { const button = document.querySelector('[data-earlier-activity]'); button.scrollIntoView({ block: 'nearest', behavior: 'instant' }); button.focus({ preventScroll: true }); return [Math.round(window.scrollY), Math.round(document.querySelector('.showroom-page-body').scrollTop)]; })()");
    $page->keys('[data-earlier-activity]', 'Enter')->assertSee('Showing 16 recent activity entries.')->assertMissing('dialog[open]');
    $expanded = $page->script("[...document.querySelectorAll('[data-activity-event]')].map(el => el.getAttribute('wire:key'))");
    expect(count($expanded))->toBe(16);
    expect(array_slice($expanded, 0, 8))->toBe($initial);
    expect(array_unique($expanded))->toHaveCount(16);
    expect(waitUntilScriptIsTrue($page, "document.activeElement === document.querySelectorAll('[data-activity-event]')[8]"))->toBeTrue();
    expect($page->script("[Math.round(window.scrollY), Math.round(document.querySelector('.showroom-page-body').scrollTop)]"))->toBe($position);
    $page->screenshot(filename: 'activity-inline-expanded-'.$width);
    $page->keys('[data-earlier-activity]', 'Enter')->assertSee('All 17 activity entries are shown.')->assertMissing('[data-earlier-activity]')->assertMissing('dialog[open]');
    $complete = $page->script("[...document.querySelectorAll('[data-activity-event]')].map(el => el.getAttribute('wire:key'))");
    expect(array_unique($complete))->toHaveCount(17);
    expect(array_slice($complete, 0, 16))->toBe($expanded);
    $page->assertSee('Walkthrough actor 0')->assertNoJavaScriptErrors();
    $page->click('#tools-details')->click('#tools-activity')->assertSee('All 17 activity entries are shown.');
    $page->click('[data-recent-activity]')->assertSee('Showing 8 recent activity entries.')
        ->assertMissing('[data-recent-activity]')->assertSee('Show earlier activity');
    expect($page->script("[...document.querySelectorAll('[data-activity-event]')].map(el => el.getAttribute('wire:key'))"))->toBe($initial);
    expect(waitUntilScriptIsTrue($page, "document.activeElement === document.querySelector('[data-activity-event]')"))->toBeTrue();
    $page->keys('[data-earlier-activity]', 'Enter')->assertSee('Showing 16 recent activity entries.')->assertNoJavaScriptErrors();
    expect($ticket->events()->count())->toBe(17);
    expect($ticket->messages()->count())->toBe(0);
})->with([1440, 390]);

test('policy browsing returns to the ticket with unsent edits intact', function (int $width) {
    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create(['status' => TicketStatus::AwaitingReview]);
    $reply = SuggestedReply::query()->create(['ticket_id' => $ticket->id, 'body' => 'Original suggested reply.', 'grounded' => true, 'status' => SuggestedReplyStatus::Pending, 'cited_chunk_ids' => []]);
    $article = KnowledgeArticle::query()->create(['title' => 'Return window', 'slug' => 'policy-roundtrip', 'category' => 'returns', 'body' => "Unused returns within thirty days.\n\n## Box\nA sturdy carton is fine.", 'is_published' => true, 'is_seeded' => true]);

    $page = visit(route('agent.tickets.show', $ticket), ['viewport' => ['width' => $width, 'height' => 900]]);
    $page->fill('#ticket-draft', 'My unsent edited reply.');
    $page->click('#ticket-manual-open')->fill('#ticket-reply', 'My independent manual reply.');
    if ($width < 768) {
        $page->click('.showroom-mobile-toggle');
    }
    $page->click('a[aria-label="Browse policies"]')->assertSee('Harbor Outfitters policies')->assertSee('Back to ticket');
    if ($width < 768) {
        $page->click('.showroom-mobile-toggle');
    }
    $page->assertVisible('nav[aria-label="Agent desk"]');
    if ($width < 768) {
        $page->click('.showroom-mobile-toggle');
    }
    $page->click('[wire\\:click="filterCategory(\'returns\')"]')
        ->assertVisible('[aria-label="Filter by category"] [aria-pressed="true"]:has-text("Returns")')
        ->click('.showroom-policy-grid .policy-link[href*="topic=returns"]')
        ->assertSee('A sturdy carton is fine.');
    $page->assertDontSee('Ask the assistant');
    if ($width < 768) {
        $page->click('.showroom-mobile-toggle');
    }
    $page->assertVisible('nav[aria-label="Agent desk"]')->assertSee('Read a policy');
    if ($width < 768) {
        $page->click('.showroom-mobile-toggle');
    }
    expect($page->script("Math.abs(document.querySelector('[data-policy-content]').getBoundingClientRect().width - document.querySelector('.lucid-page-header').getBoundingClientRect().width)"))->toBeLessThanOrEqual(1);
    expect($page->script("document.querySelectorAll('h1').length"))->toBe(1);
    expect($page->script('document.documentElement.scrollWidth <= innerWidth + 1'))->toBeTrue();
    $page->screenshot(filename: 'policy-article-'.$width);
    $page->click('.lucid-page-header a:not([data-policy-return])')->assertSee('Harbor Outfitters policies');
    expect($page->script("document.querySelector('.showroom-policy-browser [aria-pressed=\"true\"]').textContent.trim()"))->toBe('Returns');
    expect($page->script("getComputedStyle(document.querySelector('.showroom-policy-grid > li:last-child')).borderBottomWidth"))->toBe('0px');
    $page->screenshot(filename: 'policy-catalog-'.$width);
    $page->click('[data-policy-return]')->assertValue('#ticket-reply', 'My independent manual reply.');
    $page->click('#ticket-suggested-open')->assertValue('#ticket-draft', 'My unsent edited reply.')->assertNoJavaScriptErrors();
    expect($reply->fresh()->body)->toBe('Original suggested reply.');
    expect($ticket->fresh()->status)->toBe(TicketStatus::AwaitingReview);
})->with([1440, 390]);

test('unsafe ticket warnings stay visible beside the assessment', function () {
    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Escalated, 'injection_suspected' => true]);
    $page = visit(route('agent.tickets.show', $ticket));
    $page->assertSee('Unsafe instructions detected. No AI reply was created. Please respond manually.')
        ->assertSee('Write your own reply')->assertNoJavaScriptErrors();
    $page->assertVisible('#ticket-assessment')->assertVisible('#tools-sources');
});

test('visible reply actions dispatch regenerate and reject', function () {
    $this->actingAs(User::factory()->create());
    Queue::fake();
    $ticket = Ticket::factory()->create(['status' => TicketStatus::AwaitingReview]);
    SuggestedReply::query()->create(['ticket_id' => $ticket->id, 'body' => 'A proposed reply to review.', 'grounded' => true, 'status' => SuggestedReplyStatus::Pending, 'cited_chunk_ids' => []]);
    $page = visit(route('agent.tickets.show', $ticket));
    $page->assertVisible('[wire\\:click="regenerate"]')->assertVisible('[wire\\:click="reject"]');
    $page->keys('[wire\\:click="regenerate"]', 'Enter')->assertSee('Regenerating a grounded draft');
    Queue::assertPushed(GenerateSuggestedReply::class);
    $page->click('[wire\\:click="reject"]')->assertSee('Suggestion rejected');
    expect($ticket->fresh()->status)->toBe(TicketStatus::Escalated);
    $page->assertNoJavaScriptErrors();
});

test('ticket priority remains visible with a distinct urgency treatment', function (string $priority, string $background) {
    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create(['status' => TicketStatus::AwaitingReview, 'priority' => TicketPriority::from($priority)]);
    $page = visit(route('agent.tickets.show', $ticket));
    $page->assertVisible('[data-ticket-priority="'.$priority.'"]');
    expect($page->script("document.querySelector('[data-ticket-priority]').textContent.trim()"))->toBe(ucfirst($priority));
    expect($page->script("getComputedStyle(document.querySelector('[data-ticket-priority]')).backgroundColor"))->toBe($background);
})->with([['low', 'rgb(238, 240, 246)'], ['medium', 'rgb(255, 251, 235)'], ['high', 'rgb(255, 247, 237)'], ['urgent', 'rgb(254, 242, 242)']]);

test('the opened ticket shares the instructional layout and keeps reply, note, and approval controls', function () {
    $this->actingAs(User::factory()->create());
    fakeSupportAi();
    Http::preventStrayRequests();

    $article = KnowledgeArticle::query()->create([
        'title' => 'Return window',
        'slug' => 'ticket-workspace-return-window',
        'category' => TicketCategory::Returns,
        'body' => 'Unused returns within 30 days, with tags attached.',
        'is_published' => true,
        'is_seeded' => true,
    ]);
    $chunk = $article->chunks()->create([
        'heading' => 'Box not required',
        'body' => 'Unused returns within 30 days, with tags attached.',
        'token_count' => 8,
    ]);
    $returnChunk = $article->chunks()->create([
        'heading' => 'Return window',
        'body' => 'Unused items may be returned within 30 days of delivery with tags attached.',
        'token_count' => 16,
    ]);

    $ticket = Ticket::factory()->create([
        'subject' => 'Can I still return the Harbor Trail Pack?',
        'customer_name' => 'Maya Chen',
        'customer_email' => 'maya.chen@example.test',
        'product' => 'Harbor Trail Pack',
        'description' => 'I bought a Harbor Trail Pack 18 days ago. It is unused with tags. What is the return window and do I need the original box?',
        'ai_summary' => 'Customer asks about the return window and whether the original box is required for an unused Harbor Trail Pack purchased 18 days ago.',
        'decision_factors' => ['The pack is unused with tags.', 'The customer asks about the return window and original box.', 'No urgency is expressed.'],
        'status' => TicketStatus::AwaitingReview,
        'category' => TicketCategory::Returns,
        'priority' => TicketPriority::Low,
        'sentiment' => TicketSentiment::Neutral,
        'source' => TicketSource::Seeded,
        'is_seeded' => true,
    ]);

    TicketMessage::query()->create([
        'ticket_id' => $ticket->id,
        'visibility' => MessageVisibility::Public,
        'author_type' => MessageAuthorType::Customer,
        'body' => 'I bought a Harbor Trail Pack 18 days ago. It is unused with tags.',
        'approved_at' => now(),
    ]);

    SuggestedReply::query()->create([
        'ticket_id' => $ticket->id,
        'body' => "Hi Maya,\n\nYes. Unused items may be returned within 30 days of delivery as long as the tags are attached. The original shipping box is helpful but not required; you can use a sturdy carton instead.\n\nBest,\nAlex Rivera\nHarbor & Co Support",
        'grounded' => true,
        'status' => SuggestedReplyStatus::Pending,
        'cited_chunk_ids' => [$returnChunk->id, $chunk->id],
    ]);

    $page = visit(route('agent.tickets.show', $ticket), [
        'viewport' => ['width' => 1440, 'height' => 900],
    ]);

    $page->assertSourceMissing('@vite/client')
        ->assertSee('Can I still return the Harbor Trail Pack?')
        ->assertSee('Maya Chen')
        ->assertSee('Customer request')
        ->assertSee('Reply history')
        ->assertSee('Approve and send')
        ->assertSee('Policy sources available')
        ->assertDontSee('Expand editor')
        ->assertSee('Demo only · No email is sent.')
        ->assertDontSee('Send reply')
        ->assertDontSee('Source review');

    $page->assertVisible('#ticket-assessment')->assertVisible('#tools-sources');

    $page->click('View reasoning')->assertVisible('dialog[open]')
        ->assertSee('What AI noticed in the request')
        ->assertSee('These points help explain the category, priority, and sentiment.')
        ->assertSee('This is the AI’s own estimate, separate from the policy match below.')
        ->assertSee('Lucid uses this match to decide whether there is enough supporting information to suggest a reply.');
    expect($page->script("getComputedStyle(document.querySelector('dialog[open] h2')).fontSize"))->toBe('20px');
    expect($page->script("getComputedStyle(document.querySelector('dialog[open] h3')).fontSize"))->toBe('16px');
    $page->screenshot(filename: 'ticket-assessment-business-copy');
    $page->keys('dialog[open]', 'Escape')->assertMissing('dialog[open]');

    foreach ([[1440, 900], [1280, 870], [1800, 1215]] as [$width, $height]) {
        $page->resize($width, $height)->assertVisible('#demo-guide');
        expect($page->script("getComputedStyle(document.querySelector('.showroom-sidebar')).width"))->toBe('220px');
        expect($page->script("getComputedStyle(document.querySelector('#demo-guide')).width"))->toBe('320px');
        expect($page->script("getComputedStyle(document.querySelector('.showroom-page-body')).paddingLeft"))->toBe($width >= 1400 ? '28px' : '24px');
        expect($page->script("getComputedStyle(document.querySelector('.showroom-ticket-heading')).backgroundColor"))->toBe('rgba(0, 0, 0, 0)');
        expect($page->script("getComputedStyle(document.querySelector('#ticket-draft')).fontSize"))->toBe('16px');
        expect($page->script("getComputedStyle(document.querySelector('[data-customer-message]')).fontSize"))->toBe('14px');
        expect($page->script("getComputedStyle(document.querySelector('#ticket-assessment h2')).fontSize"))->toBe('16px');
        expect($page->script("getComputedStyle(document.querySelector('#ticket-assessment')).borderTopWidth"))->toBe('0px');
        expect($page->script("[...document.querySelectorAll('.showroom-assessment-meta dl > div')].every(el => el.querySelector('dd').getBoundingClientRect().top >= el.querySelector('dt').getBoundingClientRect().bottom + 3)"))->toBeTrue();
        expect($page->script(<<<'JS'
            (() => {
                const groups = [...document.querySelectorAll('.showroom-assessment-meta dl > div')];
                const action = document.querySelector('.showroom-assessment-meta button').getBoundingClientRect();
                return groups.length === 3 && groups.every(el => getComputedStyle(el.querySelector('dt')).fontWeight === '600' && getComputedStyle(el.querySelector('dd')).fontWeight === '400' && action.top >= el.querySelector('dt').getBoundingClientRect().bottom - 1);
            })()
        JS))->toBeTrue();
        expect($page->script("getComputedStyle(document.querySelector('.ticket-reply-section')).borderTopWidth"))->toBe('0px');
        expect($page->script("document.querySelector('.showroom-ticket-tools h3').textContent.trim()"))->toBe('Policy passages');
        expect($page->script("document.querySelector('#ticket-draft').getBoundingClientRect().height"))->toBe(256);
        expect($page->script("document.querySelector('#ticket-draft-actions').getBoundingClientRect().top - document.querySelector('#ticket-draft').getBoundingClientRect().bottom"))->toBeGreaterThanOrEqual(16);
        expect($page->script('document.querySelector("#ticket-draft").scrollHeight <= document.querySelector("#ticket-draft").clientHeight'))->toBeTrue();
        expect($page->script("document.querySelector('#ticket-manual-row').getBoundingClientRect().top - document.querySelector('#ticket-draft-actions').getBoundingClientRect().bottom"))->toBeGreaterThanOrEqual(16);
        expect($page->script("getComputedStyle(document.querySelector('#ticket-manual-row')).borderTopWidth"))->toBe('0px');
        expect($page->script("Math.abs(document.querySelector('[data-customer-message]').getBoundingClientRect().left - document.querySelector('#ticket-review-panel h2').getBoundingClientRect().left)"))->toBeLessThanOrEqual(1);
        expect($page->script("getComputedStyle(document.querySelector('.lucid-workspace-main')).borderTopWidth"))->toBe('0px');
        expect($page->script("getComputedStyle(document.querySelector('.lucid-workspace-main')).borderRadius"))->toBe('0px');
        expect($page->script("getComputedStyle(document.querySelector('.lucid-workspace-side')).borderRadius"))->toBe('0px');
        expect($page->script("document.querySelector('.showroom-ticket-tools').getBoundingClientRect().top > document.querySelector('#ticket-draft-actions').getBoundingClientRect().bottom"))->toBeTrue();
        expect($page->script("Math.abs(document.querySelector('.showroom-toolbar').getBoundingClientRect().bottom - document.querySelector('.showroom-guide-heading h2').getBoundingClientRect().bottom)"))->toBeLessThanOrEqual(1);
        expect($page->script('document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
        expect($page->script("getComputedStyle(document.querySelector('.showroom-guide-disclaimer')).fontSize"))->toBe('14px');
        expect($page->script("[...document.querySelectorAll('.showroom-support-steps > li')].every(el => getComputedStyle(el).paddingTop === '16px' && getComputedStyle(el).paddingBottom === '16px')"))->toBeTrue();
        $page->assertSee('Click inside the reply to edit it before sending.')->assertSee('Review and edit the reply')->assertNoJavaScriptErrors();
        expect($page->script("(() => { const buttons = [...document.querySelectorAll('#ticket-draft-actions [data-flux-button]')]; const widths = buttons.map(button => button.getBoundingClientRect().width); return buttons.length === 5 && Math.max(...widths) - Math.min(...widths) <= 1 && buttons.every(button => button.scrollWidth <= button.clientWidth); })()"))->toBeTrue();
        $page->screenshot(filename: 'ticket-shared-'.$width);
        expect($page->script("getComputedStyle(document.querySelector('.showroom-ticket-tools ul > li:last-child')).borderBottomWidth"))->toBe('0px');
        expect($page->script("getComputedStyle(document.querySelector('.showroom-ticket-tools ul > li:first-child')).borderBottomWidth"))->toBe('1px');
        if ($width === 1800) {
            expect($page->script("Math.abs(document.querySelectorAll('#ticket-draft-actions [data-flux-button]')[2].getBoundingClientRect().top - document.querySelector('#ticket-manual-open').getBoundingClientRect().top)"))->toBeLessThanOrEqual(1);
        }

    }
    $page->click('[aria-label="Hide instructions"]')->assertVisible('button[aria-controls="demo-guide"][aria-expanded="false"]')
        ->click('#workspace-conversation')->assertSee('Messages and replies visible to the customer on their ticket status page.');
    expect($page->script("getComputedStyle(document.querySelector('#demo-guide')).display"))->toBe('none');
    $page->click('Show instructions')->assertVisible('#demo-guide')->assertSee('Follow these steps to review the messages on this ticket.')
        ->click('#workspace-review')->assertSee('Review and edit the reply');
    $page->resize(390, 844);
    expect($page->script("document.querySelector('#ticket-manual-row').getBoundingClientRect().top - document.querySelector('#ticket-draft-actions').getBoundingClientRect().bottom"))->toBeGreaterThanOrEqual(16);
    expect($page->script('document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
    $page->assertVisible('#ticket-manual-open')->assertVisible('[wire\\:click="regenerate"]');
    $page->screenshot(filename: 'ticket-shared-phone');
    $page->resize(320, 844)->assertVisible('[wire\\:click="regenerate"]')->assertVisible('#ticket-manual-open');
    expect($page->script('document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
    $page->resize(390, 844);
    $page->fill('#ticket-draft', 'Phone draft stays private.')
        ->click('#ticket-manual-open')
        ->assertVisible('[aria-label="Send reply"]');
    expect(waitUntilScriptIsTrue($page, "document.activeElement.id === 'ticket-reply'"))->toBeTrue();
    $page->assertMissing('dialog[open]')->assertSee('Write a reply to the customer');
    expect($page->script("[...document.querySelectorAll('.ticket-reply-section textarea')].filter(el => el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden').length"))->toBe(1);
    $page->assertValue('#ticket-reply', '');
    $page->type('#ticket-reply', 'Keep this unsent reply.')
        ->click('Back to suggested reply')->assertVisible('#ticket-draft');
    expect($page->value('#ticket-draft'))->toBe('Phone draft stays private.');
    $page->click('#ticket-manual-open');
    expect($page->value('#ticket-reply'))->toBe('Keep this unsent reply.');
    $page->click('Back to suggested reply')->assertVisible('#ticket-draft');

    $page->resize(1440, 900);

    $longDraft = str_repeat("A longer policy explanation remains editable.\n", 30);
    $originalHeight = $page->script('document.querySelector("#ticket-draft").clientHeight');
    $page->fill('#ticket-draft', $longDraft);
    expect($page->script('document.querySelector("#ticket-draft").clientHeight'))->toBe($originalHeight);
    expect($page->script('document.querySelector("#ticket-draft").getBoundingClientRect().height'))->toBe(256);
    expect($page->script('document.querySelector("#ticket-draft").scrollHeight > document.querySelector("#ticket-draft").clientHeight'))->toBeTrue();
    $page->keys('#ticket-draft', ['Control', 'End']);
    expect($page->script('document.querySelector("#ticket-draft").scrollTop > 0'))->toBeTrue();
    $page->assertNoJavaScriptErrors();

    $edited = "Hi Maya,\n\nEdited before switching tabs.\n\nBest,\nAlex Rivera\nHarbor & Co Support";

    $page->fill('#ticket-draft', $edited)
        ->click('#workspace-conversation')
        ->assertSee('Messages and replies visible to the customer on their ticket status page.')
        ->assertSee('Follow these steps to review the messages on this ticket.')
        ->assertSee('I bought a Harbor Trail Pack 18 days ago. It is unused with tags.')
        ->click('#workspace-review');

    expect($page->value('#ticket-draft'))->toBe($edited);

    $page->assertSee('View reasoning')->assertSee('Open policy')->assertVisible('[data-ticket-priority="low"]');
    $page->click('Save edits')->assertSee('Draft saved.');
    $page->assertVisible('#ticket-assessment')->assertVisible('#tools-sources');
    $page->assertMissing('.showroom-ticket-section-icon');
    $page->screenshot(filename: 'ticket-disclosures-open');

    clickTicketControl($page, 'Box not required');

    $page->assertSee('From: Return window')
        ->assertSee('Unused returns within 30 days, with tags attached.')
        ->click('dialog[open] [aria-label="Close modal"]')
        ->click('#tools-details')
        ->assertSee('Save details')
        ->assertSee('Update ticket details')
        ->click('#tools-activity')
        ->assertSee('Timeline')
        ->click('#workspace-notes')
        ->assertSee('Follow these steps to keep private notes for the support team.')
        ->type('#ticket-note', 'Keep this note off the customer page.')
        ->keys('#ticket-note', 'Control+Enter')
        ->assertSee('Internal note saved')
        ->assertSee('Keep this note off the customer page.')
        ->click('#workspace-conversation')
        ->assertDontSee('Keep this note off the customer page.')
        ->click('#workspace-review')
        ->assertDontSee('Keep this note off the customer page.')
        ->click('Write your own reply')
        ->assertVisible('[aria-label="Send reply"]')
        ->click('Back to suggested reply')->assertVisible('#ticket-draft')
        ->click('Write your own reply')
        ->type('#ticket-reply', 'A person can send this reply.')
        ->keys('#ticket-reply', 'Control+Enter')
        ->assertSee('Human reply sent to the customer status page (simulated).')
        ->assertSee('A person can send this reply.')
        ->click('#workspace-review')
        ->click('Approve and send')
        ->assertSee('Reply sent to the customer status page')
        ->assertSee('Read the sent reply')->assertDontSee('Review and edit the reply')->assertNoJavaScriptErrors();
});

test('reply mode switches preserve internal scroll and avoid focus scrolling in Agent Desk', function (int $width, int $height) {
    $this->actingAs(User::factory()->create());
    fakeSupportAi();
    Http::preventStrayRequests();
    $ticket = Ticket::factory()->create(['status' => TicketStatus::AwaitingReview, 'description' => str_repeat('The customer asks about returning an unused pack with tags. ', 8), 'ai_summary' => 'The customer wants to check return eligibility.']);
    SuggestedReply::query()->create(['ticket_id' => $ticket->id, 'body' => "Hi Maya,\n\nUnused returns are accepted within thirty days with tags attached. A sturdy carton is fine.\n\nBest,\nSupport", 'grounded' => true, 'status' => SuggestedReplyStatus::Pending, 'cited_chunk_ids' => []]);
    $page = visit(route('agent.tickets.show', $ticket), ['viewport' => ['width' => $width, 'height' => $height]]);
    $page->refresh();
    foreach (['Write your own reply', 'Back to suggested reply'] as $action) {
        $page->script('window.replyAction = '.json_encode($action));
        $page->script(<<<'JS'
            (() => {
                [...document.querySelectorAll('button')].find(el => el.textContent.trim() === window.replyAction && el.getClientRects().length)?.scrollIntoView({ block: 'center' });
                const panel = document.querySelector('.showroom-page-body, .showroom-task-scroll');
                window.replyScrollTrace = { initial: panel.scrollTop, frames: [], running: true };
                const sample = () => {
                    if (!window.replyScrollTrace.running) return;
                    window.replyScrollTrace.frames.push({ actual: panel.scrollTop, expected: Math.min(window.replyScrollTrace.initial, Math.max(0, panel.scrollHeight - panel.clientHeight)) });
                    requestAnimationFrame(sample);
                };
                requestAnimationFrame(sample);
            })()
        JS);
        clickTicketControl($page, $action);
        $page->wait(1.2);
        $baseline = 'window.replyScrollTrace.initial';
        $movement = $page->script('window.replyScrollTrace.running = false; Math.max(0, ...window.replyScrollTrace.frames.map(frame => Math.abs(frame.actual - '.$baseline.')))');
        expect($movement)->toBeLessThanOrEqual(2);
    }
})->with([[1800, 1215], [1440, 900]]);

test('reply switches keep the viewport steady at both reported button positions', function (int $width, int $height, float $position, float $scale = 1) {
    $this->actingAs(User::factory()->create());
    fakeSupportAi();
    Http::preventStrayRequests();
    $article = KnowledgeArticle::query()->create(['title' => 'Returns', 'slug' => 'scroll-return-policy', 'category' => 'returns', 'body' => 'Unused returns within thirty days.', 'is_published' => true, 'is_seeded' => true]);
    $chunks = collect(['Return window', 'Box not required'])->map(fn (string $heading) => $article->chunks()->create(['heading' => $heading, 'body' => 'Unused packs with tags may be returned within thirty days. The original box is not required.', 'token_count' => 20]));
    $ticket = Ticket::factory()->create(['status' => TicketStatus::AwaitingReview, 'subject' => 'Can I still return the Harbor Trail Pack?', 'product' => 'Harbor Trail Pack', 'description' => 'I bought a Harbor Trail Pack 18 days ago. It is unused with tags. What is the return window and do I need the original box?', 'ai_summary' => 'Customer asks about the return window for an unused Harbor Trail Pack purchased 18 days ago and whether the original box is required.', 'category' => TicketCategory::Returns, 'priority' => TicketPriority::Low, 'sentiment' => TicketSentiment::Neutral]);
    SuggestedReply::query()->create(['ticket_id' => $ticket->id, 'body' => "Hi Maya,\n\nUnused returns are accepted within thirty days with tags attached. A sturdy carton is fine.\n\nBest,\nAlex Rivera\nHarbor & Co Support", 'grounded' => true, 'status' => SuggestedReplyStatus::Pending, 'cited_chunk_ids' => $chunks->pluck('id')->all()]);
    $page = visit(route('agent.tickets.show', $ticket), ['viewport' => ['width' => $width, 'height' => $height], 'deviceScaleFactor' => $scale]);
    $page->refresh();
    if ($scale !== 1.0) {
        $page->script("document.documentElement.style.zoom = '0.9'");
    }
    $page->script(<<<'JS'
        window.replyModeRequests = 0;
        const replyFetch = window.fetch;
        window.fetch = (resource, ...options) => {
            if (String(resource?.url ?? resource).includes('/livewire/update')) window.replyModeRequests++;
            return replyFetch(resource, ...options);
        };
    JS);
    foreach (['Write your own reply', 'Back to suggested reply', 'Write your own reply'] as $action) {
        $targetPosition = $action === 'Write your own reply' && ! isset($hasSwitched) ? 0.98 : $position;
        $hasSwitched = true;
        $page->script('window.replyAction = '.json_encode($action).'; window.replyPosition = '.$targetPosition);
        $page->script(<<<'JS'
            (() => {
                const button = [...document.querySelectorAll('button')].find(el => el.textContent.trim() === window.replyAction && el.getClientRects().length);
                const panel = document.querySelector('.showroom-page-body');
                const bounds = panel.getBoundingClientRect();
                const scrollingPage = !['auto', 'scroll'].includes(getComputedStyle(panel).overflowY);
                const target = scrollingPage ? innerHeight * window.replyPosition : bounds.top + bounds.height * window.replyPosition;
                const scroller = scrollingPage ? document.scrollingElement : panel;
                scroller.scrollTop += (button.getBoundingClientRect().top - target) / (bounds.height / panel.offsetHeight);
            })()
        JS);
        $page->wait(0.2);
        $point = $page->script(<<<'JS'
            (() => {
                const button = [...document.querySelectorAll('button')].find(el => el.textContent.trim() === window.replyAction && el.getClientRects().length);
                const box = button.getBoundingClientRect();
                const panel = document.querySelector('.showroom-page-body').getBoundingClientRect();
                const elements = [...document.querySelectorAll('html, body, #main-content, .showroom-secondary-area, .showroom-page-body, .lucid-workspace-main, #ticket-review-panel')];
                const measure = () => [...elements.map(el => el.scrollTop), window.scrollY, document.querySelector('.ticket-reply-section').getBoundingClientRect().top];
                window.positionTrace = { initial: measure(), frames: [], running: true };
                const sample = () => {
                    if (!window.positionTrace.running) return;
                    window.positionTrace.frames.push(measure());
                    requestAnimationFrame(sample);
                };
                requestAnimationFrame(sample);
                return { x: box.left + box.width / 2, y: Math.min(box.top + box.height / 2, panel.bottom - 4, innerHeight - 4) };
            })()
        JS);
        $playwright = $page->page();
        $guid = new ReflectionProperty($playwright, 'guid');
        iterator_to_array(Client::instance()->execute($guid->getValue($playwright), 'mouseClick', $point));
        expect(waitUntilScriptIsTrue($page, $action === 'Back to suggested reply' ? "document.activeElement.id === 'ticket-draft'" : "document.activeElement.id === 'ticket-reply'"))->toBeTrue(json_encode(['action' => $action, 'point' => $point, 'state' => $page->script('({ active: document.activeElement.id, hit: document.elementFromPoint('.$point['x'].','.$point['y'].")?.outerHTML, manual: document.querySelector('#ticket-reply').getBoundingClientRect().toJSON() })")]));
        $page->wait(2);
        $movement = $page->script('window.positionTrace.running = false; Math.max(0, ...window.positionTrace.frames.flatMap(frame => frame.map((value, index) => Math.abs(value - window.positionTrace.initial[index]))))');
        expect($movement)->toBeLessThanOrEqual(2);
        expect($page->script('window.replyModeRequests'))->toBe(0);
        $manual = $action !== 'Back to suggested reply';
        expect($page->script("document.querySelector('#demo-guide [data-manual-reply-guide]').getClientRects().length > 0"))->toBe($manual);
        expect($page->script("document.querySelector('#demo-guide [data-suggested-reply-guide]').getClientRects().length > 0"))->toBe(! $manual);
        expect($page->script("document.querySelector('#demo-guide .showroom-guide-heading p').textContent.trim()"))->toBe($manual ? 'Write a reply to the customer' : 'Review and send a reply');
    }
    if ($scale !== 1.0) {
        $heightBeforeAdjustment = $page->script("document.querySelector('.showroom-ticket').getBoundingClientRect().height");
        $page->script("document.querySelector('.showroom-page-body').scrollTop -= 1");
        $page->wait(0.2);
        $heightAfterAdjustment = $page->script("document.querySelector('.showroom-ticket').getBoundingClientRect().height");
        expect(abs($heightAfterAdjustment - $heightBeforeAdjustment))->toBeLessThanOrEqual(0.1);
    }
})->with([[1800, 1215, 0.5], [1800, 1215, 0.98], [1440, 900, 0.5], [1440, 900, 0.98], [2275, 1100, 0.5], [2275, 1100, 0.98], [1080, 900, 0.5], [1080, 900, 0.98], [2267, 1096, 0.5, 1.125], [2267, 1096, 0.98, 1.125]]);

function waitUntilScriptIsTrue(object $page, string $expression): bool
{
    for ($attempt = 0; $attempt < 25; $attempt++) {
        if ($page->script($expression)) {
            return true;
        }

        usleep(40_000);
    }

    return (bool) $page->script($expression);
}

function clickTicketControl(object $page, string $name): void
{
    /** @var Page $playwright */
    $playwright = $page->page();
    $button = $playwright->getByRole('button', ['name' => $name, 'exact' => true]);
    $button->scrollIntoViewIfNeeded();
    $box = $button->boundingBox();

    expect($box)->toBeArray()
        ->and($box['width'])->toBeGreaterThan(0)
        ->and($box['height'])->toBeGreaterThan(0);

    $guid = new ReflectionProperty($playwright, 'guid');
    iterator_to_array(Client::instance()->execute($guid->getValue($playwright), 'mouseClick', [
        'x' => $box['x'] + ($box['width'] / 2),
        'y' => $box['y'] + ($box['height'] / 2),
    ]));
}

test('internal notes use the shared unclipped growing composer', function (int $width) {
    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create();
    $page = visit(route('agent.tickets.show', $ticket), ['viewport' => ['width' => $width, 'height' => 900]]);
    $page->click('#workspace-notes')->assertSee('Enter starts a new line.');
    expect($page->script("(() => { const f=document.querySelector('#ticket-note'), r=f.getBoundingClientRect(), tools=document.querySelector('[data-note-editor] .lucid-reply-editor-tools').getBoundingClientRect(); return r.height >= 80 && tools.top >= r.bottom - 1 && getComputedStyle(f).fontSize === '16px' && getComputedStyle(f).resize === 'none'; })()"))->toBeTrue();
    $page->click('#ticket-note');
    expect($page->script("(() => { const editor=document.querySelector('[data-note-editor]'); const r=editor.getBoundingClientRect(); const inset=parseFloat(getComputedStyle(editor).outlineWidth)+parseFloat(getComputedStyle(editor).outlineOffset); for(let parent=editor.parentElement;parent;parent=parent.parentElement){const style=getComputedStyle(parent); if(['hidden','clip','auto','scroll'].includes(style.overflowX)){const bounds=parent.getBoundingClientRect();if(r.left-inset < bounds.left-1 || r.right+inset > bounds.right+1)return false;}} return inset >= 4; })()"))->toBeTrue();
    $page->fill('#ticket-note', 'Private team note.')->keys('#ticket-note', 'Enter');
    expect($ticket->messages()->count())->toBe(0);
    $page->click('[aria-label="Add note"]')->assertSee('Internal note saved.');
    expect($ticket->messages()->where('visibility', 'internal')->count())->toBe(1);
    $page->screenshot(filename: 'internal-notes-composer-'.$width);
    $page->assertNoJavaScriptErrors();
})->with([1440, 390]);

test('internal notes expand inline and collapse without exposing customer messages', function (int $width) {
    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create();
    for ($index = 1; $index <= 10; $index++) {
        $ticket->messages()->create(['visibility' => 'internal', 'author_type' => 'agent', 'body' => 'Private preview note '.$index.'.']);
    }
    $page = visit(route('agent.tickets.show', $ticket), ['viewport' => ['width' => $width, 'height' => 900]]);
    $page->click('#workspace-notes')->assertSee('Private preview note 10.');
    expect($page->script("document.querySelectorAll('[data-internal-note]').length"))->toBe(4);
    $page->click('[data-earlier-notes]');
    expect(waitUntilScriptIsTrue($page, "document.querySelectorAll('[data-internal-note]').length === 8"))->toBeTrue();
    $page->click('[data-earlier-notes]')->assertSee('All 10 notes are shown.');
    expect($page->script("document.querySelectorAll('dialog[open]').length"))->toBe(0);
    $page->screenshot(filename: 'internal-notes-inline-'.$width);
    $page->click('#workspace-review')->click('#workspace-notes');
    expect(waitUntilScriptIsTrue($page, "document.querySelectorAll('[data-internal-note]').length === 10"))->toBeTrue();
    $page->click('[data-recent-notes]');
    expect(waitUntilScriptIsTrue($page, "document.querySelectorAll('[data-internal-note]').length === 4"))->toBeTrue();
    $page->click('#workspace-conversation')->assertDontSee('Private preview note');
    expect($ticket->messages()->count())->toBe(10);
    $page->assertNoJavaScriptErrors();
})->with([1440, 390]);
