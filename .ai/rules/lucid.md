---
paths:
  - "resources/views/components/lucid/**"
---

# Lucid

## Source inspection matches the monochrome guide
Persistent source panel uses showroom-source-group/passages/passage classes,30px black numbered circles with white numerals,black dividers and a neutral inspected passage. Keep existing article expansion,stable keys,citation numbering and scroll behavior. Header belongs to outer guide; do not repeat Sources & actions inside. Hover previews follow the same neutral palette.

## Walkthrough explains visible page sections in plain language
Owner requires right-hand guidance to explain what visitors see, what each meaningful section means and what to do on that current page. Use visible labels and plain language; do not compress definitions into jargon or jump ahead into another screen. Let coverage determine step count. Review each page with owner; do not assume existing three headings prove complete coverage.

## Preserve explanatory introduction and full sentences in guides
Instruction panels follow Customer support format: current-page title,short Follow these steps introduction,numbered actions/explanations using visible labels. Do not remove intro orreplace clear sentences with label:fragment shorthand toavoid scroll. Trim redundant words only;shared layout/padding/type stay fixed andscrolling is acceptable.

## Shared instruction scroll arrows support continuous hold
Owner approved consistent scrollbar rollout: solid triangle arrows,continuous pointer/Enter/Space hold until release/end,cleanup oncancel/blur/hidden/destroy. Controls appear only forinternal overflow;nativewheel/touch/keyboard/thumb retained. Keep shared padding/widths andproportional rounded neutral thumb. Defer ResizeObserver updates toanimationframe toavoid loop errors duringLivewireviewswitches.

## Policy round trips preserve unsent ticket and chat drafts
Reuse the tab-local policy-draft helper for whitelisted ticket fields and the Harbor question field. Restore once within one hour, scoped to authenticated user/ticket/reply-update version or the current chat conversation ID. New conversations must not inherit old text. Policy navigation must never save or send. Abort navigation listeners on destruction and tolerate unavailable storage. Regress desktop/phone returns with both citations and independent ticket drafts.

## Inline feedback uses semantic colors and selective expiry
Routine successful saves use green and hide locally after eight seconds, pausing on hover or focus and while the document is hidden; retain layout space. A new identical success must restart the timer, but unrelated morphs/tab changes must not resurrect expired confirmations. Progress/info is blue, warnings amber, errors red; keep these and detailed cleanup summaries persistent. Keep text and live-region semantics, and preserve permanent sent/confirmed/result states. Eight seconds is the app choice, not a universal standard; do not time out the only copy of actionable information.

## Routine success feedback matches the approved success pill
Terry approved the Details and internal reply-save pills and authorized matching similar routine success confirmations. lucid.action-feedback defaults success to the same emerald-50 background, emerald-950 text and shared thin black ring as existing success badges; internal notes reuse it. Keep warnings/errors/progress as text in their semantic colors. Detailed queue cleanup counts use the same wrapping success pill and remain untimed so the counts stay available. Preserve timing, accessible live regions and reserved geometry; do not make confirmations interactive ticket statuses.

## Copy ticket links uses local feedback with a manual fallback
Use lucid.copy-ticket-link in embedded and standalone ticket status. It writes the actual customer status URL, reuses the approved eight-second success pill beside the action, and exposes a selectable readonly link if Clipboard API access fails. Keep this client-only control isolated from polling morphs so confirmations are not erased early. Do not retain the replaced ticket-copy toast or duplicate timer logic. Other settings/return-code toasts are separate.

## Shared guide structure and scrolling
Reuse lucid.instruction-panel with its accessible hide/show controls, current-page subtitle, short explanatory introduction, full-sentence numbered steps and secondary footer notice. Coverage determines step count. Shared spacing and readable text take priority over forced no-scroll fit; preserve responsive scrolling, hold-to-scroll controls and cleanup.

## Guides that change explain that up front
For guides that change with task stages, tabs, or workflow events, explain when the steps update in the opening paragraph. Keep static guides such as the dashboard and policy pages free of that note.

## Ask Lucid uses a rounded composer without permanent navigation footer
Owner removed the permanent Customer support, Document chat, Invoice review and Privacy row beneath Ask Lucid. Preserve the privacy notice. Highlight the rounded outer composer on focus, matching the shared reply editor, rather than outlining its inner textarea.

## Shared chat actions and confirmations
Owner correction 2026-11-20: New chat is a visible text action directly in Ask Lucid's header in normal and expanded modes, never hidden in overflow. Three-dot menu retains secondary Expand/Collapse window. New chat opens the existing shared Flux confirmation modal. Preserve shared Thinking state, separate stopped feedback, friendly business copy and recognizable action/navigation roles.

## Ticket clocks follow the documented audience policy
Reuse the shared timestamp component for agent/customer views, retain ISO datetime values and preserve UTC storage. Follow the confirmed worldwide-viewer policy in views.md: history displays in each visitor's local/preferred zone; location-bound deadlines retain their stated business zone. Do not force Central Time or infer that browser-local fallback behavior has passed manual acceptance.

## Localized instants must survive server polling
Shared lucid.local-time owns its browser-formatted text with wire:ignore so Livewire polling cannot replace it with the server UTC fallback after x-init. Keep immutable ISO datetime values inside stable keyed message rows. No-JavaScript fallback explicitly labels UTC; dates that are literal business/document dates must not be shifted as instants. Verify multiple zones and post-morph output, not only initial render.


## Ask Lucid opens promptly without duplicate history loads
Ask Lucid opens immediately with 360ms motion and preloads history on pointer entry or keyboard focus. Share one pending history request. Invalidate pending history responses when New chat clears the conversation so delayed preloads cannot resurrect deleted messages; preserve reduced motion and existing controls.
