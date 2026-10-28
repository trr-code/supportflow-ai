---
paths:
  - 'tests/**'
---

# Tests

## Test runners share a database and must not overlap
phpunit.xml uses tests/bootstrap.php to hold a process-lifetime file lock keyed by test database host/port/name, acquired before migrations. A second runner fails fast; finish the first run instead of launching overlapping Feature/Browser/Eval suites or disabling the lock. Keep normal runs sequential. For diagnostic warnings use --log-events-text=storage/logs/<name>.log; PAO 1.1.4 can count runner warnings without their details. Pest Browser 5.0.1 screenshot cleanup needs its optional folders prepared only when the screenshot root exists; remove the workaround only after a verified upstream fix.

## Run useful live AI smoke checks regardless of nominal API cost
The owner explicitly wants all verification that materially helps correctness and reliability. When normal tests skip OPENAI_SMOKE cases, assess whether they add coverage for the current change/release and run the useful live cases separately with fictional fixtures; do not silently treat skipped tests as verified. OpenAI credits or nominal cost are never a reason to omit needed smoke/eval/live checks. Do not rerun unchanged passing live cases without a verification reason; explain skips and retain relevant passing evidence.
