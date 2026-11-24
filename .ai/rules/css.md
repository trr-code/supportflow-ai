---
paths:
  - "resources/css/**"
---

# Css


## Modal controller cannot consume workspace grid row
Workspace-owned Flux modal renders ui-modal direct grid child; set .showroom-workspace > ui-modal {display:contents} so hidden controller does not create implicitrow/shrink primary panels. Dialog itself stays top-layer and accessible. Browser regression asserts sidebar/workarea bottoms stay within14px of desktopviewport. Final destructive reset confirmation uses Flux danger/red;ordinary actions use the current monochrome roles.

## Collapsed workspace keeps black icon rail
Owner superseded complete-hide collapse: all shared showroom-support-unified desktops retain82px black rounded sidebar with compact Lucid,collapse toggle and existing single set of icon links. Hide labels/section headings only; preserve12px gap,white active navigation,16px expanded navigation and mobile toggle. No duplicate rail/nav. Workspace toolbar reopen button removed; expand using railtoggle. Icon actions need aria-label/title.

## Guide instructions and supporting notices have distinct sizes
Owner clarified shared guide/sidebar instructions remain16px, including support and ticket guides. Bottom showroom-guide-disclaimer notices use14px readable secondary print across workspaces, superseding the earlier16px footer-notice rule. Keep actionable guidance and ticket-link privacy explanation at body size.

## Workspace header dividers align across desktop panels
Owner approved instruction heading and close control in one header row, black divider aligned with middle toolbar boundary, current-page subtitle below. Expanded black sidebar divider matches its white footer border; logo/collapse sit above it. Preserve existing navigation row positions and collapsed icon rail.

## Dashboard shares sidebar widths and flat workspace structure
Owner rejected400px dashboard guide: keep shared220px expanded left navigation/320px right instructions (collapsed82px),same components andresponsive content insets. Dashboard middle is one white surface withlight section dividers;retain four pale summary tiles butremove nested rounded/shadowed attention/chart/activity cards. Trim/split guidance for fit;do not widen sidebar orshrink text toforce it.

## Consistent spacing takes priority over avoiding scroll
Owner explicitly supersedes earlier forced no-scroll rules: use the same16px vertical padding for numbered right-panel steps/section dividers,16px number/text gap,8px heading/body gap,shared24px guide insets and16px footer spacing. Keep16px instructions/14px notices and220/320 widths. Allow scrolling instead of page-specific tight padding orwidening. Dashboard chart/activity vertical dividers share exact50% centerline.

## Keep sections but avoid divider lines between related review blocks
Owner prefers spacing to distinguish related request/AI assessment/suggested reply blocks; removing redundant lines does not remove sections or headings. Keep dividers for navigation boundaries, genuinely separate major groups such as Ticket information, and between list items, with no trailing footer-like line after the last item. Compare against approved app screens before adding more page-specific separators.

## Shared content and subsection heading roles
Use lucid-content-heading for20px semibold primary workspace content headings, matching Human review, internal/embedded ticket subjects, scenario titles and invoice upload/review headings. Page H1 titles remain24px; ordinary group/section labels stay16px semibold, including policy categories and dashboard groups. Heading rank remains semantic, not a size selector. Preserve deliberate homepage/empty-state marketing, document prose and shared guide/chrome roles; compare all approved screens before choosing a new baseline.

## Internal reply switches opt out of browser scroll anchoring
Scope overflow-anchor:none and scroll-behavior:auto to the nested .showroom-page-body containing .showroom-ticket. Keep the existing compact composer and visible-bottom scroll preservation. Check the first switch after refresh and sample delayed movement in both directions; do not apply this exclusion globally to chat transcripts or instruction-panel scrolling.

## Shared action and navigation sizes
Regular actions use40px height,14px labels and12px horizontal padding through shared tokens; preserve role-appropriate widths and wrapping. Tabs use36px height with16px labels; stacked Customer support view controls may grow beyond36px to wrap their labels without reducing type. Sidebar navigation remains48px or taller. Icon-only/composer controls and homepage CTAs retain their separate roles. Verify rendered sizes and unclipped labels.

## Plain landing demo icons
Homepage demo-card icons use the current black resting/white hover strokes without background tiles, glow or shadows. Preserve alignment and the separate Lucid wordmark styling.

## Prevent anchoring jumps when activity expands
The active ticket Activity view disables body scroll anchoring because the mobile guide below it can otherwise move the viewport when older events append. Preserve the separate ticket-panel anchoring guard. Do not use wire:click.preserve-scroll for append: the installed Livewire implementation adds the body-height difference to window scroll, causing a jump. Avoid compensating scroll timers or vendor changes.

## Ticket composer focus outlines must clear ancestors
The ticket page body owns scrolling. Legacy secondary-content and notes-panel wrappers must not clip external composer focus outlines. Verify outline width plus offset against ancestor clipping bounds on desktop and phone; matching field dimensions alone does not prove the editor is unclipped.

## Current homepage baseline
Keep the approved full-width homepage with black navigation/footer, independent purple hero mist fading into the white three-demo gallery, and aligned demo actions. Preserve the approved desktop artwork offsets and layout. The owner's mobile/tablet completion pass permits the stacked card list and a separate artwork band below hero copy described in the stacked-layout rule. Do not revive contained cards, equal-height gray halves, plain no-artwork heroes or earlier artwork experiments. New visual changes require the current owner direction.

## Current shared workspace frame
Showroom workspaces use the neutral #dcdde1 browser backdrop, centered maximum 1600px frame, one black navigation card with a clearly visible crisp white outer edge (1px border plus 1px outer ring, without changing its box dimensions) and white task/guide panels with 12px gaps. Shared expanded navigation is 220px, collapsed icon navigation 82px and guide 320px; preserve responsive mobile navigation, existing actions and readable scrolling. Do not restore lavender navigation, duplicate rails, full-hide desktop collapse or old 208px/fixed-row layouts.

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

## Document Chat has one desktop scroll region in the middle panel
Let the desktop Document Chat transcript expand inside the document workspace scroll region so a wheel gesture over the answer has one scroll owner. Keep the shared composer sizing. Upward scrolling pauses automatic following immediately, and a downward return to the end or a new send resumes it. Preserve the transcript scroller on stacked layouts and the standalone dock.

## Preserve the original workspace launcher anchor
Owner rejected the viewport-edge/outside-gutter relocation and broad 76px gutters/phone scroll strip. Ask Lucid retains its original outer-right workspace anchor. Its vertical position follows the hidden-guide chat dock only on the two explicitly approved chat screens; all other screens keep their prior bottom position. Only small local offsets/control clearance at actual collisions are approved. Preserve page widths, normal scrolling and fixed footer alignment; inspect wide-window placement against the workspace edge.

## Dock Ask Lucid above only hidden-guide desktop chats
Only Harbor customer chat and embedded Document Chat, at desktop widths with instructions hidden, reserve a blank row above the full-width composer for Ask Lucid. Keep its existing outer workspace-right anchor, not the conversation column. CSS anchor positioning tracks the row as drafts grow or the document scrolls; an open Ask Lucid panel fits above it. Instructions shown, stacked layouts and all other screens retain their existing placement. Remove obsolete fixed Harbor offsets/composer shortening; preserve dashboard and queue positions.

## Shared reply editor dimensions
Suggested drafts in Harbor and internal tickets remain fixed256px with internal scrolling. Owner 2026-11-20 supersedes the130px chat baseline: chat composers start about80px overall with36px textarea minimum and grow before scrolling. Manual/note editors keep their existing80px field minimum/288px maximum unless separately approved. Instruction fields remain80px minimum. Preserve16px entry type, shared padding and full available chat width.

## Shared text entry uses16px across the application
All regular text-entry inputs, selects and textareas use16px at desktop and phone widths; textareas use1.5 line height. Compact body/labels/actions remain14px; section headings/navigation/guides retain their role sizes. Preserve256px suggested drafts and40px regular actions. Current owner-approved compact chat composer dimensions supersede the old130px baseline; do not shrink type to fit.

## Chat uses original full-width alignment with local overlap fixes
Preserve full available Harbor/Document chat width aligned with surrounding content. Do not restore centered/capped columns, extra mobile microphone/Send padding or panel-dependent control offsets. Owner 2026-11-20 approves compact growing chat composers, superseding the original130px/80px chat baseline. Preserve dashboard/queue and existing launcher positions; hidden-guide desktop docking applies only to Harbor/Document chat.

## Compact chat panels and contiguous focus
Latest desktop owner sizing refinement 2026-11-20 supersedes444x782 normal and688x882 expanded: use400x700px normal and672x864px expanded, bounded to the viewport. Normal retains launcher/hidden-guide anchors; expanded retains independent viewport clearance. The 2026-11-22 compact-screen rule below supersedes launcher clearance for the open mobile panel, which uses the available screen with its own Close control. Preserve visible New chat, compact growing input, existing text sizes and contiguous focus band.

## Flux action roles use explicit application classes
Flux variant is a component prop, not a rendered data-variant attribute. Style secondary outlined actions through the merged lucid-outline-action class on owned Flux buttons; keep primary/destructive, navigation, tabs and icon/composer roles distinct. Verify computed styles on the rendered element instead of assuming the prop reaches the DOM.

## Homepage demo cards use the shared black outline
Keep a thin black resting outline on all three homepage demo cards, matching the Harbor suggested-question/secondary outline treatment. Resting icons and Try demo labels/arrows are black. On hover and keyboard focus use a black surface, pure-white headings/icons/Try demo text/arrows. Homepage guidance, card descriptions and safeguard text use the same brighter cool purple #885bdb without glow. Keep description purple identical at rest, hover and keyboard focus; use pure-black hover/focus surfaces so that purple remains readable on both white and black. Keep safeguard icons black and the step-by-step instructions phrase bold black. Preserve wording, geometry and artwork.

## Monochrome workspaces and white Lucid glow
Owner decision 2026-11-22 supersedes lavender workspace accents: customer support, documents, invoices and Agent desk use white active navigation on black, black dividers on white and white dividers on black. Guide/source number circles use black with white numerals; invert citation contrast inside black assistant bubbles. Use neutral fields, tabs, scrollbars and microphone waves; preserve semantic status/error colors. Owner reverted the white-wave preview: retain the original purple landing artwork without grayscale filters, preserving its shapes, placement and detail. The latest owner refinement makes all Lucid wordmarks and Ask Lucid launcher/glyph halos pure white throughout, including legacy chrome/footer; use moderate white halos (roughly 60% opacity at the close/shared halo, fading outward), halfway between the former strong and subdued previews while keeping the white lettering crisp; the owner found the former white glow stronger than the old purple. The owner rejected white hero accents as blending into the other text; the owner liked the distinct violet direction but found #c878ff too pink. The current preview uses cooler violet #b69aff with a moderate white close halo and cooler purple bloom, halfway between the prior strong and subdued previews to retain glow without excessive blur, remaining gentler than Lucid’s white glow for the homepage eyebrow and See AI in action. Preserve lettering, shared layout and behavior.

## Outline only the active workflow progress stage
Owner decision 2026-11-22: stateful progress cards show a thin #111 inset outline only on the current stage; completed/upcoming stages and static numbered instructions do not gain that outline. Reuse the shared ticket-progress treatment in embedded and standalone views. Keep progress tied to actual workflow state, not animation. The black sidebar outer white edge is reinforced by a crisp 1px outer ring; leave its inner header/footer dividers unchanged.

## Black Ask Lucid headers have a white edge
Owner decision 2026-11-22: outline the black Ask Lucid header (Lucid/New chat/options/close) with a thin white edge, on its rounded top and sides only; no bottom white stroke at the black-to-white join. Apply through the shared header in normal/expanded views on every host page. Keep only white around the black header: do not leave an enclosing black panel border behind it. Draw the black edge only on the white transcript/composer sections; no black panel inset shadow may sit behind the rounded header corners. Keep the header background clipped inside its white border to prevent dark corner bleed. Preserve outer panel sizing, corners and controls.

## Stacked mobile and tablet layouts prioritize reachable work
Owner 2026-11-22 authorizes mobile/tablet completion separately from later performance work. Below 1100px let task panels grow with controls and keep only long transcripts internally scrollable; never clip suggested questions or composers into a fixed 520px row. Compact phones (including short landscape) follow the icon-only navigation rule below (2026-11-23), superseding the earlier Menu/Home header controls. Compact Ask Lucid uses the available visual viewport, Close, a backdrop and modal focus containment; a substantially reduced visual viewport also activates it for a tablet keyboard. Clean up viewport listeners when the component is destroyed. Keep instructions reachable via the toolbar and let hiding them remove their space. Homepage mobile/tablet cards form one readable list with artwork clear of hero copy; preserve the approved desktop geometry and palette. Browser emulation does not establish real-device keyboard/microphone acceptance.

## Compact headers use one hamburger beside the page title
Owner 2026-11-23 replaces the compact Menu text plus separate Home link with one icon-only hamburger next to the existing current-page title across shared demo/support/agent headers. Keep Home inside navigation. Use invisible Open navigation/Close navigation accessible names, aria-expanded and aria-controls, and a 44px square tap area. Keep the desktop sidebar collapse chevron: collapsing a visible rail is distinct from opening compact navigation. Remove obsolete phone-Home markup/styles; do not add visible Open navigation text or change unrelated page actions.
