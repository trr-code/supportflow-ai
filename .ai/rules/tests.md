---
paths:
  - 'tests/**'
---

# Tests

## Test runners share a database and must not overlap
phpunit.xml uses tests/bootstrap.php to hold a process-lifetime file lock keyed by test database host/port/name, acquired before migrations. A second runner fails fast; finish the first run instead of launching overlapping Feature/Browser/Eval suites or disabling the lock. Keep normal runs sequential. For diagnostic warnings use --log-events-text=storage/logs/<name>.log; PAO 1.1.4 can count runner warnings without their details. Pest Browser 5.0.1 screenshot cleanup needs its optional folders prepared only when the screenshot root exists; remove the workaround only after a verified upstream fix.

## Run useful live AI smoke checks regardless of nominal API cost
The owner explicitly wants all verification that materially helps correctness and reliability. When normal tests skip OPENAI_SMOKE cases, assess whether they add coverage for the current change/release and run the useful live cases separately with fictional fixtures; do not silently treat skipped tests as verified. OpenAI credits or nominal cost are never a reason to omit needed smoke/eval/live checks. Do not rerun unchanged passing live cases without a verification reason; explain skips and retain relevant passing evidence.

## Verification follows the single project schedule
Follow .ai/guidelines/testing-verification.md: focused tests/rendered checks for low-risk review previews, full Composer validation after view/batch approval or earlier for risky/broad changes, and relevant AI/performance/release gates. The agent owns execution. A focused pass is not a full-suite pass. Preserve valid unchanged evidence, track deferred batch validation and report actual skips/blockers.

## Installed browser tooling overrides stale generic skill assumptions
This repository installs pestphp/pest-plugin-browser and Playwright and already has tests/Browser. Generic testing skill wording that says this project does not install browser tooling is stale; use the verified installed versions and project browser rules. Controlled browser events and fictional-audio real-provider tests are distinct from owner physical-microphone acceptance. Do not remove existing coverage or change dependencies to resolve this documentation mismatch.
