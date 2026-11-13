---
paths:
  - rector.php
  - composer.json
  - vite.config.js
  - "**"
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

## Use Conventional Commits for future commit messages
The owner adopted Conventional Commits 1.0.0 for this project going forward. Use lowercase type and a short meaningful lowercase scope where useful: fix(security): prevent unsafe uploads. Common types: feat, fix, docs, refactor, perf, test, build, ci, chore. Keep descriptions concise, imperative and without a trailing period; use ! and a BREAKING CHANGE footer for actual breaking changes. Scope is optional under the specification; do not invent one for changes with no useful single area. Preserve existing history. This message-format preference does not authorize commits, pushes or deployment; obtain the owner's approval as already required.

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
