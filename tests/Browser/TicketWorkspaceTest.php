<?php

use App\Enums\MessageAuthorType;
use App\Enums\MessageVisibility;
use App\Enums\SuggestedReplyStatus;
use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketSentiment;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Models\KnowledgeArticle;
use App\Models\SuggestedReply;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Pest\Browser\Playwright\Client;
use Pest\Browser\Playwright\Page;

test('the opened ticket fits the desktop viewport and keeps reply, note, and approval controls', function () {
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
        ->assertSee('Open policy')
        ->assertSee('Policy sources available')
        ->assertDontSee('Expand editor')
        ->assertSee('Demo only · No email is sent.')
        ->assertDontSee('Simulate send')
        ->assertDontSee('Source review');

    $measure = <<<'JS'
        (() => {
            const root = document.scrollingElement
            const header = document.querySelector('header')?.getBoundingClientRect()
            const footer = document.querySelector('footer')?.getBoundingClientRect()
            const main = document.querySelector('.lucid-workspace-main')
            const side = document.querySelector('.lucid-workspace-side')
            const draft = document.querySelector('#ticket-draft')
            const request = [...document.querySelectorAll('h2')].find((heading) => heading.textContent.trim() === 'Customer request')
            const card = request?.closest('article')
            const spark = document.querySelector('.ticket-reply-section svg')
            const policy = [...document.querySelectorAll('a')].find((link) => link.textContent.includes('Open policy'))
            const sources = document.querySelector('#tools-sources')
            const actionWidths = [...document.querySelectorAll('#ticket-draft-actions button')].map((button) => Math.round(button.getBoundingClientRect().width))
            const manual = document.querySelector('#ticket-manual-row').getBoundingClientRect()
            const actions = document.querySelector('#ticket-draft-actions').getBoundingClientRect()
            const policyLinks = [...document.querySelectorAll('[wire\\:key^="source-card-"] a')]
            const message = document.querySelector('[data-customer-message]')
            const reason = [...document.querySelectorAll('button')].find((button) => button.textContent.includes('View reasoning'))
            const sourceTitle = document.querySelector('[wire\\:key^="source-card-"] button')
            const attribution = document.querySelector('[data-source-attribution]')

            return {
                overflow: root.scrollHeight - window.innerHeight,
                headerTop: header ? header.top : -1,
                footerBottom: footer ? footer.bottom : 9999,
                height: window.innerHeight,
                mainOverflow: main ? main.scrollHeight - main.clientHeight : 99,
                sideOverflow: side ? side.scrollHeight - side.clientHeight : 99,
                draftScroll: draft ? draft.scrollHeight - draft.clientHeight : 99,
                reviewOverflow: document.querySelector('#ticket-review-panel') ? document.querySelector('#ticket-review-panel').scrollHeight - document.querySelector('#ticket-review-panel').clientHeight : 99,
                draftFlex: draft ? getComputedStyle(draft).flexGrow : '',
                draftResize: draft ? getComputedStyle(draft).resize : '',
                dividers: main ? main.querySelectorAll('.border-t').length : 0,
                requestRadius: card ? parseFloat(getComputedStyle(card).borderTopLeftRadius) : 0,
                dividerColor: main?.querySelector('.border-t') ? getComputedStyle(main.querySelector('.border-t')).borderTopColor : '',
                initials: document.querySelector('[data-customer-initials]')?.textContent.trim() ?? '',
                spark: spark ? Math.round(spark.getBoundingClientRect().height) : 0,
                actionWidths,
                policyUnderline: policy ? getComputedStyle(policy).textDecorationLine : '',
                policyArrow: policy ? policy.querySelector('svg') !== null : false,
                sourcesFill: sources ? getComputedStyle(sources).backgroundColor : '',
                manualVisible: manual.bottom <= main.getBoundingClientRect().bottom && manual.top >= actions.bottom,
                divider: getComputedStyle(document.querySelector('#ticket-manual-row')).borderTopWidth,
                policyCount: policyLinks.length,
                policiesAtTop: policyLinks.every(link => Math.abs(link.getBoundingClientRect().top - link.parentElement.getBoundingClientRect().top) < 3),
                requestWeight: request ? getComputedStyle(request).fontWeight : '',
                messageBg: message ? getComputedStyle(message).backgroundColor : '',
                policyColor: policy ? getComputedStyle(policy).color : '',
                reasonColor: reason ? getComputedStyle(reason).color : '',
                actionGap: Math.round(manual.top - actions.bottom),
                editorButtonGap: draft ? Math.round(actions.top - draft.getBoundingClientRect().bottom) : 99,
                cardOverflow: card ? getComputedStyle(card).overflowY : '',
                cardScroll: card ? card.scrollHeight - card.clientHeight : 99,
                messageInside: card && message ? Math.round(card.getBoundingClientRect().bottom - message.getBoundingClientRect().bottom) : 0,
                assessmentAbove: (() => {
                    const assessment = document.querySelector('.ticket-assessment')
                    const heading = assessment?.querySelector('h2')

                    return assessment && heading ? Math.round(heading.getBoundingClientRect().top - assessment.getBoundingClientRect().top) : 99
                })(),
                assessmentBelow: (() => {
                    const assessment = document.querySelector('.ticket-assessment')
                    const pills = assessment?.querySelector('.flex-wrap')

                    return assessment && pills ? Math.round(assessment.getBoundingClientRect().bottom - pills.getBoundingClientRect().bottom) : 99
                })(),
                assessmentDivider: (() => {
                    const pills = document.querySelector('.ticket-assessment .flex-wrap')
                    const reply = document.querySelector('.ticket-reply-section')

                    return pills && reply ? Math.round(reply.getBoundingClientRect().top - pills.getBoundingClientRect().bottom) : 99
                })(),
                assessmentFlex: document.querySelector('.ticket-assessment') ? getComputedStyle(document.querySelector('.ticket-assessment')).flexGrow : '',
                sectionGapA: (() => {
                    const card = document.querySelector('#ticket-review-panel > :first-child > article')?.getBoundingClientRect()
                    const assessment = document.querySelector('.ticket-assessment')?.getBoundingClientRect()

                    return card && assessment ? Math.round(assessment.top - card.bottom) : 99
                })(),
                bottomGap: Math.round((document.querySelector('#ticket-review-panel')?.getBoundingClientRect().bottom ?? 0) - manual.bottom),
                assessmentIcon: document.querySelector('.ticket-assessment svg path')?.getAttribute('d') ?? '',
                sourceTitleWeight: sourceTitle ? getComputedStyle(sourceTitle).fontWeight : '',
                attributionColor: attribution ? getComputedStyle(attribution).color : '',
            }
        })()
    JS;

    $page->screenshot(filename: 'ticket-1440');
    $fit = $page->script($measure);

    expect($fit['overflow'])->toBeLessThanOrEqual(1)
        ->and($fit['headerTop'])->toBeGreaterThanOrEqual(0)
        ->and($fit['footerBottom'])->toBeLessThanOrEqual($fit['height'] + 1)
        ->and($fit['mainOverflow'])->toBeLessThanOrEqual(1)
        ->and($fit['sideOverflow'])->toBeLessThanOrEqual(1)
        ->and($fit['draftScroll'])->toBeLessThanOrEqual(1)
        ->and($fit['reviewOverflow'])->toBeLessThanOrEqual(1)
        ->and($fit['draftFlex'])->toBe('0')
        ->and($fit['draftResize'])->toBe('none')
        ->and($fit['dividers'])->toBe(4)
        ->and($fit['requestRadius'])->toBe(12)
        ->and($fit['dividerColor'])->toBe('rgb(229, 226, 239)')
        ->and($fit['initials'])->toBe('MC')
        ->and($fit['spark'])->toBe(28)
        ->and($fit['actionWidths'])->toHaveCount(4)
        ->and(max($fit['actionWidths']) - min($fit['actionWidths']))->toBe(0)
        ->and($fit['policyUnderline'])->toBe('none')
        ->and($fit['policyArrow'])->toBeTrue()
        ->and($fit['sourcesFill'])->toBe('rgb(238, 231, 255)');
    expect($fit['manualVisible'])->toBeTrue();
    expect($fit['divider'])->toBe('1px');
    expect($fit['policyCount'])->toBe(2);
    expect($fit['policiesAtTop'])->toBeTrue();
    expect($fit['requestWeight'])->toBe('600')
        ->and($fit['messageBg'])->toBe('rgb(245, 245, 248)')
        ->and($fit['policyColor'])->toBe('rgb(109, 91, 208)')
        ->and($fit['reasonColor'])->toBe('rgb(109, 91, 208)')
        ->and($fit['actionGap'])->toBeLessThanOrEqual(16)
        ->and($fit['editorButtonGap'])->toBeLessThanOrEqual(12)
        ->and($fit['cardOverflow'])->toBe('visible')
        ->and($fit['cardScroll'])->toBeLessThanOrEqual(1)
        ->and($fit['messageInside'])->toBeGreaterThanOrEqual(8)
        ->and($fit['messageInside'])->toBeLessThanOrEqual(16)
        ->and($fit['assessmentFlex'])->toBe('0')
        ->and($fit['assessmentAbove'])->toBeGreaterThanOrEqual(10)
        ->and($fit['assessmentBelow'])->toBeGreaterThanOrEqual(10)
        ->and($fit['assessmentDivider'])->toBeLessThanOrEqual(16)
        ->and($fit['sectionGapA'])->toBeLessThanOrEqual(8)
        ->and($fit['bottomGap'])->toBeLessThanOrEqual(8)
        ->and($fit['assessmentIcon'])->toContain('M14 2H5')
        ->and($fit['sourceTitleWeight'])->toBe('600')
        ->and($fit['attributionColor'])->toBe('rgb(100, 97, 125)');

    $page->resize(1280, 870);

    $page->screenshot(filename: 'ticket-1280');
    $compact = $page->script($measure);

    expect($compact['overflow'])->toBeLessThanOrEqual(1)
        ->and($compact['headerTop'])->toBeGreaterThanOrEqual(0)
        ->and($compact['footerBottom'])->toBeLessThanOrEqual($compact['height'] + 1)
        ->and($compact['mainOverflow'])->toBeLessThanOrEqual(1)
        ->and($compact['sideOverflow'])->toBeLessThanOrEqual(1)
        ->and($compact['draftScroll'])->toBeLessThanOrEqual(1)
        ->and($compact['reviewOverflow'])->toBeLessThanOrEqual(1)
        ->and($compact['draftFlex'])->toBe('0')
        ->and($compact['spark'])->toBe(28)
        ->and($compact['actionWidths'])->toHaveCount(4)
        ->and(max($compact['actionWidths']) - min($compact['actionWidths']))->toBe(0);
    expect($compact['manualVisible'])->toBeTrue();
    expect($compact['actionGap'])->toBeLessThanOrEqual(16)
        ->and($compact['editorButtonGap'])->toBeLessThanOrEqual(12)
        ->and($compact['cardOverflow'])->toBe('visible')
        ->and($compact['cardScroll'])->toBeLessThanOrEqual(1)
        ->and($compact['messageInside'])->toBeGreaterThanOrEqual(8)
        ->and($compact['messageInside'])->toBeLessThanOrEqual(16)
        ->and($compact['assessmentFlex'])->toBe('0')
        ->and($compact['assessmentAbove'])->toBeGreaterThanOrEqual(4)
        ->and($compact['assessmentBelow'])->toBeGreaterThanOrEqual(4)
        ->and($compact['assessmentDivider'])->toBeLessThanOrEqual(16)
        ->and($compact['sectionGapA'])->toBeLessThanOrEqual(8)
        ->and($compact['bottomGap'])->toBeLessThanOrEqual(16);

    $page->resize(390, 844);

    $page->screenshot(filename: 'ticket-phone');
    $phone = $page->script(<<<'JS'
        (() => {
            const root = document.scrollingElement
            const controls = [...document.querySelectorAll('#ticket-draft-actions button')]

            return {
                horizontalOverflow: root.scrollWidth - window.innerWidth,
                actionCount: controls.length,
                controlsReachable: controls.every((control) => {
                    const box = control.getBoundingClientRect()

                    return box.width > 0 && box.height > 0
                }),
                reviewOverflow: getComputedStyle(document.querySelector('#ticket-review-panel')).overflowY,
            }
        })()
    JS);

    expect($phone['horizontalOverflow'])->toBeLessThanOrEqual(1)
        ->and($phone['actionCount'])->toBe(4)
        ->and($phone['controlsReachable'])->toBeTrue()
        ->and($phone['reviewOverflow'])->toBe('auto');

    $page->fill('#ticket-draft', 'Phone draft stays private.')
        ->click('#ticket-manual-open')
        ->assertSee('Simulate send');
    expect(waitUntilScriptIsTrue($page, "document.activeElement.closest('dialog[open]') !== null"))->toBeTrue();
    $page->keys('#ticket-reply', 'Tab');
    expect($page->script("document.activeElement.closest('dialog[open]') !== null"))->toBeTrue();
    $page->type('#ticket-reply', 'Keep this unsent reply.')
        ->keys('#ticket-reply', 'Escape')
        ->assertDontSee('Simulate send');
    expect($page->script('document.activeElement.id'))->toBe('ticket-manual-open');
    expect($page->value('#ticket-draft'))->toBe('Phone draft stays private.');
    $page->click('#ticket-manual-open');
    expect($page->value('#ticket-reply'))->toBe('Keep this unsent reply.');
    $page->click('form[wire\\:submit="sendCustom"] button:has-text("Close")')
        ->assertDontSee('Simulate send');

    $page->resize(1440, 900);

    $longDraft = str_repeat("A longer policy explanation remains editable.\n", 30);
    $originalHeight = $page->script('document.querySelector("#ticket-draft").clientHeight');
    $page->fill('#ticket-draft', $longDraft);
    expect($page->script('document.querySelector("#ticket-draft").clientHeight'))->toBe($originalHeight);
    expect($page->script('document.querySelector("#ticket-draft").scrollHeight > document.querySelector("#ticket-draft").clientHeight'))->toBeTrue();
    $page->keys('#ticket-draft', ['Control', 'End']);
    expect($page->script('document.querySelector("#ticket-draft").scrollTop > 0'))->toBeTrue();
    $page->assertNoJavaScriptErrors();

    $edited = "Hi Maya,\n\nEdited before switching tabs.\n\nBest,\nAlex Rivera\nHarbor & Co Support";

    $page->fill('#ticket-draft', $edited)
        ->click('#workspace-conversation')
        ->assertSee('Approved public messages on the customer status page.')
        ->assertSee('I bought a Harbor Trail Pack 18 days ago. It is unused with tags.')
        ->click('#workspace-review');

    expect($page->value('#ticket-draft'))->toBe($edited);

    clickTicketControl($page, 'Box not required');

    $page->assertSee('Unused returns within 30 days, with tags attached.')
        ->click('dialog[open] [aria-label="Close modal"]')
        ->click('#tools-details')
        ->assertSee('Save override')
        ->assertSee('AI classification confidence')
        ->click('#tools-activity')
        ->assertSee('Timeline')
        ->click('#workspace-notes')
        ->type('#ticket-note', 'Keep this note off the customer page.')
        ->keys('#ticket-note', 'Enter')
        ->assertSee('Internal note saved')
        ->assertSee('Keep this note off the customer page.')
        ->click('#workspace-conversation')
        ->assertDontSee('Keep this note off the customer page.')
        ->click('#workspace-review')
        ->assertDontSee('Keep this note off the customer page.')
        ->click('Write a manual reply')
        ->assertSee('Simulate send')
        ->click('form[wire\\:submit="sendCustom"] button:has-text("Close")')
        ->assertDontSee('Simulate send')
        ->click('Write a manual reply')
        ->type('#ticket-reply', 'A person can send this reply.')
        ->keys('#ticket-reply', 'Enter')
        ->assertSee('Human reply sent to the customer status page (simulated).')
        ->assertSee('A person can send this reply.')
        ->click('#workspace-review')
        ->click('Approve and send')
        ->assertSee('Reply sent to the customer status page');
});

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
