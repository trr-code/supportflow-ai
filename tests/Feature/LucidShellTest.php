<?php

use App\Enums\SuggestedReplyStatus;
use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Livewire\Chat\Widget;
use App\Livewire\Pages\DemoWorkspace;
use App\Livewire\Pages\TicketCreate;
use App\Livewire\Pages\TicketShow;
use App\Models\ChatMessage;
use App\Models\DemoSession;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeChunk;
use App\Models\SuggestedReply;
use App\Models\Ticket;
use App\Models\User;
use App\Services\ChatService;
use App\Services\DemoSessionService;
use App\Support\DemoGuide;
use Livewire\Livewire;

test('Agent Desk saves an edited draft privately without sending it', function () {
    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create(['status' => TicketStatus::AwaitingReview]);
    $reply = SuggestedReply::query()->create(['ticket_id' => $ticket->id, 'body' => 'Original suggested reply.', 'grounded' => true, 'status' => SuggestedReplyStatus::Pending, 'cited_chunk_ids' => []]);
    Livewire::test(TicketShow::class, ['ticket' => $ticket])
        ->assertSee('Save edits')
        ->set('draftBody', 'An edited reply saved for later review.')
        ->call('saveDraft')
        ->assertSee('Draft saved. The customer cannot see it until you approve and send.');
    expect($reply->fresh()->body)->toBe('An edited reply saved for later review.')
        ->and($reply->fresh()->status)->toBe(SuggestedReplyStatus::Pending)
        ->and($ticket->fresh()->status)->toBe(TicketStatus::AwaitingReview)
        ->and($ticket->messages()->count())->toBe(0);
    $this->get(route('tickets.status', $ticket->public_token))->assertDontSee('An edited reply saved for later review.');
});

test('lucid demo routes render the shared workspace and keep the older routes', function () {
    $this->get(route('demo.harbor'))
        ->assertSee('Harbor support')
        ->assertSee('Step-by-step instructions')
        ->assertSee('Customer chat')
        ->assertSee('Create a support ticket')
        ->assertSee('Skip to content')
        ->assertSee('id="main-content"', false)
        ->assertSee('aria-label="Send"', false)
        ->assertSee('Dictate with microphone')
        ->assertSee('How can we help?')
        ->assertDontSee('Your AI workspace')
        ->assertDontSee('<footer', false);

    $this->get(route('demo.documents'))
        ->assertSee('Document chat')
        ->assertSee('Choose files')
        ->assertSee('OpenAI processes your documents and questions to prepare answers.')
        ->assertDontSee('Get more help');

    $invoices = $this->get(route('demo.invoices'))
        ->assertSee('Try a sample or upload your own invoice')
        ->assertSee('download CSV or JSON')
        ->assertDontSee('Get more help');

    expect($invoices->getContent())->toMatch('/<title>\s*Invoice review - Lucid\s*<\/title>/');
    expect(preg_match_all('/<h1\b/i', $invoices->getContent()))->toBe(1);

    $this->get(route('demo.replacement'))->assertRedirect(route('demo.harbor', ['example' => 'replacement']));
    foreach (['workspaces.preview', 'documents.invoices'] as $route) {
        $this->get(route($route))->assertSee('Built for modern business workflows.')->assertSee(route('demo.environment').'#privacy', false)->assertDontSee(route('demo.environment').'#contact', false);
    }
    $this->get(route('demo.tools'))->assertRedirect(route('demo.harbor', ['example' => 'replacement']));
});
test('each demo side panel lists its own sources', function () {
    Livewire::test(DemoWorkspace::class)
        ->set('mode', 'documents')
        ->call('showSideTab', 'sources')
        ->assertSee('Add your documents')
        ->assertSee('Choose files')->assertDontSee('Try a sample document')
        ->assertDontSee('Get more help');

    Livewire::test(DemoWorkspace::class)
        ->set('mode', 'invoices')
        ->call('showSideTab', 'sources')
        ->assertSee('Choose an invoice')
        ->assertDontSee('Cited catalog sources show up here after an answer.');
});

test('demos opens the workspace and stays plain on the homepage', function () {
    $home = $this->get(route('home'))->assertOk();

    expect($home->getContent())
        ->not->toContain(route('home').'#demos')
        ->not->toContain('after:bg-[#d5c5fc]')
        ->toContain(route('demo.harbor'))
        ->toContain('Skip to content')
        ->toContain('id="main-content"');
});

test('the floating harbor chat keeps escalate on the composer', function () {
    Livewire::test(Widget::class)
        ->set('open', true)
        ->assertSee('Escalate to a ticket')
        ->assertDontSee('aria-label="Send"', false);
});

test('the guide copy is navigation and the harbor thread keeps private files out', function () {
    Livewire::test(DemoWorkspace::class)
        ->assertSet('mode', 'harbor')
        ->assertSet('sideTab', 'instructions')
        ->assertSee('Ask a question')
        ->assertSee('Create a support ticket')
        ->assertDontSee('Try a human handoff')
        ->assertSee('data-showroom-workspace', false)
        ->assertSeeText('Pick a question above the message box, then select Send.')
        ->assertSee('Browse store policies')->assertDontSee('Click a numbered reference in an answer')
        ->assertSee('How can we help?')
        ->call('showSideTab', 'sources')
        ->assertSet('sideTab', 'sources')
        ->assertSee('Cited catalog sources show up here after an answer.')
        ->assertSee('Browse policies')
        ->assertDontSee('Get more help')
        ->call('showSideTab', 'not-a-tab')
        ->assertSet('sideTab', 'sources');
});

test('inline harbor answers use numbered citations and the floating widget keeps source lines', function () {
    $article = KnowledgeArticle::query()->create([
        'title' => 'Return window',
        'slug' => 'inline-cite-returns',
        'category' => 'returns',
        'body' => 'Unused returns are accepted within 30 days of delivery with tags attached.',
        'is_published' => true,
        'is_seeded' => true,
    ]);
    $chunk = KnowledgeChunk::query()->create([
        'knowledge_article_id' => $article->id,
        'heading' => 'Window',
        'body' => 'Unused returns are accepted within 30 days of delivery with tags attached.',
    ]);

    $sessionId = Livewire::test(Widget::class)->get('demoSessionId');
    $conversation = app(ChatService::class)->conversationFor(DemoSession::query()->findOrFail($sessionId));
    $message = ChatMessage::query()->create([
        'chat_conversation_id' => $conversation->id,
        'role' => 'assistant',
        'body' => 'Unused returns are accepted within 30 days of delivery. Have a nice day.',
        'cited_chunk_ids' => [$chunk->id],
    ]);

    Livewire::test(Widget::class, ['inline' => true, 'demoSessionId' => $sessionId])
        ->assertSee('aria-label="Citation 1, Return window"', false)
        ->assertSee('class="harbor-cite"', false)
        ->assertSee('relative -top-0.5', false)
        ->assertSee('&quot;messageId&quot;:'.$message->id, false)
        ->assertDontSee('Source: Return window');

    Livewire::test(Widget::class, ['demoSessionId' => $sessionId])
        ->set('open', true)
        ->assertSee('Source: Return window')
        ->assertDontSee('class="harbor-cite"', false);
});

test('harbor question bubbles fill the composer and a citation opens its passage', function () {
    $question = DemoGuide::prompt('return_window')['question'];

    Livewire::test(Widget::class, ['inline' => true])
        ->assertSee('How can we help?')
        ->assertSee('What do I need to return my pack?')
        ->assertDontSee('Use a return question')
        ->call('fillQuestion', 'return_window')
        ->assertSet('question', $question)
        ->assertSet('streaming', false);

    expect(ChatMessage::query()->count())->toBe(0);

    Livewire::test(DemoWorkspace::class)
        ->set('mode', 'documents')
        ->call('inspectCitation', 12, '4', 9)
        ->assertSet('sideTab', 'instructions');
});

test('an older harbor citation opens that answer and keeps other sources expanded', function () {
    $pickup = KnowledgeArticle::query()->create([
        'title' => 'Store pickup',
        'slug' => 'older-cite-pickup',
        'category' => 'shipping',
        'body' => 'Seattle Flagship can hold replacement parts for same-day pickup when stock is on hand.',
        'is_published' => true,
        'is_seeded' => true,
    ]);
    $warranty = KnowledgeArticle::query()->create([
        'title' => 'Warranty',
        'slug' => 'older-cite-warranty',
        'category' => 'general',
        'body' => 'The limited warranty covers defects in materials for two years from delivery.',
        'is_published' => true,
        'is_seeded' => true,
    ]);
    $returns = KnowledgeArticle::query()->create([
        'title' => 'Return window',
        'slug' => 'newer-cite-returns',
        'category' => 'returns',
        'body' => 'Unused returns are accepted within 30 days of delivery with tags attached.',
        'is_published' => true,
        'is_seeded' => true,
    ]);
    $pickupChunk = KnowledgeChunk::query()->create([
        'knowledge_article_id' => $pickup->id,
        'heading' => 'Flagship',
        'body' => 'Seattle Flagship can hold replacement parts for same-day pickup when stock is on hand.',
    ]);
    $warrantyChunk = KnowledgeChunk::query()->create([
        'knowledge_article_id' => $warranty->id,
        'heading' => 'Coverage',
        'body' => 'The limited warranty covers defects in materials for two years from delivery when you keep the receipt.',
    ]);
    $returnChunk = KnowledgeChunk::query()->create([
        'knowledge_article_id' => $returns->id,
        'heading' => 'Window',
        'body' => 'Unused returns are accepted within 30 days of delivery with tags attached.',
    ]);

    $sessionId = Livewire::test(Widget::class)->get('demoSessionId');
    $conversation = app(ChatService::class)->conversationFor(DemoSession::query()->findOrFail($sessionId));
    $older = ChatMessage::query()->create([
        'chat_conversation_id' => $conversation->id,
        'role' => 'assistant',
        'body' => 'Seattle Flagship can hold replacement parts for same-day pickup. The limited warranty covers defects in materials for two years from delivery.',
        'cited_chunk_ids' => [$pickupChunk->id, $warrantyChunk->id],
    ]);
    ChatMessage::query()->create([
        'chat_conversation_id' => $conversation->id,
        'role' => 'assistant',
        'body' => 'Unused returns are accepted within 30 days of delivery.',
        'cited_chunk_ids' => [$returnChunk->id],
    ]);

    $page = Livewire::withCookies([DemoSessionService::COOKIE => $sessionId])
        ->test(DemoWorkspace::class)
        ->call('showSideTab', 'sources')
        ->assertSee('aria-expanded="false"', false)
        ->assertSee('Return window')
        ->assertDontSee('when stock is on hand')
        ->call('inspectCitation', $older->id, (string) $pickup->id, $pickupChunk->id)
        ->assertSet('inspectedMessageId', $older->id)
        ->assertSet('inspectedChunkId', $pickupChunk->id)
        ->assertSee('when stock is on hand')
        ->assertSee('aria-label="Citation 1, Store pickup"', false)
        ->assertSee('aria-expanded="true"', false)
        ->assertDontSee('tags attached')
        ->call('toggleSource', (string) $warranty->id)
        ->assertSee('keep the receipt')
        ->call('toggleSource', (string) $pickup->id)
        ->assertDontSee('when stock is on hand')
        ->assertSee('keep the receipt')
        ->call('inspectCitation', $older->id, (string) $pickup->id, 0)
        ->assertSet('inspectedChunkId', $pickupChunk->id);

    expect($page->get('expandedSourceIds'))->toBe([(string) $warranty->id]);
});

test('harbor instructions open the ticket form and safety examples without leaving the workspace', function () {
    $workspace = Livewire::test(DemoWorkspace::class)
        ->call('showHumanReview')
        ->assertSee('Use a prepared ticket')
        ->assertSee('Write a ticket')
        ->call('openPreparedTicket')
        ->assertSet('center', 'ticket')
        ->assertSet('prepareTicket', true)
        ->assertSee('wire:name="pages.ticket-create" class="space-y-3"', false);

    expect(session('supportflow.ticket.customer_name'))->toBe('Maya Chen')
        ->and(session('supportflow.ticket.subject'))->toBe('Can I still return the Harbor Trail Pack?')
        ->and(session('supportflow.ticket.description'))->toContain('unused with tags');

    $workspace
        ->assertSee('New ticket')
        ->assertSee('Customer chat')
        ->assertDontSee('Submit a support ticket')
        ->assertDontSee('Use a sample return')
        ->assertSee('wire:key="lucid-harbor-chat"', false)
        ->assertSee('Create a support ticket')
        ->call('openOwnTicket')
        ->assertSet('prepareTicket', false)
        ->assertSet('startBlank', true)
        ->assertSee('New ticket')
        ->assertDontSee('Use a sample return')
        ->call('returnToChat')
        ->assertSet('center', 'chat')
        ->call('toggleGuide', 'safety')
        ->call('trySafetyExample', 'prompt_injection')
        ->assertSet('center', 'chat')
        ->assertDispatched('demo-fill-chat', key: 'prompt_injection')
        ->call('trySafetyExample', 'not-a-key')
        ->assertNotDispatched('demo-fill-chat');

    Livewire::test(DemoWorkspace::class)
        ->set('mode', 'documents')
        ->call('openPreparedTicket')
        ->assertSet('center', 'chat');
});

test('an inline harbor chat opens the workspace ticket form', function () {
    $widget = Livewire::test(Widget::class)->set('inline', true);
    $session = DemoSession::query()->findOrFail($widget->get('demoSessionId'));
    $conversation = app(ChatService::class)->conversationFor($session);

    ChatMessage::query()->create([
        'chat_conversation_id' => $conversation->id,
        'role' => 'user',
        'body' => 'Can I return an unused Harbor Trail Pack?',
        'cited_chunk_ids' => [],
    ]);

    $widget->call('escalate')
        ->assertDispatched('harbor-open-ticket')
        ->assertNoRedirect();

    expect(session('supportflow.ticket.subject'))->toBe('Follow-up from Harbor chat');

    $form = Livewire::test(TicketCreate::class);

    expect($form->get('subject'))->toBe('Follow-up from Harbor chat')
        ->and($form->get('description'))->toContain('Can I return an unused Harbor Trail Pack?');

    Livewire::test(DemoWorkspace::class)
        ->call('openEscalatedTicket')
        ->assertSet('center', 'ticket')
        ->assertSee('New ticket')
        ->assertSee('Nothing is emailed.')
        ->assertDontSee('Submit a support ticket')
        ->assertSee('Ask a question');
});

test('a prepared workspace ticket fills the form and a blank one starts empty', function () {
    $prepared = Livewire::test(TicketCreate::class, [
        'embedded' => true,
        'prepareSample' => true,
    ]);

    expect($prepared->get('subject'))->toBe('Can I still return the Harbor Trail Pack?')
        ->and($prepared->get('customer_name'))->toBe('Maya Chen');

    $html = $prepared->html();

    expect(strpos($html, 'wire:name="pages.ticket-create"'))
        ->toBeLessThan(strpos($html, 'id="harbor-ticket-description"'))
        ->toBeLessThan(strpos($html, 'data-noun="description"'));

    $prepared->assertSee('Nothing is emailed.')
        ->assertSee('public status link')
        ->assertSee('sm:grid-cols-2')
        ->assertSee('Dictate with microphone')
        ->assertSee('harbor-ticket-description', false)
        ->assertSee('!pe-20', false)
        ->assertSee('!bg-[#080808]', false)
        ->assertSee('wire:name="pages.ticket-create" class="space-y-3"', false)
        ->assertDontSee('wire:name="pages.ticket-create" class="text-sm', false)
        ->assertDontSee('Submit a support ticket')
        ->assertDontSee('Use a sample return');

    Livewire::test(TicketCreate::class, [
        'embedded' => true,
        'startBlank' => true,
    ])->assertSet('subject', '')
        ->assertSet('customer_name', '');
});

test('a workspace ticket returns to the customer reply in the center', function () {
    fakeSupportAi();

    $component = Livewire::test(TicketCreate::class, [
        'embedded' => true,
        'prepareSample' => true,
    ]);

    $component->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('demo.harbor'));

    $ticket = Ticket::query()->first();

    expect($ticket)->not->toBeNull();

    $this->get(route('demo.harbor'))
        ->assertOk()
        ->assertSee($ticket->reference)
        ->assertDontSee('View ticket as a support agent')
        ->assertSee($ticket->subject)
        ->assertSee('Customer chat')
        ->assertSee('See the reply as the customer');

    Livewire::test(TicketCreate::class)
        ->assertSet('customer_name', '');
});

test('escalating a harbor chat copies the transcript onto the ticket draft', function () {
    $widget = Livewire::test(Widget::class);
    $session = DemoSession::query()->findOrFail($widget->get('demoSessionId'));
    $conversation = app(ChatService::class)->conversationFor($session);

    ChatMessage::query()->create([
        'chat_conversation_id' => $conversation->id,
        'role' => 'user',
        'body' => 'Can I return an unused Harbor Trail Pack?',
        'cited_chunk_ids' => [],
    ]);

    $widget->call('escalate')
        ->assertRedirect(route('demo.harbor', ['view' => 'ticket']));

    expect(session('supportflow.ticket.subject'))->toBe('Follow-up from Harbor chat');

    $this->get(route('demo.harbor', ['view' => 'ticket']))
        ->assertOk()
        ->assertSee('Follow-up from Harbor chat')
        ->assertSee('Can I return an unused Harbor Trail Pack?');
});

test('inline reply modes preserve independent drafts without sending', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();
    $body = "Hi Jamie,\n\nUnused items can be returned within 30 days.\n\nBest,\nAlex Rivera\nHarbor & Co Support";

    SuggestedReply::query()->create([
        'ticket_id' => $ticket->id,
        'body' => $body,
        'grounded' => true,
        'status' => SuggestedReplyStatus::Pending,
        'cited_chunk_ids' => [],
    ]);

    $this->actingAs($user);

    Livewire::test(TicketShow::class, ['ticket' => $ticket])
        ->assertSee('Review reply')
        ->assertSee('Write your own reply')
        ->assertDontSee('Expand editor')
        ->assertSee('Reply history')
        ->assertDontSee('Source review')
        ->assertSet('manualReply', false)
        ->set('manualReply', true)
        ->assertSet('manualReply', true)
        ->assertDontSee('Use suggested reply')
        ->assertSee('Send reply')
        ->set('customReply', 'Keep this unsent reply.')
        ->set('draftBody', 'An edited AI draft stays separate.')
        ->set('manualReply', false)
        ->assertSet('manualReply', false)
        ->assertSet('customReply', 'Keep this unsent reply.')
        ->assertSet('draftBody', 'An edited AI draft stays separate.')
        ->assertSeeNormalizedText('Save edits saves your changes without sending')
        ->set('manualReply', true)
        ->assertSet('customReply', 'Keep this unsent reply.')
        ->set('draftBody', $body)
        ->assertSet('customReply', 'Keep this unsent reply.')
        ->assertDontSee('Reply sent to the customer status page');

    expect($ticket->refresh()->status)->not->toBe(TicketStatus::WaitingOnCustomer);
});

test('policy passage dialogs distinguish passage headings from their parent policy', function (string $heading) {
    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create(['status' => TicketStatus::AwaitingReview]);
    $article = KnowledgeArticle::query()->create(['title' => 'Return window', 'slug' => 'passage-origin-policy', 'body' => 'Unused returns within thirty days.', 'category' => 'returns', 'is_published' => true]);
    $chunk = $article->chunks()->create(['heading' => $heading, 'body' => 'A cited policy passage.', 'token_count' => 5]);
    SuggestedReply::query()->create(['ticket_id' => $ticket->id, 'body' => 'A draft with a policy citation.', 'grounded' => true, 'status' => SuggestedReplyStatus::Pending, 'cited_chunk_ids' => [$chunk->id]]);
    $html = $this->get(route('agent.tickets.show', $ticket))->assertOk()->getContent();
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    $dialogs = $xpath->query('//dialog[.//h2[normalize-space(.)="'.$heading.'"]]');
    expect($dialogs->length)->toBe(1);
    $origin = $xpath->query('.//p[starts-with(normalize-space(.), "From:")]', $dialogs->item(0));
    expect($origin->length)->toBe(1)
        ->and(trim($origin->item(0)->textContent))->toBe('From: Return window');
})->with(['Return window', 'Box not required']);

test('the ticket source badge requires a grounded pending draft with resolvable citations', function (bool $grounded, bool $cited, bool $resolvable, bool $pending, bool $expected) {
    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create(['status' => TicketStatus::AwaitingReview]);
    $article = KnowledgeArticle::query()->create([
        'title' => 'Return policy', 'slug' => 'ticket-badge-policy', 'body' => 'Returns within 30 days.', 'is_published' => true,
        'category' => TicketCategory::Returns,
    ]);
    $chunk = $article->chunks()->create(['body' => 'Returns within 30 days.', 'token_count' => 5]);
    $id = $chunk->id;
    if (! $resolvable) {
        $chunk->delete();
    }
    SuggestedReply::query()->create([
        'ticket_id' => $ticket->id,
        'body' => 'Private policy draft.',
        'grounded' => $grounded,
        'status' => $pending ? SuggestedReplyStatus::Pending : SuggestedReplyStatus::Rejected,
        'cited_chunk_ids' => $cited ? [$id] : [],
    ]);

    $component = Livewire::test(TicketShow::class, ['ticket' => $ticket]);
    if ($expected) {
        $component->assertSee('Policy sources available')->assertSee(route('knowledge.show', $article->slug), false);
    } else {
        $component->assertDontSee('Policy sources available');
    }
    $this->get(route('tickets.status', $ticket->public_token))->assertDontSee('Private policy draft.');
})->with([
    'grounded and cited' => [true, true, true, true, true],
    'grounded without citations' => [true, false, true, true, false],
    'ungrounded with citations' => [false, true, true, true, false],
    'deleted citation' => [true, true, false, true, false],
    'rejected draft' => [true, true, true, false, false],
]);
