# Required UI review before handoff

Use this procedure before changing a Lucid screen and before calling it ready for Terry. Guidelines describe the work procedure; indexed rules hold current application decisions. Existing code, historical mockups and passing tests do not establish owner approval.

## Before editing

1. Identify the route, role and actual state being reviewed. Read the current checkpoint in [release-readiness](../../docs/release-readiness.md), the shared [UI rules](../rules/views.md) and [style rules](../rules/css.md), the applicable entries in the [rules index](../rules/index.md), and the [walkthrough skill](../skills/lucid-ui-walkthrough/SKILL.md). Read both shared rule files even for a CSS-only change; then load the matching page/component rules.
2. Compare against the current approved app by equivalent component role. Find the shared implementation before adding a separate style: `resources/views/components/lucid/`, `resources/css/showroom.css`, the inline Harbor chat in `resources/views/livewire/chat/widget.blade.php`, and the relevant embedded/standalone page. CSS and current code are implementation evidence, not a new design approval.
3. Resolve contradictory instructions using Terry's latest explicit decision and the current checkpoint. If the intended design is genuinely unresolved, record that specific choice for design review; continue independent work without choosing a new visual standard or repeatedly asking about settled choices. Historical instructions in docs/ui-instruction-history-2026-11-09.md are not active rules.

## Check the finished view yourself

- Inventory initial, loading, empty, error, saved, sent and expanded states, including tabs, accordions, dialogs and menus. Check both streamed and persisted chat output, repeated actions and the first interaction after refresh. Do not claim unseen states were reviewed.
- Compare page/section/dialog heading roles, fonts, colors, spacing, dividers, panel widths, action sizes, icons, wrapping and metadata. Measure the rendered result at desktop and phone widths; include the narrow-width regression where relevant. Preserve shared tokens and readable scrolling instead of shrinking type to force fit.
- Check the bottom of all three workspace panels. Fixed middle footers should share the sidebar divider baseline on desktop. For scrollable content without a footer, end with spacing and remove any trailing divider that looks like a footer.
- For conversations, compare **user and agent/assistant roles separately**: background and text colors, all corner radii, any tail or role-specific corner treatment, padding, alignment, width, links and citations. Rounded outer panels do not imply rounded message bubbles. An unresolved owner objection to message geometry stays open until checked against the intended design.
- Follow visible controls and verify the right-hand instructions describe what is actually available, using exact labels and plain business language. Compare source controls according to their purpose: public Harbor policies can open public articles; private document sources show their own workspace passages without invented policy URLs or page numbers.
- Check keyboard operation, visible focus, focus return, unclipped outlines, overflow and reading position. Hidden editors must remain inert and must not steal focus. Check natural reflow separately from focus/scroll jumps; preserve stable Livewire keys and visible-box polling.
- Verify local feedback on the first and repeated save, privacy boundaries, independent drafts and source restoration when touched. Navigation must not save/send; private drafts/notes must not appear in public status. Use controlled fictional fixtures where actions are needed; do not send replies, delete workspaces or upload owner files just to inspect a layout.

Use the skill for task-specific sequences and the indexed rules for exact role values. Do not duplicate those design values in this procedure. Read the current checkpoint for unresolved design choices. Use the latest readiness checkpoint to distinguish verified microphone behavior from pending refinements; deferred custom-toast work remains separate.

## Before handing back

Complete [cleanup](change-cleanup.md), then the applicable [verification schedule](testing-verification.md). For low-risk visual iteration, run focused checks, build when needed and inspect the rendered result before a review preview. Run the full Composer gate after view/batch approval, or earlier for risky/broad behavior changes; do not automatically run it before every preview. Code/classes and tests do not replace rendered inspection. For instructions-only changes, validate links, rule discovery and consistency; do not claim a fresh app pass.

Report briefly what changed, the routes/states/viewports actually inspected, what passed and any specific remaining decision or blocker. Terry reviews the intended design, not routine inconsistencies the agent could have found. Keep one current handoff in release-readiness; retain old evidence as history. Broader end-of-package convention, SEO/state-inventory, device/microphone and release checks remain separate gates.

## Documentation basis

- [Laravel Boost guidelines and project rules](https://laravel.com/framework/docs/13.x/boost): custom procedures in `.ai/guidelines`, application decisions recorded through Boost into `.ai/rules`, with a generated discovery index. Pass separate actual globs rather than a comma-joined string.
- [Official OpenAI AGENTS.md guidance](https://learn.chatgpt.com/docs/agent-configuration/agents-md): a concise project instruction entry point with explicit links to detailed guidance. Links require deliberate reading; they are not a guarantee of automatic recursive loading or perfect compliance.
