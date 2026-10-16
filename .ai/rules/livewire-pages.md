---
paths:
  - resources/views/livewire/pages/welcome.blade.php
  - resources/views/livewire/pages/demo-safety.blade.php
  - resources/views/livewire/pages/demo-environment.blade.php
  - resources/views/livewire/pages/workspace-preview.blade.php
  - resources/views/livewire/pages/ticket-index.blade.php
  - resources/views/livewire/pages/supplier-invoice.blade.php
---

# Livewire Pages

## Landing page stacks four demo cards
The public landing page stacks four full-width cards: Harbor & Co., Your Documents, Document Processing, and AI Tools. Harbor combines quick chat, the workflow page, Browse policies, and the safety page as outlined actions. Your Documents links to `/preview` and keeps the no-account, seven-day cleanup text. Document Processing links to `/invoices`. AI Tools links to `/tools` and is no longer a Planned card. Never use the words Coming soon. Business knowledge is Browse policies only—Agent stays in the chrome. Keep one primary button, on Your Documents. Harbor’s four actions may use `sm:grid-cols-2` inside that card. wire:key belongs on the safety-page test foreach, not on static landing cards.

## Safety page omits the chat cap and deep-links Agent scenarios
Keep the conversation question cap in config, read by ChatService. Do not mention that limit on the visitor Advanced Safety page or the landing safety card. Open Agent scenarios must land on the already-visible catalog (query scenarios=1 and #agent-scenarios) so visitors do not hunt through Agent navigation.

## Demo home stays sales-focused
Demo home leads with the live-portfolio kicker, an intro for customer support, documents, and invoices, and a smaller stack line. Fold source-checking and human approval into the Harbor card. Do not put “What this demonstrates”, “What this shows”, or “What this is not” on home. Limitations and dictation live on the Demo environment page.

## Dock preview chat bottom-right from md
From md up, Preview chat is a closed bottom-right dock (preview-dock). The dock ignores pointer events so it does not cover the page. The chat panel, launcher, and New chat dialog turn them back on; a dialog left without that stays open because the click falls through onto the page. The launcher opens the same panel; Close only hides it and does not start a new chat. Expand stays on that corner and grows the panel inside the viewport. Only [data-preview-transcript] scrolls, so the panel cannot lengthen the page. Enter sends; Shift+Enter inserts a newline; ignore Enter while an IME composition is active.

## Phone Preview uses a closed Harbor launcher
Below md, Preview is a closed fixed bottom dock with the same side margins as Harbor. The launcher says Preview chat when closed and Hide when open, including the phone circle. Close and Hide only clear the open flag. Expand stays hidden on phones. From md, the bottom-right size and expanded panel stay as they are, and reopening keeps the saved expanded size.

## Poll the agent ticket list while it is visible
The agent ticket list polls with wire:poll.5s.visible so a visible queue and a tab that returns from the background pick up a ticket submitted elsewhere. Do not change the customer status poll, which stops once a public approved agent reply is showing.

## Pace the visible Preview and Harbor answers together
Store the latest server HTML in pacedTarget and reveal it into liveHtml about every 32ms, two characters at a time, without cutting a tag. Preview and Harbor use that same interval. Hold sources until that reveal catches the done HTML, then refresh or finish the turn. Stop clears the timer immediately. If done has already arrived, show the final HTML and sources at once. If it has not, abort and do not reveal the unseen target. Do not sleep in PHP.

## Invoice controls reuse the upload button and policy link
The file control is the preview Choose files label button, labeled Choose file, with file-choose so keyboard focus uses the policy-link outline. Download CSV and JSON use class policy-link. Action buttons stay flux:button. Do not toast from this page.

## Invoice confidence is the model's extraction estimate
Confidence is the extraction model's own 0–1 estimate that it copied the printed fields. It is not a math check and it does not change after correction or confirmation. Before confirmation say to review the fields and that the score does not guarantee accuracy. After confirmation, say it is the model's estimate from extraction and does not guarantee accuracy.
