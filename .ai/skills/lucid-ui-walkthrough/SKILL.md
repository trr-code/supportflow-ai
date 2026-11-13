---
name: lucid-ui-walkthrough
description: Review Lucid screens with Terry against current project decisions, shared components and rendered states. Use for screen acceptance and consistency reviews.
---

# Lucid UI walkthrough

Read [.ai/guidelines/ui-review.md](../../guidelines/ui-review.md), the matching indexed rules and the current [release-readiness checkpoint](../../../docs/release-readiness.md) first. Resume its next route/state; never take a fixed next view from this skill or a historical checkpoint. Terry is the owner.

This skill holds task-specific regression sequences. The guideline owns the overall review procedure; indexed rules own exact design values. Do not copy a second set of sizes/colors/radii here or treat the current implementation as approval.

## Choose the relevant sequence

- Conversations: compare user and assistant roles, colors, all corners/tails, spacing and source controls across initial, streaming and persisted output. Check source selection, Escape/close focus and the appropriate panel or dialog. Document chat uses a source dialog; Harbor retains its source panel. Harbor's public policy links and private document passages are different source contexts. Never invent public links for uploads.
- Documents: review visible own-file upload controls, filenames/readiness, upload/error states, optional Answer settings and Privacy & access, repeated feedback, return-code copy/blocked fallback, New chat and Back to document choices. Preserve files and drafts according to the actual confirmation. Use fictional fixtures; do not claim real file-picker or microphone verification from automated fakes.
- Ticket replies: retain independent suggested/manual drafts. Test the first mode switch after refresh, repeated switches, partly clipped and centered actions, long drafts and desktop/phone/scaled layouts. Compare window and ancestor scroll positions; distinguish natural reflow from unwanted focus/anchoring jumps. Hidden editors must be inert. Do not introduce layout-resize feedback loops, animation or delayed server redraws to hide a jump.
- Ticket Details/Activity: clicking each opens its current focused guide at the top. Save details does not send a reply; status changes apply separately. Activity shows business-facing event labels/actors/times, starts with eight recent entries and appends older entries in batches of eight. Test tied timestamps, stable unique keys, first/repeated expansion, keyboard reading position, completion, collapse and retained state across tools tabs. Keep diagnostics internal and never restore a duplicate modal.
- Reply history: distinguish empty, customer-only/unsent, sent and overflow states. Start with six recent approved public messages, expand six older at a time and allow return to recent messages. Check thirty-message repeated expansion, chronological order, reachable top/bottom collapse controls, guide wording and retained tab state. Exclude private/unapproved content; finish with Review reply.
- Internal notes: check the shared growing composer, entry focus, keyboard save, private feedback and no resize corner. Notes expand inline four older at a time with return to recent notes; test repeated loads, collapse, retained state and isolation from public replies. Editor checks alone do not establish overflow acceptance.
- Feedback: test first/repeated save, expiration, hover/focus/hidden-tab timing and unrelated polling/tab updates. Use the shared reserved feedback region. Permanent sent/confirmed state and actionable warnings/results must remain available. Queue cleanup results are dismissible with focus returned to Clear inactive demo data; cancellation and reappearance must preserve layout.
- Clipboard/privacy: inspect both ticket-status copy controls and document return-code controls. Test repeated success, blocked-copy/selectable fallback and polling. Real browser clipboard permissions remain a separate hands-on check. Never imply hiding a return code saved it or discarded it.
- Policy round trips: inspect both numbered citations and exact Open policy links, category retention, Back to all policies and contextual return. Restore whitelisted tab-local unsent drafts only within the scoped user/ticket/reply version or visitor conversation and expiry. Validate restored citations against the current conversation. Policy navigation must never save/send. Public browsing remains published, seeded and workspace-null. Check full-width separators independently of the reading column and correct agent/customer navigation context.
- Guides and structure: review every touched tab/disclosure/dialog and meaningful empty/loading/error/sent state. Check headings/metadata, readable role-based type, actual control labels, clipping, scroll arrows and keyboard behavior. Keep guides explanatory and tied to the visible task. Existing tests/classes alone do not prove rendered consistency.

## Keep the walkthrough moving

Use focused checks and rendered inspection for low-risk review previews. Run the full Composer gate when the view/batch is approved for completion, not automatically before each small visual correction. Follow .ai/guidelines/testing-verification.md for early full-check triggers and clear preview versus validated reporting.

Report what changed, which rendered states/viewports were inspected, what passed and one next action. Owner review concerns the intended design; it must not replace the agent's routine consistency check. Preserve accepted screens unless the current task or a confirmed defect requires revisiting them.

Use fictional controlled fixtures for authorized action checks. Never send replies, delete real workspaces or create repeated tickets merely to inspect presentation. Label and record any owner-authorized preview rows for scoped cleanup. Retain meaningful tests and remove temporary diagnostics or abandoned fixes.

## Separate open gates

- Read the current checkpoint for exact status. The known microphone lifecycle defect, real-device/spoken/assistive checks and custom-toast design remain open until actually resolved and verified.
- Harbor answer/action positioning and the older /tools, /demo/replacement and standalone /tickets/create callers require the recorded later review. Shared components are not dead code simply because a showroom embeds them; retire routes only after auditing/migrating callers and preserving authorization/forms/tests.
- Before UI-package closure, complete the full route/state/SEO/semantic consistency inventory, full code-convention inference and a separate existing-rule audit. This scoped instruction cleanup does not complete backend convention inference or release acceptance.
