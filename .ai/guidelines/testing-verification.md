# Verification during review and before validation

The agent owns checks, failure resolution and reporting. Do not ask Terry to run tests. This schedule supersedes the former full-suite-before-every-code-handoff requirement.

## Fast visual-review loop

For local copy, spacing, colors, icons, guide visibility and limited UI/control-state refinements:

1. Confirm the requested outcome against the current app/rules. Make a small coherent change and clean up replaced code.
2. Run the narrowest meaningful checks for affected behavior and failure modes. Reuse existing coverage for copy/style-only edits; do not write tests that merely repeat markup. Apply relevant quick formatting/static checks when PHP changes.
3. Build assets when needed and inspect actual desktop/phone states yourself. A test pass does not establish that the design matches Terry's request.
4. Hand back a **review preview** promptly: explain what changed, what was inspected/tested and that full-batch validation is pending. Do not automatically run the full suite before this preview.
5. Continue corrections in the same review batch with focused checks. Do not ask Terry to repeat permission for this already authorized preview workflow.

Visual approval is design feedback, not proof of functional correctness. Fix syntax/rendering failures and affected-test failures before presenting a usable preview. Never use the preview label to conceal known defects, data risk or failed checks.

## Full validation triggers

Run composer test once against the final view/batch when Terry accepts it or asks to finish, validate or move on from it. Also run it before an authorized commit, push, merge or deployment. Validation does not authorize those publishing actions. Track the open batch and pending gate in release-readiness; do not defer it indefinitely.

Run fuller verification earlier for authentication/authorization, privacy/data isolation, destructive actions, upload/storage/processing logic, database/schema/config/dependency changes, core business/AI behavior or broad cross-workflow behavior changes. Choose checks by actual behavior and risk, not filename; briefly explain an early full run. Shared visual styling can remain a preview if affected consumers are inspected/tested. Read-only display conditions and local button-state refinements normally use focused integration/browser coverage during review, then full-batch validation.

composer test retains config clearing, Pint check, Rector dry-run, PHPStan and the regular suite. Every stage must pass before calling the batch fully validated. Resolve failures without suppressions or weakened assertions. Rerun affected checks after a correction; repeat the full gate when a later substantive change invalidates its evidence. Do not repeat an unchanged passing full run for another screenshot, explanation or documentation edit.

## Additional checks

- AI behavior changes: run relevant real-AI evaluations/provider checks with fictional fixtures for changed prompts, models, grounding/retrieval, tools, answer controls or provider boundaries. Citation appearance or guide wording alone does not require repeating unchanged provider checks. Use composer test:evals and OPENAI_SMOKE=true checks as applicable. Keep existing egress approval scope and report actual blockers/skips.
- Performance changes: run relevant local measurements for changed query/processing/streaming/concurrency/runtime behavior. Match checks to risk; an existing bounded display predicate alone does not justify a full load ladder. Follow progressive smoke-first GET checks and stop on failures; streaming-chat timing is separate. Hosted load keeps its separate authorization/off-peak constraints.
- Release candidate: complete full Composer, relevant AI/performance and required manual/device/security/operational gates. Review approval/local tests do not establish CI or deployed readiness.
- Instructions/documentation only: validate references, discovery, consistency and diffs. Do not run the application suite or claim a fresh app pass for these changes.

Keep runners sequential under the shared-database lock. Composer owns test-only memory/timeout; do not change production settings for tests. Preserve valid unchanged evidence and report exclusions honestly. Do not adopt parallel runners, impact-analysis caches, dependencies or altered test scripts merely to speed this policy change.

Record the preview batch, focused evidence, pending full gate and last relevant full result in release-readiness. Cleanup remains required before every preview and validated handoff.

Documentation basis: [Pest filtering](https://github.com/pestphp/docs/blob/5.x/filtering-tests.md) supports focused development runs; [Codex project instructions](https://learn.chatgpt.com/docs/agent-configuration/agents-md) support persistent project guidance. The approval/batch schedule is Terry's project policy and engineering judgment, not framework-mandated timing.
