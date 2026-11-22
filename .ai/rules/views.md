---
paths:
  - "resources/views/**"
---

# Views

## Follow path-specific Lucid rules
Before changing a Lucid screen, read the path-specific rules in this directory. The current homepage is approved and must not be reopened from older CTA mockups. Later explicit approvals and those rules win over older mockups.

## Remaining support screens share the Lucid sidebar and capped frame
Owner authorized completing invoice review, policy browse/detail, agent dashboard, queue, ticket review and customer status/replies before the journey walkthrough. Reuse lucid.sidebar and lucid.support-frame for support pages; demos reuse the same sidebar. Keep policy browsing public/seeded/workspace-null. Agent content stays authorized and drafts/internal notes never enter public status. The shared 1600px capped frame replaces the old header/footer/sidebar frame; queue row counts are measured from the available panel height, not the historical 8/11-row figures. Preserve existing review, confirmation, export, polling and manual-reply focus behavior.

## Review SEO and heading structure during UI work and before closure
Owner requires typography consistency, semantic heading hierarchy and route-appropriate SEO/indexing checks on the internal ticket page now and every page reviewed from this point forward. Before closing the UI package, complete a route-and-state inventory covering the entire app, including previously approved pages, embedded views and dialogs; verify headings, metadata, indexing/canonical decisions, labels/link semantics and responsive typography with tests. Track coverage and unresolved findings in release-readiness; individual page checks do not establish whole-app completion or guarantee perfect SEO.

## Compare shared styling against the approved app before changing it
Owner explicitly requires reviewing the homepage and all previously reviewed sections/readiness history before selecting a baseline or changing shared typography/layout. Never treat one cited page as proof that another page is wrong. Compare controls by role across customer support, document/invoice workspaces and agent screens, check rendered styles, then apply a coherent shared rule and regressions. Report completed changes plainly; avoid confusing before/after inventories unless requested.

## Shared workspace page headers supersede subject-first ticket layout
Owner wants the same page-header layout across app workspaces: page H1 plus contextual demo pill, then one short description in the same position. Use x-lucid.page-header (initially dashboard,queue,full ticket). Full ticket H1 is Ticket review; Back to tickets is the header's secondary action at right on desktop, wrapping on phones; customer subject is H2 below. Preserve oneH1 and existing page-specific heading IDs. Do not revert to subject-first H1 just because other ticket products do so; this supersedes that earlier choice.

## Use the repeatable Lucid UI walkthrough
For Lucid screen acceptance and consistency reviews, read .ai/skills/lucid-ui-walkthrough/SKILL.md alongside applicable rules and the current release-readiness checkpoint. Resume the recorded next view, compare controls by role against the approved journey, and report corrections, verification limits and one next action plainly. The owner's name is Terry.

## Tabbed workspace header order
In tabbed workspaces, place the page title/context and short description first, then the page-view tabs and their divider, then ticket-specific subject/customer/status and selected-view content. Internal Ticket review uses this order for Review reply/Reply history/Internal notes, matching the embedded support views. Preserve the distinct lower-level Sources/Details/Activity tabs and existing heading hierarchy; do not add tabs or extra dividers to pages without them.

## Heading roles include public screens and real dialog headings
Maintain 24px semibold application page titles (lucid-main-heading for reusable Flux headers; public/workspace layouts supply the equivalent), 20px semibold primary content/dialog titles and 16px semibold ordinary sections. Preserve separate homepage/empty-state/document-prose/guide roles. Flux heading defaults to div: explicitly set semantic level for real page, section and dialog headings; leave decorative/user-name labels as non-headings. Review active routes and conditional states, not only the sampled sizing screens; keep regression coverage in HeadingConsistencyTest and ControlSizingTest.

## Keep action feedback beside the action without moving its controls
Save/reset/submit feedback belongs in the relevant form or action section, not a conditional banner above an unrelated long workspace. Reuse lucid.action-feedback's reserved live region so first completion does not displace controls; keep errors actionable and local. Ticket Details/notes/reply feedback must remain independent. Existing visible result states and established toasts need not be duplicated; custom toast design remains a separate closure task. Regress first and repeated saves, message visibility, desktop/phone position and real persistence.

## Verify references before declaring UI cleanup complete
After replacing UI, trace its markup, styles, handlers, component props and state across all consumers; remove leftovers made unused by the change and temporary diagnostics. Preserve shared code still used elsewhere and meaningful tests. Report the cleanup scope and any deferred leftovers explicitly; infer-conventions documents patterns and does not perform this cleanup.

## Document controls stay visible and guides follow current state
Supersedes the earlier Document chat naming rule's expandable file-management direction: loaded filenames/statuses and upload actions stay visible. Only optional Answer settings and Privacy & access are disclosures; preserve their open state through updates. Guides follow upload/processing/failure/ready/answered state. Reuse local feedback and shared confirmation styles; hiding a return code must not discard it or imply it was saved.

## UI work starts from the current design and rendered evidence
Before editing UI, follow .ai/guidelines/ui-review.md and applicable indexed rules. Current explicit owner decisions take priority over historical screenshots, checkpoints and existing code. Use the shared component for the same role; compare the rendered result in persistent, streaming and conditional states before handing it back. An unresolved owner objection is not approval of the current implementation.

## Document chat name is consistent throughout the showroom
Use Document chat in the shared navigation, toolbar, page title and entry links. Keep the name on one sidebar line using the established navigation styling; do not shrink type or widen navigation to accommodate an old label.

## Policy reading width must not limit dividers or change navigation context
Full policy article header/content separators span the inset work area; keep any 72ch text reading limit inside the divider wrapper. Regress rendered divider width against the page header. Policy catalog/article pages opened from an authorized ticket preserve Agent desk left navigation via server-derived layout context; their right guide describes the policy phase, then Back to ticket restores the ticket guide. Check desktop and collapsed mobile navigation.

## Operational ticket tools belong in the middle; guides follow their Livewire page
Owner authorized consistent main showroom pass. Use lucid.workspace-page for page-owned Livewire instructions inside shared support-frame pageGuide mode. Its root/main use display:contents so body and guide occupy shared grid; keep same220/320 widths/insets/header/footer/step spacing. Ticket sources/details/activity are operational content below reply in middle, never replace far-right instructions. Ticket guide updates with review/history/notes and pending/processing/sent state. Policies use flat inset rules, not nested cards. Standalone customer status guide follows actual approved public replies. Preserve privacy,polling,controls,modals and oneH1; scrolling allowed.

## Cited policies open directly and the library stays separate
Terry supersedes the inline all-policy browser: Harbor numbered citations still open Sources & actions, and each expanded source offers Open policy for that exact public article, matching internal ticket sources. Browse policies in the left sidebar opens the full catalog. Full articles retain Back to all policies and contextual Back to customer chat/ticket. Carry message/citation IDs through library navigation and restore only citations validated against the current visitor conversation. Remove replaced inline-browser handlers, markup, queries, styles and obsolete rules; retain meaningful privacy/Markdown coverage.

## Visibility polling needs a rendered box
Do not attach wire:poll.visible to lucid.workspace-page's display:contents root: it has no viewport rectangle, so polling pauses. Put the directive on a real visible inner element. Browser regression must let initial queue sizing settle before changing server state; the sizing request otherwise masks broken polling.

## Rendered UI evidence is required for visual handoff
Before handing back changed UI, report the actual routes, roles, states and viewports inspected according to .ai/guidelines/ui-review.md. Check both streamed and persisted messages and all touched disclosures/dialogs. Do not use code-class matches, tests or an existing numeric radius as a substitute for role-specific visual comparison; owner-reported design objections remain open until resolved.

## Document chat source dialog and controls
Reading/queued status uses the shared blue information pill, ready uses green, failure red. Match the shared single send/Stop icon behavior and primary action styles. The empty state invites a question in business-facing language without explaining the send-arrow mechanics; New chat confirms conversation deletion while keeping files/settings. Standalone preview retains its supported inspector. Bubble geometry follows the owner's separate current clarification, not an inferred radius.

## Showroom chat bubbles share role-specific square corners
Terry chose to keep message bubbles with one square corner. Document chat and inline Harbor use the shared showroom-message-bubble role classes for persisted, pending and streamed output: user messages keep the lower-right corner square; assistant messages keep the lower-left square; other corners retain the existing rounding. Preserve distinct readable role palettes, source contrast and shared padding/width; consult the current owner direction in release-readiness before changing colors. Do not give every corner the same radius or infer message shapes from rounded outer panels. Floating legacy chat remains separate.

## Document chat is upload-only and shares customer citation previews
Terry replaced the bundled sample shortcut with upload-only Document chat. Entry links, empty states and instructions must describe choosing the visitor's own files; do not reintroduce a sample-loading action or document-specific canned question. Reuse Harbor's numbered circle styling, shared citation hover/focus preview behavior and preview markup for saved and streamed document references; Harbor keeps its public policy panel and navigation. Uploaded filenames and excerpts supply hover content without public links. Optional document disclosures put the chevron before the label like existing expandable source rows.

## Guide copy assumes an experienced business audience
Use concise, task-focused business language. Do not explain ordinary send-arrow mechanics or tell visitors how to type into a chat box. Explain product-specific distinctions such as approving a reply, private documents, sources, readiness or destructive actions; preserve accessible control labels. Keep empty states and right-hand guides consistent.

## Document chat sources use the desktop guide position
Selecting a numbered Document chat citation shows the complete cited workspace passage in the right sidebar when the layout has a separate guide column. On stacked layouts, use the source dialog. Close or Escape restores citation focus and the prior instructions visibility. Hover and focus previews stay compact. Never invent public links or page numbers for private uploads.

## Document chat cites supporting claims inline
Use Harbor's inline placement for embedded Document Chat saved answers and completed streams. Put each source after the claim or adjacent related text it supports. Reuse a single citation for adjacent claims from the same passage when possible, and keep distinct sources together when both support a calculation. The private workspace passage still opens in the desktop sidebar or stacked-layout dialog.

## Emphasize actionable labels selectively in sidebar guides
Bold exact control labels, status badges, or section names when emphasis helps the reader find the next action. Keep explanatory wording plain. Steps without a useful landmark can have no bold text.

## Instructions follow the active layout
Show only the directions applicable at the actual control or panel breakpoint. Do not tell visitors 'on desktop', 'on phone' or 'on smaller screens', or show both variants together. Use responsive visibility matching the feature (Document Chat source sidebar 1100px, invoice floating preview 1280px), including stacked tablet layouts. Verify current visible wording when resizing.

## File failures stay concise beside uploads
Terry rejected paragraph-length red explanations beneath Document Chat files. Keep the semantic failure status and one short local reason/correction, without a second generic failure paragraph. Preserve detailed reading reasons for chat/diagnostics; inability to summarize readable text is a separate answer defect. WCAG asks for text identifying errors and known corrections, not long red copy.

## Application-wide wording and control consistency
Use Harbor Outfitters for the fictional retailer and omit empty Product rows/placeholder-name greetings. Use plain current-step sidebar guidance, e.g. Select Open policy to read the full article; return review directions follow actual detail/Send support request controls. Navigation uses recognizable underlined text links; copy actions share the same Flux button role and feedback/fallback behavior. Preserve deliberate card/navigation styling instead of underlining whole cards.

## Worldwide visitors see their own local timestamps
Confirmed owner decision 2026-11-21: Lucid will be viewed worldwide through Upwork. Keep UTC storage; display ticket/chat/event history in the viewer's local or explicitly selected time zone, with clear zone labels and correct daylight-saving/date-boundary handling. Do not force Central Time on visitors. Location-bound business deadlines retain their explicitly stated business zone. Verify the app-wide policy, public/private browsing, international zones and fallbacks; the agent owns the sweep.

## Blade formatting uses the configured Prettier pipeline
Format Blade with npm run format:blade and check with npm run format:blade:check. The project config supplies the Blade/PHP plugins and Tailwind 4 stylesheet; Pint remains responsible for ordinary PHP. Do not enable Pint/laravel_blade alongside it or use another Blade formatter. Preserve whitespace-sensitive content and verify a stable formatting pass.

## Pills share black outlines across workspace and guides
Owner decision 2026-11-22: status and confirmation pills use a thin #111 inset ring, including instruction-panel examples. Preserve pastel fills, semantic text/markers, pill sizes and feedback timing. Grounded/source badges use the same outline. The black left workspace sidebar uses a thin white outline; preserve its dimensions, collapse behavior and navigation.
