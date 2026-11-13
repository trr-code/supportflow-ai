---
paths:
  - "resources/css/**"
---

# Css

## Consistent lavender roles and active hover
Use shared --lucid-accent #7735dc for showroom icons,links,tab underline,send arrow,guide numbers; --lucid-lavender #eee7ff for pale icon/number/send fills and --lucid-selected #e4d6fc for selected navigation/hover fill. Send uses dark purple on pale fill rather than unrelated medium-purple/white. Active sidebar hover must preserve lavender background and readable dark text. Preserve the landing artwork.

## Modal controller cannot consume workspace grid row
Workspace-owned Flux modal renders ui-modal direct grid child; set .showroom-workspace > ui-modal {display:contents} so hidden controller does not create implicitrow/shrink primary panels. Dialog itself stays top-layer and accessible. Browser regression asserts sidebar/workarea bottoms stay within14px of desktopviewport. Final destructive reset confirmation uses Flux danger/red;ordinary actions lavender.

## Collapsed workspace keeps black icon rail
Owner superseded complete-hide collapse: all shared showroom-support-unified desktops retain82px black rounded sidebar with compact Lucid,collapse toggle and existing single set of icon links. Hide labels/section headings only; preserve12px gap,lavender active hover,16px expanded navigation and mobile toggle. No duplicate rail/nav. Workspace toolbar reopen button removed; expand using railtoggle. Icon actions need aria-label/title.

## Guide instructions and supporting notices have distinct sizes
Owner clarified shared guide/sidebar instructions remain16px, including support and ticket guides. Bottom showroom-guide-disclaimer notices use14px readable secondary print across workspaces, superseding the earlier16px footer-notice rule. Keep actionable guidance and ticket-link privacy explanation at body size.

## Workspace header dividers align across desktop panels
Owner approved instruction heading and close control in one header row, lavender divider aligned with middle toolbar boundary, current-page subtitle below. Expanded black sidebar divider matches its muted footer border #35303f; logo/collapse sit above it. Preserve existing navigation row positions and collapsed icon rail.

## Dashboard shares sidebar widths and flat workspace structure
Owner rejected400px dashboard guide: keep shared220px expanded left navigation/320px right instructions (collapsed82px),same components andresponsive content insets. Dashboard middle is one white surface withlight section dividers;retain four pale summary tiles butremove nested rounded/shadowed attention/chart/activity cards. Trim/split guidance for fit;do not widen sidebar orshrink text toforce it.

## Consistent spacing takes priority over avoiding scroll
Owner explicitly supersedes earlier forced no-scroll rules: use the same16px vertical padding for numbered right-panel steps/section dividers,16px number/text gap,8px heading/body gap,shared24px guide insets and16px footer spacing. Keep16px instructions/14px notices and220/320 widths. Allow scrolling instead of page-specific tight padding orwidening. Dashboard chart/activity vertical dividers share exact50% centerline.

## Shared text entry uses16px across the application
Owner chose app-wide consistency using approved chat/manual composer text as the baseline: all regular text-entry inputs, selects and textareas use16px at desktop and phone widths; textareas use1.5 line height. Includes AI draft editors, chat/document/manual/notes composers, ticket and invoice forms, preview/settings/auth text fields. Compact body/labels/actions remain14px; section headings and nav/guide retain established role-based sizing and approved marketing hierarchy. This supersedes the internal14px draft-text decision. Preserve256px AI draft heights,130px manual/chat baseline and40px action height.

## Keep sections but avoid divider lines between related review blocks
Owner prefers spacing to distinguish related request/AI assessment/suggested reply blocks; removing redundant lines does not remove sections or headings. Keep dividers for navigation boundaries, genuinely separate major groups such as Ticket information, and between list items, with no trailing footer-like line after the last item. Compare against approved app screens before adding more page-specific separators.

## Shared content and subsection heading roles
Use lucid-content-heading for20px semibold primary workspace content headings, matching Human review, internal/embedded ticket subjects, scenario titles and invoice upload/review headings. Page H1 titles remain24px; ordinary group/section labels stay16px semibold, including policy categories and dashboard groups. Heading rank remains semantic, not a size selector. Preserve deliberate homepage/empty-state marketing, document prose and shared guide/chrome roles; compare all approved screens before choosing a new baseline.

## Internal reply switches opt out of browser scroll anchoring
Scope overflow-anchor:none and scroll-behavior:auto to the nested .showroom-page-body containing .showroom-ticket. Keep the existing compact composer and visible-bottom scroll preservation. Check the first switch after refresh and sample delayed movement in both directions; do not apply this exclusion globally to chat transcripts or instruction-panel scrolling.

## Shared reply editor dimensions
Both Harbor and internal-ticket suggested drafts are fixed256px, with internal scrolling and no resize handle. Chat/manual composers start at130px overall with80px textarea minimum, grow to288px textarea height, then scroll. Preserve shared padding and16px entry text; do not reserve a large empty slot around compact manual replies.

## Shared action and navigation sizes
Regular actions use40px height,14px labels and12px horizontal padding through shared tokens; preserve role-appropriate widths and wrapping. Tabs use36px height with16px labels; sidebar navigation remains48px or taller. Icon-only/composer controls and homepage CTAs retain their separate roles. Verify rendered sizes and unclipped labels.

## Plain landing demo icons
Homepage demo-card icons use purple strokes without lavender background tiles, glow or shadows. Preserve alignment and the separate Lucid wordmark styling.

## Prevent anchoring jumps when activity expands
The active ticket Activity view disables body scroll anchoring because the mobile guide below it can otherwise move the viewport when older events append. Preserve the separate ticket-panel anchoring guard. Do not use wire:click.preserve-scroll for append: the installed Livewire implementation adds the body-height difference to window scroll, causing a jump. Avoid compensating scroll timers or vendor changes.

## Ticket composer focus outlines must clear ancestors
The ticket page body owns scrolling. Legacy secondary-content and notes-panel wrappers must not clip external composer focus outlines. Verify outline width plus offset against ancestor clipping bounds on desktop and phone; matching field dimensions alone does not prove the editor is unclipped.

## Current homepage baseline
Keep the approved full-width homepage with black navigation/footer, independent purple hero mist fading into the white three-demo gallery, and aligned demo actions. Preserve current artwork offsets, overlay and responsive clearances in showroom.css; do not revive contained cards, equal-height gray halves, plain no-artwork heroes or earlier artwork experiments. New visual changes require the current owner direction.

## Current shared workspace frame
Showroom workspaces use the neutral #e7e8eb browser backdrop, centered maximum 1600px frame, one black navigation card and white task/guide panels with 12px gaps. Shared expanded navigation is 220px, collapsed icon navigation 82px and guide 320px; preserve responsive mobile navigation, existing actions and readable scrolling. Do not restore lavender navigation, duplicate rails, full-hide desktop collapse or old 208px/fixed-row layouts.

## Sidebar and guide text roles
Keep shared navigation and actionable guide text at 16px and secondary guide-footer notices at 14px. Preserve shared step padding and readable wrapping; allow scrolling rather than shrink text, compress spacing or widen panels. Compare equivalent roles across screens instead of forcing every label, button, source number or heading to the same size.

## Ticket queue shares flat middle surface and instruction panel
Owner requires queue to match dashboard/Harbor: same220px left/320px right widths, sharedinstruction-panel with current-page intro/numbered steps, consistent insets/16px step padding/14px notice,hide/show controls. Queue middle is one white surface with inset dividers,not nestedroundedcards. Preserve polling,filters,pagination,scenarios/reset authorization. Fit rows to availableheight;do not hardcode historical counts.

## Align fixed workspace footers or leave the middle open
At the desktop three-panel layout, align actual fixed middle footer dividers with the Help and instructions dividers using the existing shared footer offset. When the middle ends in scrolling content, leave it open and omit a trailing footer-like divider. An inline privacy disclosure stays in the document flow and needs no bottom border when it is the last section. Inspect desktop and stacked phone states.

## Homepage hero has no tour panel
The owner superseded the old Start here tour layout. Keep the full-width purple waves and footer mist, and use a single hero copy column with the three direct demo cards in the gallery.

## Ask Lucid launcher and motion
For the planned Ask Lucid showroom guide, use a compact bottom-right chat launcher and a short upward slide with fade on open, reversed on close, inspired by Zendesk. Respect reduced motion. Reuse Lucid’s existing message bubble styling and microphone composer. Size the panel for its available space and keep it clear of workspace controls.
