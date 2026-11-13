# Historical UI instruction cleanup — 2026-11-09

Historical evidence only. These removed/superseded entries are NOT current instructions. Follow AGENTS.md, the current indexed rules and .ai/guidelines/ui-review.md. Global entries marked by the cleanup were relocated through Boost, not discarded. Existing readiness/design-audit history remains intact.

## .ai/rules/components-lucid.md (obsolete file)

```markdown
---
paths:
  - 'resources/css/**,resources/views/components/lucid/sidebar.blade.php'
---

# Components Lucid

## Single lavender sidebar supersedes double black rail
Owner superseded black icon rail plus labeled sidebar: Harbor uses ONE collapsible navigation directly on full-browser lavender #eeebf4, with glowing Lucid in a compact true-black pill. Keep centered max1600 frame and 12px panel gutters. Homepage hero is lighter gray #64656d; retain equal gray/white desktop halves and black header/footer.
```

## .ai/rules/css.md

```markdown
## Showroom uses a centered capped frame across pages
Owner prefers homepage and demo workspaces to share a centered maximum-width frame (1600px), filling narrower browsers with responsive gutters rather than stretching across wide monitors. Choose breakpoints by available CSS pixels, not physical monitor inches. Keep copy readable and panel corners restrained (14px desktop, 12px phone).

## Homepage joins hero and demo body
The owner approved lucid-design/lucid-homepage-connected-approved.png. Home uses one continuous rounded hero/demo surface with no gray seam, a compact 420px tour panel, and lucid-hero-mist.png artwork fading into white. Preserve readable functional tour content, stack it on phones, and retain shared max-width/gutters. This supersedes the separate dark/white homepage cards.

## Landing header and footer are connected
Homepage is one continuous surface: black navigation joins hero with a faint divider, existing purple hero mist leads to white demos, and subtle reversed monochrome mist leads back into black footer. No gray gaps or internal rounded seams; only outer top/bottom corners are rounded. Keep footer text on solid black and preserve shared gutters/max width.

## Prefer regular landing navigation over a whole-page card
Latest owner choice supersedes connected homepage header/footer: flat black navigation, separate contained rounded hero/demo surface, simple separate black footer. Remove whole-page clip and footer mist; keep purple hero transition, shared maximum width/gutters and a bright white/purple Lucid glow. Footer appearance remains adjustable. Freshdesk/Apollo are visual references, not exact layout mandates.

## Apollo screenshot governs landing-page chrome
Latest approved direction supersedes detached navigation/footer bars: charcoal landing page with glowing Lucid and Explore demos directly on the background, aligned to the contained main card's outer edges. Compact transparent footer on same charcoal, thin divider, tagline and essential links; no footer logo, mist, sales section or directory. Preserve existing rounded hero/demo card and brighter glow. Use lucid-homepage-apollo-structure.png as implementation reference.

## Full-width scrollable homepage supersedes contained card
Owner approved lucid-design/lucid-homepage-full-width-approved.png: full-width black navigation and section backgrounds, no enclosing rounded page card or gray gutters. Keep content centered/readable, compact white tour card and three clickable demos, long purple hero mist fading gradually to white, compact black footer with soft transition. Preserve workspace framing; older contained-homepage rules are superseded.

## Homepage desktop fit and wider navigation
Owner prefers fitting the homepage in one normal desktop viewport, with natural scrolling for smaller windows/phones, instead of an intentionally tall marketing page. Navigation contents sit wider than hero copy with equal outer padding and a subtle bottom divider. Footer uses uneven rising smoke from the shared support mist texture rather than a smooth gray gradient. Preserve readability and functional tour steps.

## Homepage footer is plain black
Owner superseded the rising-smoke footer: use a compact solid black footer with a clean white-to-black edge, no fade or smoke texture. Keep wider navigation, its subtle divider, desktop fit and purple hero transition.

## Clean hero and taller demo gallery
Owner approved lucid-design/lucid-homepage-clean-gallery-approved.png: mostly black hero with only subtle lavender accent, clean straight boundary to white and no purple wave/mist artwork. Three equal taller demo cards stack icon above title and benefit, with aligned Try demo links and breathing room before plain black footer. Preserve wider navigation, subtle divider, functional intro and readable desktop fit; supersedes older hero-wave direction.

## Charcoal hero gives white gallery more space
Owner lightened the homepage hero to charcoal/dark gray (#292a2f), retaining black navigation and plain black footer. Give the white gallery breathing room above/below cards; preserve readable stacked demo cards and desktop fit. No purple swirl artwork or hero fade.

## Wider tour aligns with hero eyebrow
Homepage tour panel is wider (up to 560px desktop, 460px tablet), with its top aligned to the AI business automation eyebrow. Hero is lighter charcoal #35363c, black navigation/footer remain. Preserve semantic heading outline H1, H2 tour, H2 gallery, H3 demo titles; visual alignment does not set heading level. Keep readable desktop fit and natural narrow-screen reflow.

## Lower hero alignment and balanced white gallery
Keep hero eyebrow and wider tour aligned with 36px desktop clearance below navigation; move panel down rather than moving hero copy upward. Desktop hero/white gallery share roughly equal height (white slightly larger), preserving spacious gallery, one-screen normal desktop fit and scrolling when needed. Supersedes compressed top-aligned padding.

## Equal gray and white homepage halves
Owner clarified desktop gray hero and white gallery must have equal heights excluding black header/footer, rather than giving white a larger share. Hero color is #414248 (lighter charcoal). Preserve 36px top alignment, wider tour, readable cards and natural reflow on smaller windows.

## Lavender fills browser around capped support workspace
Support page background fills the full browser in #eeebf4, matching its light navigation and panel gutters. Preserve centered max1600 workspace width, black rail through far-right guide card span and existing internal spacing. Background expansion is not workspace expansion.

## Lavender sidebar and lighter gray hero
Latest owner direction supersedes double black rail and pink mockup: full-browser lavender #eeebf4, single220px light sidebar, black Lucid pill, white task/context cards with12px gutters and centered1600px cap. Homepage hero now #64656d, retaining black header/footer and equal desktop gray/white halves.

## Black navigation card and gray support background
Latest owner direction supersedes lavender background/sidebar: Harbor backdrop uses homepage hero gray #64656d. Navigation is one pure-black rounded12px card matching guide card shape. Desktop collapse removes whole navigation card and column; toolbar Navigation button reopens it. Preserve max1600 frame,12px gutters, mobile toggle and current workflows.

## Restore full-width cloud homepage
Owner restored screenshot cloud direction: black/purple lucid-hero-mist full-width hero fading white; keep current wider560px tour, navigation outside/full-width and taller demo cards. Footer uses a single full-width monochrome lucid-header-mist transition blending exactly into #090a0e, with no separate gray/color band. Preserve desktop fit and mobile reflow. Workspace gray/black navigation remains.

## Reference waves positioning and lighter Harbor backdrop
Owner correction: compare lucid-design/lucid-homepage-wide-navigation.png (matches supplied reference). Hero wave artwork must sit in an independent full-width layer starting80px below hero top, extending80px below with gradual200px white/lavender fade; do not compress into hero background. Footer smoke must have visible64px depth rather than32px strip and blend into same black. Harbor full-browser background light neutral #e7e8eb supersedes dark #64656d; black navigation and white cards stay.

## Lower hero waves and raise footer smoke
Owner requests artwork away from copy and less blank space below trust row. Hero wave layer now starts180px below hero top (80px phone), shifted100px down from prior desktop position. Footer smoke128px tall starts64px above footer, extending into unused gallery space; retain pure-white upper blend and black footer, text/cards and desktop fit.

## Smoke height adapts to available desktop space
Final clearance refinement supersedes fixed128px smoke: normal viewports use80px smoke starting16px above footer; desktops >=1100px wide and1000px tall use128px smoke starting64px above footer to fill larger whitespace without covering trust labels. Hero waves moved100px lower; white fade100px tall keeps wave detail visible.

## Taller cloud hero supersedes equal halves
Owner superseded50/50: use60% hero/40% gallery on tall desktop to reveal full waves/cloud fade below copy. Wave layer spans80px below hero top through bottom, no negative-bottom crop. Short desktops reserve340px readable gallery and give rest to hero. Trust row sits near footer via auto margin. Footer returns compact80px smoke with no upward extension; retain pure-white blend and black. Bigger560px tour remains.

## Small artwork and footer clearance adjustment
Preserve approved hero copy and tour positions. Owner requested only background lower: desktop wave layer shifted24px down via inset104px 0 -24px, preserving image height. Footer top padding50px (6px increase) gives text tiny cloud clearance. Keep60/40 tall layout and compact footer.

## Additional small hero artwork shift
Owner requested same24px downward artwork-only adjustment again: desktop hero pseudo layer inset128px 0 -48px. Keep hero copy/tour positions, footer50px top clearance, image height and other layout unchanged.

## Tiny footer black clearance
Latest owner fine adjustment adds4px black above footer text: homepage footer top padding54px supersedes50px. Preserve wave offset128px and all other approved styling.

## Quiet hero artwork behind fixed copy
Owner requested less wave glare while preserving hero text and560px tour positions. Desktop artwork offset152px top/-72px bottom (another24px down), subtle black overlay fades clear by75%, white fade shortened100px to keep dark area lower near gallery heading. Footer54px clearance and remaining approved layout retained.

## Workspace sidebars consistently use16px text
Owner requires typography changes applied across all workspaces,not one screen. Shared showroom-support-unified sidebar nav items/section labels and right guide body,steps,source excerpts/numbers,back links,buttons and footer notices use1rem/16px; preserve larger panel headings. Covers Harbor,documents,invoices,policies/agent frames; verify responsive fit and ticketguide no-scroll.

## Workspace heading scale preserves approved marketing hierarchy
Use24px main H1 titles throughout showroom-support-unified. Ordinary dashboard group headings, invoice form section headings and document-chat header use16px semibold matching ticket/review sections. Preserve larger homepage/empty-state marketing headings as deliberate separate roles,14px compact body/labels,16px input text and16px navigation/guide text. Typography comparisons must use all reviewed areas and rendered browser evidence.

```

## .ai/rules/docs.md

```markdown
## Carry audit runtime evidence gaps through every fix package
For Lucid audit/fix work, read docs/system-design-audit.md and its Herd verification follow-up. Preserve valid code-review/test evidence; fill missing runtime evidence per package using Herd site info and fresh, site-scoped Insights with examples off. Record workflow, environment, observed result and remaining gaps before closing findings; reopen findings if evidence contradicts them. Herd does not capture test runs or prove Forge/CI, provider billing, backups, restore, mobile/accessibility or file-picker readiness. Finish with cross-workflow verification and an explicit unresolved-gap review. Check tool availability each chat and flag inaccessible tools/docs; the owner should not need to repeat this reminder. Do not expand package scope or bypass deletion, dependency, commit/deploy or production-load approvals.

## Verify answers against the official documentation relevant to the question
The owner wants documentation checks by default, without repeating the request. Before substantive technical/product guidance or implementation, consult current official documentation for the subject: OpenAI/Codex for agent behavior/setup, Herd for local tooling, installed-version Laravel/Livewire/package docs for application work, and the relevant primary authority otherwise. Reuse sufficient verified sources already in context. Cite the supporting source concisely and distinguish documented behavior, observed local evidence, engineering judgment and unverified claims. Explicitly flag inaccessible documentation or missing coverage; never imply documentation established something it did not. Persistent instructions improve continuity but do not guarantee every future agent will comply.

## Use Conventional Commits for future commit messages
The owner adopted Conventional Commits 1.0.0 for this project going forward. Use lowercase type and a short meaningful lowercase scope where useful: fix(security): prevent unsafe uploads. Common types: feat, fix, docs, refactor, perf, test, build, ci, chore. Keep descriptions concise, imperative and without a trailing period; use ! and a BREAKING CHANGE footer for actual breaking changes. Scope is optional under the specification; do not invent one for changes with no useful single area. Preserve existing history. This message-format preference does not authorize commits, pushes or deployment; obtain the owner's approval as already required.

## Current release status supersedes historical checkpoints
Read the top current checkpoint in docs/release-readiness.md and the newest package checkpoint in docs/system-design-audit.md before reporting status. Older tables and appended deployment notes are historical evidence, not current failures or pending approvals. Verify CI by exact SHA and the deployed SHA separately; local package completion does not imply CI/Forge verification of unpushed changes. The direct-main workflow and current Conventional Commits preference in .ai/rules override older .cursor git-workflow guidance; commits/push/deploy still require owner authorization.

## Print checkpoint additions in the chat
When updating audit or readiness checkpoints, tell the owner directly in the normal chat what was newly added, what passed and what remains; do not expect them to reread the document. Name and link the exact updated section, ideally its heading line. Keep the documents for durable continuity, and keep the user-facing change summary concise.

## Local design reviews use built assets to avoid Vite reloads
During owner walkthroughs use built assets (npm run build) rather than a stale public/hot marker. Vite reconnects can reload a page even without edits; refresh:true also reloads saved Blade/Livewire files. Verify actual browser scripts exclude @vite/client before claiming this mechanism is absent. The review backup marker is storage/framework/vite-review-backup.hot; starting Vite can restore public/hot. Do not claim this proves every reported reset cause.

## Manual full refresh during Lucid walkthroughs
Full-page reload-on-save defaults off: refresh is process.env.LUCID_AUTO_REFRESH === 'true'. Keep CSS HMR. Manually refresh after Blade/Livewire/routes edits before reviewing; Vite must restart for process-env opt-in changes. This does not disable Livewire polling or queue processing, alter production, or guarantee no reconnect reloads. Preserve owner preference during UI walkthroughs.

## End of UI package requires code conventions and existing-rule audits
Before closing the UI package, run full infer-conventions across the application and separately audit existing .ai/rules for obsolete, duplicate, conflicting and code-mismatched instructions; inference skips already-recorded conventions. Reconcile findings with the owner's current decisions and verify affected code/screens/tests before closure. Keep this gate distinct from the ongoing UI walkthrough, final SEO/structure gate and deferred custom-toast design.

## Finish replacement fixes with scoped cleanup
When replacing a failed or rejected approach, remove its unused code and temporary diagnostics, consolidate obsolete related rules into current guidance, and update the walkthrough/readiness checkpoint. Retain meaningful regression coverage and historical readiness evidence. Preserve unrelated work; do not claim an app-wide cleanup after auditing only the current area.

## Composer is the completion gate and verification follows the upfront schedule
Read .ai/guidelines/testing-verification.md and the top of AGENTS.md before code changes. Run affected tests during development, then composer test before handing completed code back: formatting, Rector, PHPStan and regular tests must all pass; direct Pest is not equivalent. Run relevant live-AI checks for AI changes, local performance checks for performance changes, and all required groups/manual gates for the final release candidate, reusing valid unchanged evidence. Agent owns execution and reports skips/blockers. This supersedes earlier test-only completion instructions.
```

## .ai/rules/livewire-pages.md

```markdown
## Landing page is the Lucid homepage
The visible product name is Lucid. Internal names stay Harbor & Co and SupportFlow. The homepage is an outcome page: charcoal hero, four demo links (Harbor support, Your documents, Invoice review, Replacements), Ask → Check → Review → Act, and a trust row. Direct demo routes are `/demo/harbor`, `/demo/documents`, `/demo/invoices`, and `/demo/replacement`. Old routes `/preview`, `/invoices`, and `/tools` stay. Never use the words Coming soon. Do not put “What this demonstrates”, “What this shows”, or “What this is not” on home. The guide opens those demos and must not present preset replies as a live model. wire:key belongs on the safety-page test foreach and on the workspace mode links, not on static landing cards.

## Demo home stays sales-focused
Demo home leads with Clear answers. Confident actions. Fold source-checking and human approval into the customer-support card. Limitations and dictation live on the Demo environment page.

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

## Documents showroom offers real sample and own-file paths
The proposal demo embeds the existing WorkspacePreview chat in the shared shell; /preview keeps its floating dock. Offer Try a sample document and Upload your own. Sample content goes through WorkspaceDocumentStore and normal indexing, never canned answers. Keep files/settings expandable, source inspection available, the shared microphone/up-arrow composer, and a stable real root around conditional embedded views for nested Livewire ownership.

## Homepage has one plain-language introduction inside the hero screen
For Upwork proposal visitors, use a single Start here tour inside the hero screen with Back/Next/Skip and direct demo links. Explain the four connected support features, own/sample documents and source passages, invoice review/confirmation/export, and custom project integrations in business language. Header Explore demos skips to clickable cards. Remove competing overview/read-steps/assistant-preview entry points. Keep all four cards clickable; Order actions is explicitly part of support. Add useful document/invoice card copy and align Try demo links with layout, not arbitrary blank padding. Research source: C:/Users/trrla/.codex/visualizations/2026/10/29/01a18501-a6d7-7af2-a563-51d78cc66b88/upwork-job-research.md (20 client postings, not reviews).

## Three demo cards supersede the original four-card homepage
Owner consolidated Order actions into Customer support and explicitly removed the fourth homepage card. Show exactly three clickable cards: Customer support, Ask your documents, Review an invoice. Mention replacements in support copy and keep the existing replacement examples inside Harbor. This supersedes earlier instructions to keep four cards. Keep the single Start here hero tour and align Try demo links per responsive row.

## Support guide is simple and whole-panel collapsible
Harbor uses one attached navigation/chat/guide surface with Customer support title, lavender Demo workspace badge and subtle irregular mist edge. Keep three short guide steps visible; hide/show the whole guide rather than individual instruction accordions. Avoid duplicate replacement/handoff links; use chat examples, numbered answer citations and Create a support ticket. Preserve real proposal confirmation, ticket/reply access and transcript escalation behavior.

## Compact support desk with contextual right panel
Harbor retains attached conversation-first shell: compact 64px charcoal toolbar with no mist, 220px desktop sidebar with no wave artwork, large white task area and collapsible context panel. Customer chat shows Demo guide or selected Sources & actions; human review shows Ticket details and review instructions. Reauthorize ticket/customer details with view policy. Keep Create a support ticket action, real confirmations and simulated-send boundaries.

## Ticket start guide explains full single-workspace journey
Before reviewTicket exists, human-support start uses Create a support ticket heading and Choose a ticket to try section,matching lavender/outline44px choices. Guide called Ticket guide for human/ticket/status without reviewTicket,describes create/submit,open reviewer,review/approve,See customer reply in same workspace; no second browser required. When reviewTicket exists retain authorized Ticket details and Review before sending.

## Customer and agent two-window marketing walkthrough
Owner supersedes single-workspace reviewer wording: explain send customer ticket,keep status open,open Agent desk in second tab/window,match ticket reference,check AI draft/sources,agent approves/sends,return to customer status. Plain business language about less drafting and agent control; no human-review/demo-reviewer jargon. Agent desk link target_blank relnoopener uses existing demo.agent entry for guests or ticket queue when authenticated.16px guide uses same lavender numbered/divided steps; ticket-choice buttons equal width,height andtype.

## Compact ticket guide using sidebar agent entry
Owner approved shorter four-step guide: open Agent desk from sidebar before submission,send ticket/keepstatusopen,check andsend reply,return customerstatus. Remove bottomduplicate agentCTA,intro and repeated sending disclaimer; shared footer disclaimer remains.16px numbered lavender guide fits1440x900 and1280x870 without scrolling. Sidebar Agentdesk now opens separate tab/window for authlink and guestentryform; preserve customerview.

## Embedded receipt keeps essentials and defers save-link help
Owner approved evidence-backed confirmation/details pattern: embedded receipt shows ticketreference,subject,status,progress,one short state sentence andconversation. Optional Save this ticket link details holds Copy ticket link and bearer-link privacy warning. Standalone receipt retains existing return-link explanation. Right panel on center=status is Ticket status guide with received,human approval/private drafts,automatic updates/sent reply;replace creation walkthrough only in this view.

## Right guide explains current review page
When reviewTicket exists,show compact ticket metadata and three short lavender steps explaining request,draft/source review or own reply,send/customer update. Do not repeat ticket creation walkthrough. Keep16px guidance,14px footer; review tab says Review ticket. Embedded send controls use shared lavender/outline styles; own reply uses a disclosure button.

## Submitted ticket navigation and status guidance
Once statusToken exists,middle view switch says Review ticket on customer status too. Remove duplicate bottom View ticket as a support agent CTA. Customer-facing tab/guide says See the reply as the customer. Status-guide step3 identifies status label in upper-right corner and Submitted→AIreviewing→Awaitingreview,points to Review ticket at top. Keep short prepared-form notice unchanged.

## Guided first review entry and inline status sequence
Latest owner direction supersedes always-visible review tab and removing CTA. Before first review for current ticket,hide create/review tab;show original outlined View ticket as a support agent below receipt. First click reveals Review ticket tab,remembers per ticket token,removes bottom CTA;tab persists on return/reload. Step3 preserves sentence with inline status-word highlights and → arrows,not separate full badges or arrowless row. Keep blue Submitted/AIreviewing andamber Awaitingreview,16px guidance.

## Status pills retain sequence arrows
Owner final clarification supersedes inline word highlighting: status guide uses three existing x-status-badge pills Submitted,AIreviewing,Awaitingreview with visible → arrows between them. Keep pills noninteractive and matching real status indicator. Original outlined review-entry CTA and once-revealed persistent review tab remain.

## Status pills flow inside sentence
Owner wants shared status pills inside step3 paragraph like words,not separate flex row. Preserve → arrows andnatural inline wrapping. Sent reply guidance explicitly names Conversation section on this page. Keep guided first-entry CTA/review-tab behavior.

## Restore separate status pill row
Latest owner request reverts inline sentence placement: three unchanged shared status pills with arrows sit in their own wrapping flex row after location sentence. Keep clarified Conversation destination,outlined hover fixes andguided first-review behavior. Supersedes inline-pill placement rule.

## Instructions panel identifies itself when open
Shared demo right-panel H2 is Step-by-step instructions inlavender;currentpage title appears beneath. Panel openbydefault. Visitors discover guidance without homepage orhiding/reopening. Keep currentpage steps,hide/show controls,16px steps anddesktopfit;avoid redundant intro whenheading sufficient.

```

## .ai/rules/lucid.md

```markdown
## Harbor black rail and light labeled navigation
Harbor passes rail=true to shared sidebar: pure-black 100px brand/icon rail, glowing Lucid, light lavender-gray labeled navigation and separate white conversation/guide panels with 12px gutters. Keep max1600 centered workspace. Collapsed navigation retains logo, icon links and existing authenticated/guest Agent desk action. Mobile uses a black brand header in expandable navigation. Other shared sidebar consumers stay unchanged.

## Single navigation replaces double rail
Latest owner choice supersedes rail=true layout: remove duplicate black icon rail. Harbor has one collapsible labeled navigation on lavender #eeebf4, with a compact black glowing-Luc id pill (Lucid wordmark), icons retained when collapsed. Preserve existing guest Agent desk POST and other pages' navigation.

## Reuse shared instruction panel for showroom guidance
Use x-lucid.instruction-panel for common Step-by-step instructions header, matching close tooltip, current-page subtitle, body and fine-print footer. Harbor uses Livewire hide/show; dashboard uses Alpine clientControlled mode. Dashboard opens guide by default and must fit1440x900/1280x870 without scrolling at normal text sizes; preserve natural narrow-screen scrolling.

```

## .ai/rules/tests.md

```markdown
## Agents own test execution including the full suite
Terry explicitly requires the agent to run tests rather than ask him to execute them. After affected tests pass, run php artisan test --compact yourself, resolve failures and report actual results, skips and blockers. This supersedes any generated guideline asking the user to run the complete suite. Keep runners sequential under the existing shared-database lock.

## Full local browser suite needs a larger test-process memory limit
On local PHP 8.5 the complete suite exhausts the default 128 MB during Playwright file-input MIME detection after accumulated tests. Run the full suite with php -d memory_limit=512M vendor/bin/pest --compact (same phpunit.xml coverage), keeping runners sequential. This is a test-process override, not a change to application or production memory settings. Report actual failures and opt-in skips.
```

## .ai/rules/views.md

```markdown
## Lucid proposal showroom follows approved rounded-panel designs
The owner approved lucid-design/lucid-homepage-aligned-concept.png and the numbered-guide support concept. Prioritize Upwork proposal visitors: Customer support includes replacements and human review; Ask your documents and Invoice review share the shell. Use glowing Lucid, gray gutters, black navigation, white panels, collapsible navigation/guide, and a rectangular microphone/up-arrow composer. Functionality and old labels are adjustable: flag needed behavior changes and distinguish working features from previews. This supersedes older homepage and four-sidebar-entry instructions.

## Approved support chrome across all showroom workspaces
Owner authorized applying current Harbor workspace styling across Ask your documents, Invoice review/replacement, policy list/detail, agent dashboard/queue/ticket review and customer status. Both demo-workspace and lucid.support-frame use showroom-support-unified: neutral #e7e8eb backdrop,max1600,black single sidebar,white toolbar/task and guide cards,12px gaps. Desktop collapse hides whole sidebar and Navigation button restores; mobile toggle retained. Preserve workflows,authorization,uploads,confirmations and homepage.

## Document chat is the approved document demo name
Owner chose Document chat to keep the shared sidebar label on one line without shrinking type or widening navigation. Use it consistently in navigation, page/toolbar titles and entry links. The document landing guide follows the plain numbered customer-support pattern with current control labels; keep file management/settings expandable and review their inner states separately.

## Document showroom follows upload and source-preview workflow
Owner prioritizes the intended client-facing UI over preserving legacy behavior; functionality may change to support the approved flow, with gaps reported and answer-quality/tone tuning tracked in Package 4. Document chat uses compact numbered source buttons opening the actual document title and supporting excerpt in the right panel, with Back to instructions and keyboard focus return; private uploads must not fabricate public policy links or page numbers. Match approved Harbor user lavender/assistant neutral bubbles and 16px corners. This supersedes bulky filename pills and the embedded above-transcript inspector; standalone preview remains separate.

```

## Consolidated discovery reminder

Covered by the current UI guideline and project workflow.

```markdown
## UI instruction discovery uses current sources
The AGENTS.md Start here section links the mandatory UI, verification and cleanup procedures. Follow the matching rule-index paths; historical UI instructions are archived outside .ai/rules and are not active decisions. UI review must distinguish intended owner-approved design from current implementation and verify rendered role-specific states before handoff. Keep unresolved bubble geometry and other current acceptance gaps in the latest readiness checkpoint; do not freeze them into an approved style.
```

## Final reconciliation: livewire-pages.md

```markdown
## Agent desk opens the dashboard
The header Agent desk link opens this dashboard. The Harbor walkthrough Open Agent desk button stays on the ticket queue. Needs attention opens the queue with filter awaiting, escalated, or failed. Do not put a View tickets button in the black summary panel, and do not hardcode the mockup counts.

## Queue type and table tones use existing tokens
Queue filters are text-sm font-semibold, the same size and weight as the selected sidebar row. The current pagination page uses the pale selection fill #eee7ff. The table outline and row dividers use border-zinc-100, and the header background is bg-zinc-50, with dark semibold headings. Ticket numbers stay harbor pine. Status pills stay rounded and pastel.

```

## Final reconciliation: views.md

```markdown
## Document chat sources and workflow
Document chat uses compact numbered source buttons to inspect the actual workspace document title, section and supporting excerpt in the right panel, with Back to instructions, Escape/focus return and reopening when hidden. Keep streamed and saved source controls consistent. Private files never acquire fabricated public policy links or page numbers; standalone preview remains a separate consumer. UI may drive authorized workflow changes; report functional gaps and keep answer-quality tuning in its recorded package. Bubble geometry must be resolved against the owner's current design review, not inferred from the implemented 16px radius.

```

## Source presentation superseded by document walkthrough

The owner requested end-of-answer references and a dialog instead of the sidebar.

```markdown
## Private document source presentation
Document chat uses compact numbered source controls for actual workspace document titles/sections/excerpts in the right panel. Preserve Back to instructions, Escape/focus return, reopening when hidden, and equivalent streamed/saved controls. Never invent public policy links or page numbers for private files; standalone preview remains separate. UI may drive authorized workflow changes, with gaps reported and answer tuning in its recorded package. Bubble shapes follow explicit role-specific owner decisions; existing CSS or a passing test does not establish approval.

```
