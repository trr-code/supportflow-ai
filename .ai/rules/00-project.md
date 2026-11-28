---
paths:
  - rector.php
  - composer.json
  - vite.config.js
  - '**'
  - 'compose*.yaml'
---

# Project-wide workflow

## Conservative Rector only; dry-run in CI
Keep Rector conservative: PHP sets from composer.json, Pest CODING_STYLE, skip toBeEmpty rewrites, Pest.php $this arrow functions, and new-without-parentheses. composer rector:check and GitHub Actions run process --dry-run only. Apply with composer rector locally. Do not apply Rector in CI. Do not set withCache('/tmp/rector')—this repo is developed on Windows.

## composer test includes Rector dry-run
composer test runs config:clear, Pint --test, Rector --dry-run, PHPStan, then Pest excluding stress and evals. rector:check is the read-only gate; rector applies changes locally. Do not fold evals or Stressless into test.

## Carry audit runtime evidence gaps through every fix package
For Lucid audit/fix work, read docs/system-design-audit.md and its Herd verification follow-up. Preserve valid code-review/test evidence; fill missing runtime evidence per package using Herd site info and fresh, site-scoped Insights with examples off. Record workflow, environment, observed result and remaining gaps before closing findings; reopen findings if evidence contradicts them. Herd does not capture test runs or prove Forge/CI, provider billing, backups, restore, mobile/accessibility or file-picker readiness. Finish with cross-workflow verification and an explicit unresolved-gap review. Check tool availability each chat and flag inaccessible tools/docs; the owner should not need to repeat this reminder. Do not expand package scope or bypass deletion, dependency, commit/deploy or production-load approvals.

## Verify answers against the official documentation relevant to the question
The owner wants documentation checks by default, without repeating the request. Before substantive technical/product guidance or implementation, consult current official documentation for the subject: OpenAI/Codex for agent behavior/setup, Herd for local tooling, installed-version Laravel/Livewire/package docs for application work, and the relevant primary authority otherwise. Reuse sufficient verified sources already in context. Cite the supporting source concisely and distinguish documented behavior, observed local evidence, engineering judgment and unverified claims. Explicitly flag inaccessible documentation or missing coverage; never imply documentation established something it did not. Persistent instructions improve continuity but do not guarantee every future agent will comply.

Before editing framework-dependent code, use Laravel Boost search-docs for the installed version and confirm the exact API against the installed package when needed. If applicable guidance cannot settle a proposed action, explain the gap and get Terry's decision before proceeding. Follow the change-cleanup guideline and record verified results, remaining gaps, and primary sources in the handoff.

## Use Conventional Commits for future commit messages
The owner adopted Conventional Commits 1.0.0 for this project going forward. Use lowercase type and a short meaningful lowercase scope where useful. Common types: feat, fix, docs, refactor, perf, test, build, ci, chore. Keep descriptions concise, imperative and without a trailing period; use ! and a BREAKING CHANGE footer for actual breaking changes. Follow the official Git prefixed-subject casing rule below. Scope is optional under the specification; do not invent one for changes with no useful single area. Preserve published history unless its rewrite is separately approved. This message-format preference does not authorize commits, pushes or deployment; obtain the owner's approval as already required.

## Current release status supersedes historical checkpoints
Read the top current checkpoint in docs/release-readiness.md and the newest package checkpoint in docs/system-design-audit.md before reporting status. Older tables and appended deployment notes are historical evidence, not current failures or pending approvals. Verify CI by exact SHA and the deployed SHA separately; local package completion does not imply CI/Forge verification of unpushed changes. The direct-main workflow and current Conventional Commits preference in .ai/rules override older .cursor git-workflow guidance; commits/push/deploy still require owner authorization.

## Print checkpoint additions in the chat
When updating audit or readiness checkpoints, tell the owner directly in the normal chat what was newly added, what passed and what remains; do not expect them to reread the document. Name and link the exact updated section, ideally its heading line. Keep the documents for durable continuity, and keep the user-facing change summary concise.

## End of UI package requires code conventions and existing-rule audits
Before closing the UI package, run full infer-conventions across the application and separately audit existing .ai/rules for obsolete, duplicate, conflicting and code-mismatched instructions; inference skips already-recorded conventions. Reconcile findings with the owner's current decisions and verify affected code/screens/tests before closure. Keep this gate distinct from the ongoing UI walkthrough, final SEO/structure gate and deferred custom-toast design.

## Instruction ownership and UI review
Keep essential always-required steps and links in AGENTS.md, reusable work procedures in .ai/guidelines, current application decisions in indexed .ai/rules, and task-specific checks in skills. Follow .ai/guidelines/ui-review.md before UI work and verify rendered states before handoff. Record new decisions through Boost; when authorized to consolidate old rules, preserve history outside active instructions, remove superseded entries, use separate path globs and regenerate the index. Do not append a competing current rule to an unresolved conflict or infer approval from existing code.

## Validate instruction discovery after reorganizing guidance
After instruction-only cleanup, check local links, valid separate frontmatter globs, index coverage and unresolved conflicts. Keep global project guidance in 00-project.md and operational/history guidance scoped to its actual documentation files. Boost owns new rule recording and index regeneration; preserve outside-block AGENTS.md entry points. Do not claim fresh app tests or design acceptance from documentation validation.

## Visual review previews precede full batch validation
Follow .ai/guidelines/testing-verification.md. For low-risk UI iteration, run focused checks and inspect rendered states, then return a clearly labelled review preview with the full gate pending. Run composer test once after the view/batch is approved for completion, before authorized commit/push/deploy, or earlier for risky/broad behavior changes. Never present a focused pass as full validation; keep affected failures, cleanup, relevant AI/performance checks and deferred-batch tracking explicit. This replaces the former full-suite-before-every-handoff rule.

## Microphone work uses official OpenAI documentation and owner reference behavior
Before microphone/dictation changes, search current official OpenAI documentation for the exact situation (startup, live transcription, Stop/finalization, resume, edits, Send, handoff and cleanup). Use Terry's observed ChatGPT dictation experience as the interaction target: visible incoming text, retained editable draft across Stop/restart and new speech appended safely, with Lucid colors. No spoken assistant output. Distinguish documented APIs from observed product behavior; never claim access to private ChatGPT internals or guaranteed identical latency. First empty-draft capture shows a waveform; Stop produces editable text, and resumed capture adds visible text above the waveform. This direction supersedes the earlier invisible-buffer-only preference; preserve no-duplicate/no-resurrected-sent-text safeguards.

## Begin the next repair pass with an app-wide instruction conflict sweep
Owner 2026-11-21 requires the next repair pass to start with an app-wide sweep of AGENTS.md, indexed rules, relevant skills/guidelines and current handoffs for contradictory or superseded instructions, including microphone behavior. Reconcile active rules with latest explicit owner decisions before implementation; preserve historical evidence clearly marked as superseded. Check all shared consumers rather than taking the first matching rule as sufficient. Record unresolved conflicts instead of guessing. Record the inspected scope and actual findings in the current handoff; historical checkpoints do not override settled owner decisions.

## Microphone conflict review includes all guidance and implementation clues
Owner expands the required pre-repair sweep beyond indexed rules: search all relevant Markdown/guidelines/skills/handoffs, applicable agent instructions, code comments, tests, configuration, handlers and shared consumers for mentions or clues affecting microphone behavior. Investigate every discovered inconsistency or suspicious assumption against latest owner direction and official documentation; do not dismiss it because it is outside rules. Separate current implementation, pending UX target and historical guidance. Preserve unrelated work and report unresolved choices; do not claim exhaustive coverage without a recorded search/inspection scope.

## Verify microphone requirements before proposing UI or navigation policy
For microphone decisions, first seek an applicable official OpenAI requirement. If none is found, explicitly tell Terry what remains undocumented and leave that decision for him; distinguish API mechanics, observed ChatGPT behavior and design suggestions. Do not present auto-stop on navigation or a cross-page 'Recording for...' banner as a requirement or industry standard without supporting evidence. Owner wants browsing anywhere in Lucid considered and tested; no special banner has been approved.

## Explicit recording persists across browsing and app focus changes
Owner 2026-11-21 explicitly chooses continued dictation while browsing policies, Customer support or other Lucid pages, and while another application has focus. Keep recording attached to its originating draft; returning shows accumulated speech and still-active recording until explicit Stop or a documented limit/failure. Never transfer or auto-send speech into another composer. Preserve drafts and late-event/duplicate safeguards. This supersedes navigation-auto-stop as the desired UX; verify the page-independent implementation against the current manual walkthrough. Owner observed this behavior in ChatGPT; do not misrepresent it as an official OpenAI mandate or claim universal browser support. No special cross-page banner is approved.

## Follow official Git prefixed-subject casing
Owner 2026-11-22 supersedes the capitalized-description preference: follow https://git-scm.com/docs/SubmittingPatches for prefixed subjects, e.g. fix(showroom): enable Ask Lucid preview. Keep type/scope lowercase and do not capitalize the description's first word merely because it begins the sentence; preserve proper nouns/acronyms. Use imperative wording and no final period. This adopts Git's own contribution convention for this project; Conventional Commits permits either case. Do not force-push published history for cosmetic corrections without separate approval.

## Keep Docker parity isolated from Herd and Forge
Owner approves a separate local Docker environment for Package 5 preparation, not a production runtime/dependency change. Always use --env-file .env.docker; preserve Herd .env, databases and deferred Package 6 artifacts. Build source into Linux images for measurement (no Windows source bind mount); default one worker/500 requests, development override one request/watch must not be benchmarked. Docker test database/volumes stay separate; never remove volumes or copy production data as routine cleanup. Versions/evidence/remaining work live in docs/performance.md and docs/release-readiness.md.
