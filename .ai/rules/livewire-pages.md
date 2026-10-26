---
paths:
  - resources/views/livewire/pages/welcome.blade.php
  - resources/views/livewire/pages/demo-safety.blade.php
  - resources/views/livewire/pages/demo-environment.blade.php
  - resources/views/livewire/pages/workspace-preview.blade.php
  - resources/views/livewire/pages/ticket-index.blade.php
  - resources/views/livewire/pages/supplier-invoice.blade.php
  - resources/views/livewire/pages/dashboard.blade.php
  - resources/views/livewire/pages/demo-workspace.blade.php
  - resources/views/livewire/pages/ticket-show.blade.php
---

# Livewire Pages

## Landing page is the Lucid homepage
The visible product name is Lucid. Internal names stay Harbor & Co and SupportFlow. The homepage is an outcome page: charcoal hero, four demo links (Harbor support, Your documents, Invoice review, Replacements), Ask → Check → Review → Act, and a trust row. Direct demo routes are `/demo/harbor`, `/demo/documents`, `/demo/invoices`, and `/demo/replacement`. Old routes `/preview`, `/invoices`, and `/tools` stay. Never use the words Coming soon. Do not put “What this demonstrates”, “What this shows”, or “What this is not” on home. The guide opens those demos and must not present preset replies as a live model. wire:key belongs on the safety-page test foreach and on the workspace mode links, not on static landing cards.

## Safety page omits the chat cap and deep-links Agent scenarios
Keep the conversation question cap in config, read by ChatService. Do not mention that limit on the visitor Advanced Safety page or the landing safety card. Open Agent scenarios must land on the already-visible catalog (query scenarios=1 and #agent-scenarios) so visitors do not hunt through Agent navigation.

## Demo home stays sales-focused
Demo home leads with Clear answers. Confident actions. Fold source-checking and human approval into the customer-support card. Limitations and dictation live on the Demo environment page.

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

## Agent desk opens the dashboard
The header Agent desk link opens this dashboard. The Harbor walkthrough Open Agent desk button stays on the ticket queue. Needs attention opens the queue with filter awaiting, escalated, or failed. Do not put a View tickets button in the black summary panel, and do not hardcode the mockup counts.

## Agent dashboard follows the workspace shell
The charcoal summary is full width under the header. The 208px sidebar and the cards sit below it in lucid-workspace-columns-dashboard. From 1280px, the header, summary, and footer stay put and only lucid-workspace-main scrolls, the same way the workspace columns do. Summary icons are pale-lavender outlines at size-5 and stroke 1.65, with no circular backgrounds. Reuse the workspace nav highlight, button classes, and type. Do not size this page from the mockup.

## Harbor walkthrough opens the ticket queue
The Harbor walkthrough button says Open ticket queue and still links to the ticket queue. The header link stays Agent desk. The walkthrough step that says Open Agent desk refers to that header link.

## Dashboard spacing follows Harbor
Lucid sidebar rows stay text-sm and use py-3. Do not add a taller desktop padding. The dashboard summary uses the Harbor banner padding, px-6 py-8 sm:px-10 sm:py-10, with the homepage mt-7 before the statistics. On desktop the attention and chart cards share leftover column height so the activity card lines up with the sidebar, and the footer gap stays the layout gap. Scroll the column when it overflows; below 1280px the page scrolls.

## Dashboard fits a short desktop without scrolling
The agent dashboard summary is tighter than the Harbor banner so a 1280×870 desktop shows the header, footer, and activity card with no page scroll and no column scroll. Keep the Harbor title sizes. Tighten summary padding and the subtitle-to-stats gap, then Needs attention and the charts, before shrinking type or hiding overflow. Below 1280px, and on a shorter desktop, scrolling stays available.

## Dashboard rows fill their cards evenly
Needs attention and the two chart cards keep their outer size. On desktop, spread their rows evenly through the card so leftover height is not left under the last row, with matching space above the heading and below the last row. Do not grow the minimum row spacing enough to bring back scrolling at 1280×870. Below 1280px the rows stack at their content height and the page scrolls.

## Ticket queue uses the Lucid shell
The queue uses components.layouts.lucid, the compact charcoal banner, and the 208px dashboard sidebar with Tickets active. Show 8 tickets per page so a 1280×870 desktop fits the header, footer, filters, table, and numbered pagination without page or column scroll. Status pills stay pastel with dark labels: submitted blue, awaiting amber, escalated peach, waiting gray, AI unavailable red. Lavender is for selection. Try a scenario still opens the existing catalog. Reset demo data still opens the confirm modal. Below 1280px the page scrolls.

## Queue panel aligns with the sidebar
The queue’s main column is the white rounded card around the filters, toolbar, table, and pagination. It stretches with the sidebar, and the gap above the footer stays the layout’s 10px. Keep the reset modal out of that flex column; a modal sibling adds another 16px gap and the panel stops short of the sidebar.

## Queue page size follows the panel height
Queue filters reuse Harbor’s rounded-[9px] tabs: selected is #eee7ff with ink text, unselected is #64617d and hovers #f4f0fc, focus outline is #8764cb. Reset demo data has no underline and uses that hover; it still opens the confirm modal. The visible status is Escalated, and the failed filter is labeled AI unavailable. Page size starts at 11 and the panel measures how many 38px rows fit, so 1280×870 shows 8 and a 1526×978 window shows 11, with pagination on screen, the 10px footer gap, and no column scroll or clipped rows.

## Queue type and table tones use existing tokens
Queue filters are text-sm font-semibold, the same size and weight as the selected sidebar row. The current pagination page uses the pale selection fill #eee7ff. The table outline and row dividers use border-zinc-100, and the header background is bg-zinc-50, with dark semibold headings. Ticket numbers stay harbor pine. Status pills stay rounded and pastel.

## Scenario chooser previews before it creates a ticket
Try a scenario replaces the queue table with the nine-name chooser. Selecting a name only previews situation, ai, and expect. Load scenario calls the existing launch flow. Back to tickets closes the chooser and keeps the current filter and page. Do not add labels that are not in the catalog.

## Scenario chooser matches the Lucid panel
The scenario chooser name column is 30% of the inner panel (3fr/7fr). Rows stay compact with inset zinc dividers. Preview sections stay grouped. The AI section uses the Flux sparkles outline at Lucid's 1.65 stroke. Do not add a category badge.

## Opened ticket follows the confirmed Lucid decisions
No Expand editor, fixed draft with internal scrolling, source-card top-right policy links, equal compact actions, visible divider and manual row. Positive source badge requires grounded pending reply and resolved citations. Preserve manual open/Close and unsaved text; visually verify 1440×900, 1280×870 and 390×844 using the shared responsive shell.
