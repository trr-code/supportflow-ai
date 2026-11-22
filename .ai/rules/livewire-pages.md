---
paths:
  - 'resources/views/livewire/pages/**'
  - resources/views/livewire/pages/workspace-documents-embedded.blade.php
---

# Livewire Pages

## Safety page omits the chat cap and deep-links Agent scenarios
Keep the conversation question cap in config, read by ChatService. Do not mention that limit on the visitor Advanced Safety page or the landing safety card. Open Agent scenarios must land on the already-visible catalog (query scenarios=1 and #agent-scenarios) so visitors do not hunt through Agent navigation.

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

## Scenario chooser previews before it creates a ticket
Try a scenario replaces the queue table with the nine-name chooser. Selecting a name only previews situation, ai, and expect. Load scenario calls the existing launch flow. Back to tickets closes the chooser and keeps the current filter and page. Do not add labels that are not in the catalog.

## Scenario chooser matches the Lucid panel
The scenario chooser name column is 30% of the inner panel (3fr/7fr). Rows stay compact with inset zinc dividers. Preview sections stay grouped. The AI section uses the Flux sparkles outline at Lucid's 1.65 stroke. Do not add a category badge.

## Public marketing serves multiple acquisition channels
Lucid is for Upwork, Contra, direct outreach and the owner's independent business. Use platform-neutral public tour/contact wording; do not direct every visitor back to Upwork. Proposal-specific demo links remain useful. Preserve truthful current capability and future integration boundaries.

## Workspace restart dialog must remain outside hidden chat
Toolbar Restart demo uses restart-harbor-demo modal owned by DemoWorkspace at stable visible root,not confirm-new-conversation inside hidden chat child. Native modal under hidden ancestor can block clicks invisibly on receipt/review. restartDemo clears current chat+replacement session and supportflow.harbor state then redirects to fresh Harbor; submitted tickets remain. Cover receipt after poll,view switching,cancel modal,restart andpostrestart controls.

## Customer progress distinguishes human review and pastel status
Keep short Human review label to distinguish person from AI. Completed progress has checkmark/subdued styling,current human stage neutral with a thin black inset outline and black number circle/aria-current step; completed/upcoming progress boxes remain unoutlined,upcoming neutral. Semantic status badges shared:pastelblue submitted/AIprocessing,amber awaitinghuman,green resolved,red failure,neutral waitingcustomer. Receipt workspace heading Ticket status;agent-view button aligns to900px receipt column. User requests design-standard checks when considering suggestions.

## Light compact dashboard summary replaces dark banner
Owner removed legacy large black dashboard summary. Use24px heading,16px subtitle and four compact neutral metric cards with dark counts and black icons. Keep live metrics,attention links and shared black sidebar/white workspace. Supersedes charcoal banner rules.

## Queue uses light heading and shared status badges
Owner removed legacy black queue banner: use compact24px heading and16px subtitle matching dashboard. Both mobile ticket cards and desktop rows reuse x-status-badge, never local statusTone maps. Blue submitted/AIreviewing,amber awaiting,peach escalated,red AIunavailable,green resolved,gray waitingcustomer. No fake production records for palette previews.

## Review walkthrough panel contains instructions only
Owner reserves right panel for client walkthrough,not ticket metadata. Review title Review and send a reply with concise introduction andcurrent-page actions understandable without homepage tour. Ticket number/customer/status/request belong center;no duplicate details DL in guide.

## Plain-language Harbor walkthrough headings
Owner approved right-panel action headings plus short current-page intro: Try customer support,Create a support ticket,Track your ticket and reply,Review and send a reply. Guidance must work for visitors skipping homepage andavoid repeating sales intro. Keep16px steps/14px notice anddesktop fit. Review draft pill gray withdot;sent reply history persistent green Sent pill withdot,not auto-dismissed;tickets waitingcustomer staygray.

## Instruction controls and completed-state guidance
Rightpanel compact X has hover/focus tooltip andaccessible name Hide instructions;toolbar restore says Show instructions. Avoid back arrow suggesting navigation. Completed customer guide replaces Watch for reply/statussequence with Read the reply;completed review heading/introduction acknowledge sentreply. Check actual approved public agent message,notwaitingcustomer status alone. Embedded guided review hides Write your own reply after sent;standalone full Agentdesk followups unchanged.

## Keep ticket review essentials visible
Supersedes the earlier instruction to collapse assessment and the whole tools section. Keep short AI summary,category,priority,sentiment,draft/actions and Sources/Details/Activity tabs visible. Detailed reasoning may use its existing dialog; never nest those tabs in a disclosure. Category/sentiment stay neutral; priority has labeled low/medium/high/urgent urgency styling. Preserve shared insets/icons and natural scrolling.

## Ticket section headings use plain text
Owner prefers plain section headings. Remove decorative document/sparkle icons beside internal ticket AI assessment, Suggested reply and Policy passages, matching embedded Harbor's plain headings. Preserve functional/navigation/action icons, section text, hierarchy and spacing. This supersedes earlier decorative purple section-icon guidance.

## Assessment summary emphasizes labels rather than values
Use semibold labels above regular-weight values in the ticket assessment summary. Keep priority's urgency color treatment with normal value weight, and align View reasoning with the values rather than the label row. Treat this as the read-only summary role, not a blanket rewrite of form labels or status badges.

## Reply modes switch immediately with matching guidance
Use deferred client-side manualReply updates, wire:show and inert to switch inline editors immediately. Focus the newly visible editor after nextTick with preventScroll; keep independent drafts. Update guide title, steps and policy-source attribution from the same local state. No modal, artificial transition or oversized reserved editor slot.

## Preserve reply viewport without scroll feedback
Capture the visible-bottom minimum on a reply-mode click, rounded upward with a small safety inset. Release excess space after user wheel/pointer/key input or resize, never in a scroll-event handler. Regress partly clipped and centered controls, repeated switches and scaled layouts; automatic scroll corrections must not resize the page.

## Ticket source badge and editor basics
Keep the fixed suggested draft with internal scrolling, no Expand editor and shared action sizing. Show the positive policy-source badge only for a grounded pending draft with resolved citations; keep Open policy links on source passages. Preserve independent unsent drafts and the shared responsive workspace.

## Policy passage dialogs name the parent policy explicitly
Use the passage heading as the dialog title and From: [policy title] as its secondary attribution. Keep that label when both titles match; passages may be different sections of one policy. Do not rename the parent policy or hide its attribution merely to avoid repeated words.

## Business-facing ticket details and activity omit diagnostics
Remove Technical details and AI runs from ticket review; retain diagnostic records and operational AI-state checks, but no unused all-runs or event-aiRun eager-load queries. Timeline labels use TicketEventType::label while stored event values remain unchanged. Details keeps short local context and the immediate-status/no-send warning; its sidebar explains field meanings, Save details versus immediate status buttons. Activity guidance explains actor/time, newest-first history, earlier events and read-only behavior. Avoid duplicating confidence/policy-match prose in Details; View reasoning retains it.

## Load older ticket activity inline
Show earlier activity appends up to eight older events in the existing timeline; never reopen the complete history in a duplicate modal. Keep stable event keys, an Earlier activity boundary, accessible completion status and focus on the first added entry without scrolling. Loading history is read-only. Keep the contextual Activity guide and repeatable walkthrough aligned.

## Let readers return to recent ticket activity
After older activity is expanded, offer Show recent activity only to reset to the newest eight without changing history. Preserve the chosen expansion while switching Sources/Details/Activity or reply workspace tabs; do not reset implicitly. Update Activity guide step 2 and regress collapse, re-expansion and keyboard focus on desktop/phone.

## Cleanup is clear inactive demo data, not a full reset
Use business-facing Clear inactive demo data / Clear inactive data labels and explain that inactivity is measured per demo visit, not ticket creation age. Modal must state what is removed, what stays and that deletion cannot be recovered. Preserve existing stale-session pruning/protected active runs/prepared example behavior; do not silently turn cleanup into forceReset. The destructive confirmation uses showroom-destructive-action to retain solid red/white styling against general workspace button overrides. Keep the guide and count/limit feedback aligned.

## Cleanup results use the matching persistent success pill
Terry prefers cleanup completion counts in the same wrapping green success pill as saves, including a zero-result cleanup. Remove the plain-text appearance override; keep dismiss-after=0 because the detailed counts must stay visible. Retain amber non-expiring rate-limit warnings. Regress desktop/phone visibility and wrapping.

## Cleanup completion is manually dismissible
Keep detailed green cleanup results until the user selects the small X labeled Dismiss cleanup result. Dismiss locally without a request or layout collapse; return keyboard focus to Clear inactive demo data. Increment noticeRevision on each cleanup attempt so identical later results reappear after dismissal. Warnings remain persistent. Use the shared optional action-feedback dismissal control, not a separate timer or toast.

## Reply history instructions follow actual message availability
Use business-facing language: customer messages and replies the support team has sent. Explain no reply has been sent only when there is no public approved agent reply; handle empty history separately. Show the latest six messages and load older messages inline in batches of six, with Show recent messages only to collapse. Step 2 always explains the more-than-six threshold; never point to an unavailable control without explaining why it is absent. End with Review reply as the next action. Test empty, unsent, sent and overflow states.

## Reply history expands in the ticket
Show the latest six approved public messages, ordered chronologically, and add older messages inline in batches of six. Provide Show recent messages only after expansion, retain expansion while switching ticket tabs, and explain the six-message threshold in right-hand step 2. Do not restore the duplicate full-history modal. Preserve private/unapproved message exclusion.

## Long reply histories keep controls reachable
Six messages versus eight compact Activity events is an intentional content-density choice, not an industry-mandated count. Prepend older conversation messages chronologically and bring the first newly revealed message into view. Offer collapse above and below an expanded conversation; right-hand step 2 must describe these locations and repeated loading. Test at least thirty messages on desktop and phone.

## Internal notes expand inline like Activity
Show four newest private notes first and append older notes in batches of four via Show earlier notes below the list. Provide Show recent notes only, retain limit across tabs, use stable timestamp/id ordering and bounded lookahead, and keep right-hand instructions synchronized. Remove duplicate full-notes modal; private notes must stay excluded from customer reply history.

## Homepage and legacy-route boundaries
The visible brand is Lucid; internal SupportFlow and Harbor names retain their technical/business roles. Homepage has three clickable demos: Customer support, Document chat and Invoice review, with replacements explained inside support. Keep platform-neutral proposal language and aligned direct demo links. Keep /preview and /invoices available while they have supported standalone workflows. The former /tools and /demo/replacement addresses redirect to Harbor, and /tickets/create redirects to the Harbor ticket form.

## Queue sizing follows available space
Keep the queue in the current shared frame with one flat white middle surface and shared status/filter styling. Measure available panel height for rows and pagination; historical 8/11 counts, 208px navigation, 10px gutters and forced no-scroll dimensions are not requirements. Preserve scenarios, filters, pagination, polling and authorized inactive-demo cleanup.

## Document showroom uses real files and visible readiness
Document chat embeds WorkspacePreview in the shared frame while standalone preview remains supported. Own-file uploads use the normal storage/indexing pipeline, never canned answers. Keep filenames/statuses and upload actions visible; only optional Answer settings and Privacy & access are disclosures. Preserve the stable Livewire root, draft state, return-code feedback, confirmations and guide transitions.

## Customer and agent walkthrough navigation
Use the current two-window journey: open Agent desk from the sidebar, submit the customer ticket, keep its status available, locate the same reference as an agent, review/send, then check the customer reply. Review and send replies through Agent desk. The Harbor submission guide previews the status pills before submission. The customer receipt directs visitors to the existing Tickets tab or window and switches to reply-reading instructions after an approved public response.

## Ticket status copy controls follow the current receipt
Keep ticket reference, subject, status/progress and public conversation in the receipt, with current Keep your ticket handy/copy-link controls and bearer-link privacy explanation. Use the shared clipboard success/blocked fallback. Do not restore the old Save this ticket link disclosure solely from historical notes. Guides follow actual approved public replies, not status labels alone.

## Agent desk entry and queue roles
Keep the current sidebar Agent desk entry: authenticated visitors go to the dashboard and guests use the existing authorized demo-agent entry action; the customer walkthrough then directs them to Tickets. Preserve Needs attention links and their intended queue filters. Queue tabs use the shared tab role sizing, not the old text-sm/sidebar-weight comparison; retain the existing table tones, ticket-number links and semantic status pills. Do not restore removed header/Harbor entry buttons or black dashboard summaries.

## Document upload controls distinguish selection from adding files
Keep Choose files and Upload files visible in the active document workspace; no second show/hide upload action. Explain that Choose files opens the computer's file picker, Open returns selected filenames, and Upload files adds them for answers. Keep the upload submit button keyed by empty/selected state: Livewire loading/form handlers restore prior disabled attributes, so reusing the same initially-disabled button can strand selected files or re-enable an empty submit. Preserve busy/error recovery and disable the question composer until a document is Ready for answers.

## Document upload uses one file-status pill and stage-specific guidance
Terry requires one status pill per uploaded file: blue while queued/reading, green when Ready for answers, red on failure. Do not add a separate green Files uploaded success pill or redundant reading summary; retain actionable errors and used deletion feedback. Before readiness, the document guide contains only the current add/read/retry step. Ask/settings/privacy guidance starts at ready; source/new-chat steps appear only after an answer has references, not after an empty or uncited fallback. Explain the PDF text limitation in plain language near file controls: words must be highlightable/copyable; this parser does not recognize words in scanned page images. Do not add OCR support or claim it exists as a copy change.

## Document list stays directly under its heading
Keep uploaded filenames/statuses directly under Your documents, before the separate Add documents form. Label the count as file/files so it is not confused with numbered guide steps. Ready-state guidance should point to the visible filename and Ready for answers pill, not describe a list separated from its heading by upload controls.

## Document chat opens directly on file upload
Document chat has no introductory landing screen or Back to document choices action. Show the file list and Choose files/Upload files immediately, without creating a workspace on GET; the first upload creates it through existing access/storage services. Saved-code reopening remains optional on the empty upload view; preserve standalone preview start/leave behavior and private workspace boundaries.

## Custom document instructions share the dictation editor
Document Answer settings instruction fields reuse lucid-reply-editor and the shared dictation button with noun instruction, full width and compact growing textarea. Dictation edits the draft only; Save settings applies it. Key each instruction row by instruction identity plus current array index so removing an earlier instruction remounts its nested binding. Keep visible labels and instruction validation.

## Dext and Rossum are Invoice review workflow references
Use [Dext](https://help.dext.com/en/articles/416716-how-to-edit-publish-and-export-items-in-dext) as the reference for reviewing extracted fields, editing and export. Use [Rossum](https://knowledge-base.rossum.ai/docs/getting-started-with-rossum) for surfacing discrepancies and human confirmation before export. Adapt those patterns to Lucid’s shared controls and stage-specific sidebar. Keep the fictional sample and own-file options. Treat additional file formats and accounting integrations as separate scope.

## Invoice review returns to invoice choices
Offer Back to invoice choices above review and confirmed fields. Return to the sample and own-PDF upload choices for that browser session and refresh the guide. Leaving only clears the selected review and unsaved edits. Preserve stored invoices, exports, browser ownership and normal expiry.

## Homepage demo cards open the workspaces directly
Owner superseded the homepage Start here tour. Keep the hero focused on the business message and send visitors directly into the three existing demos via the gallery cards. Explain above the cards where each demo's step-by-step guide appears on desktop and phone. Do not add an intervening walkthrough page.
