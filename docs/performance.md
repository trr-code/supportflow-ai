# Performance

SupportFlow measures HTTP capacity with Pest Stressless only. There is no raw k6 suite. Chat streaming is a separate one-shot `POST /chat/stream` client, never a repeating Stressless GET loop.

## Local GET capacity (2026-09-19)

Machine: Windows 11, Intel Core i9-10850K (20 logical CPUs / 10 cores), 32 GB RAM, Herd PostgreSQL (`max_connections=100`).

Runtimes compared:

- Herd nginx FastCGI: `https://supportflow-ai.test`
- Octane/FrankenPHP: `http://127.0.0.1:8000`

Healthy gate used for the machine-level Octane ladder: p95 ≤ 500 ms, success ≥ 99.9%, failure < 0.1%. Stressless durations stayed under 60 s (`K6_NO_COOKIES_RESET=true`).

### Herd FastCGI vs four Octane workers

Identical 10 s GET ladders on `/up`, `/`, `/knowledge`, and `/knowledge/return-window`. Both runtimes stopped on confirmed RPS plateau, not an assumed concurrency-16 cap. Zero HTTP failures.

| Path | Herd peak RPS | Octane 4-worker peak RPS |
|---|---|---|
| `/up` | ~193 | ~3,022 |
| `/` | ~50 | ~304 |
| `/knowledge` | ~48 | ~244 |
| `/knowledge/return-window` | ~49 | ~282 |

Four Octane workers were a **worker-count ceiling**, not this machine’s maximum.

### Machine-level Octane maximum on `/`

Worker counts 4, 8, 12, 16, then 20. Concurrency ladder 4, 8, 16, 24, 32, 48, 64, 96, 128. 15 s plateaus, cookies persisted, warm-up after every restart.

**Maximum healthy `/` result: 770 successful RPS at 16 workers and concurrency 32, p95 47 ms, median 39 ms, TTFB p95 46 ms, 100% success (11,609/11,609).** CPU ~84% average / 85% max. RAM ~44%. FrankenPHP RSS ~365 MB. PostgreSQL 25/100 connections.

At that configuration, `/up` reached ~4,392 rps (p95 9 ms), `/knowledge` ~579 rps (p95 57 ms), and `/knowledge/return-window` ~708 rps (p95 50 ms).

20 workers collapsed (~85 rps) and concurrency 48 exceeded p95 500 ms. 24 workers failed to bind port 8000 after that collapse. RAM and `max_connections` were not the limit.

That 770 rps figure is about **2.5×** the four-worker `/` result (~304–309 rps).

Do **not** copy 16 workers onto Forge. Choose Forge workers from that server’s CPU/RAM and CareerForge contention.

## Post-Rector local Octane check

After Rector, `composer test` passed (223 tests, 1 skipped). Official `php artisan octane:start --server=frankenphp` on this Windows host now throws: FrankenPHP binaries are only available for Linux and macOS. On Windows, use WSL or Docker. The 770 rps figure above remains the last measured local Octane `/` maximum; it was not re-run after Rector because Laravel no longer downloads a native Windows binary.

## Windows FrankenPHP limitation

Laravel Octane documents Docker for Windows FrankenPHP. The local 770 rps run used a native Windows FrankenPHP binary plus Herd’s `cacert.pem` so OpenAI TLS would verify. That workaround is **not** the supported production path and must not be committed (binaries, `frankenphp-worker.php`, local CA paths, vendor signal guards).

Linux/Forge: `php artisan octane:frankenphp` (or `octane:start --server=frankenphp`) behind Nginx. Set `OCTANE_HTTPS=true` when Nginx terminates TLS. `config/octane.php` `max_execution_time` is 120.

## Chat streaming

`POST /chat/stream` is the sole write path: validation, `throttle:chat` (IP + session, 30/minute), fail-fast `Cache::lock('chat-stream:{demoSessionId}')`, `ChatService::ask()`, SSE `delta`/`done`/`error`/`stopped`. Livewire `send()` is UX-only. Stop stays unthrottled.

Historical local waves (2026-09-19): 14/14 real OpenAI turns on Herd and Octane, grounded, persisted, no exposed `CITES:`, no app 429s, first token ~1.6–2.2 s concurrent.

Current-path waves (2026-10-26, throttle 30, separate cookie jars, `POST /chat/stream` only): **7/7** `event: done` on Herd `https://supportflow-ai.test` (concurrency 1/2/4). No `event: error`, no `CITES:`, no app 429 or 409.

Before embed batch: first `event: delta` 3.45 s (n=1), 1.88–2.47 s (n=2), 4.00–8.14 s (n=4). After one `embedMany` per search: 1.47 s (n=1), 1.23–1.32 s (n=2), 1.31–1.47 s (n=4). Confirm after the dialog-focus change (chat path unchanged), 7/7 done: first n=1 2.56 s, warm n=1 **1.28 s**, n=2 **1.83–2.34 s**, n=4 **1.17–1.60 s**. Herd PHP-FPM overlaps concurrent streams; Forge’s 1 worker does not. Provider-dominated remainder. Do not claim Octane makes answers faster for visitors. Do not add Forge workers to chase concurrent TTFB.

## Response-time targets (2026-10-26)

These are showroom gates from measured current-path numbers on this 1 vCPU/961 MiB Forge VM and Herd. They are not a reason to raise Octane workers or skip grounding.

| Workflow | Metric | Target | Measured |
| --- | --- | --- | --- |
| Harbor chat, one visitor | Time to first `event: delta` | ≤ 2.0 s warm | Forge warm **1.37 s**; local warm **1.28 s** (first local this pass 2.56 s) |
| Harbor chat, two overlapping visitors | Slowest first `event: delta` | ≤ 5.0 s | Forge **1.80–4.58 s** |
| Harbor chat, four overlapping visitors | Slowest first `event: delta` | ≤ 8.0 s | Forge **1.36–6.38 s** |
| GET `/` on Forge | p95 at last-healthy 16 VU | ≤ 750 ms | **520 ms**, ~35 rps |
| GET `/up` | HTTP 200 | ≤ 300 ms | 200 after deploy |
| Replacement Confirm/Cancel | No OpenAI round-trip | Local commit only | Live Forge **HR-5A6717** / **Nothing was saved.** |

Ticket triage and suggested drafts stay queued (`wire:poll.5s.visible`). Preview chat uses the same SSE+embed path as Harbor; use the Harbor row until a separate Preview wave exists. Visible character pacing (two characters/~32 ms) is intentional demo UX and is not part of the SSE target.

Nielsen’s 0.1 s / 1 s bars are for UI chrome, not a grounded model call. First useful chat text is provider-dominated after one embed batch (~1.3–1.5 s). Concurrent chat on 1 worker is serialized (~prior `done` + 1.5–2 s). Keep those as the overlapping-visitor targets; do not add workers to shrink them.

## Forge staging (2026-09-19)

Historical GET/chat benches targeted `https://supportflow-ai-ou1b5gvy.on-forge.com` on the shared CareerForge VM (1 vCPU, 961 MiB), Octane/FrankenPHP **1 worker** on `127.0.0.1:8000` behind Nginx TLS. Live staging is now `https://supportflow-ai-lqojsjr5.on-forge.com` with **1 worker** on `127.0.0.1:8001`. GET benches use the HTTPS URL, so they do not depend on that loopback port. Port **8001** is permanent. Scheduler was not paused for the 19 Sep run. GET benches used Pest Stressless only (`K6_NO_COOKIES_RESET=true`, `STRESS_MAX_CONCURRENCY=16`). Chat used one-shot `POST /chat/stream` with separate cookie jars; `throttle:chat` was 10/minute on that date. Live config is now 30/minute (`supportflow.rate_limits.chat_per_minute`).

Smoke, load, stress, stability, and capacity all passed with **zero HTTP failures**. Capacity last-healthy concurrency was 16 on `/`, `/knowledge`, and `/knowledge/return-window` (the 16-VU safety rail, not a local-style p95 gate). `/` peaked around **39 rps** (concurrency 12–16, p95 362–465 ms). `/knowledge` p95 rose above 1 s at concurrency 12. That is not the local 770 rps, 47 ms p95 result and must not be treated as a reason to raise Forge workers.

Browser checks: `/`, `/knowledge`, `/knowledge/return-window`, agent dashboard/tickets, mobile-width home, SSE chat with citations and a persisted follow-up, no exposed `CITES:`. Chat waves 1/2/4: 7/7 `event: done`, no `event: error`, no `CITES:`, no app 429s.

## Forge staging (2026-10-26)

Live hostname `https://supportflow-ai-lqojsjr5.on-forge.com`, commit `2d6943d` (GET ladder and pre-fix chat TTFB were on `d93755f`), Octane **1 worker** on `127.0.0.1:8001`. Weekday. CareerForge `worker-987181` was running for GET/TTFB, then **STOPPED** at 22:10Z for a reversible pause. `K6_NO_COOKIES_RESET=true`, `STRESS_MAX_CONCURRENCY=16`. Chat throttle 30.

GET Stressless: smoke 4/4, load 3/3, stress 6/6, stability 3/3, all **0 failed**. Capacity first pass marked `/` last-healthy **2** after 1 failed request at concurrency 4 (following the long GET session). Cooldown rerun: last-healthy **16** on `/`, `/knowledge`, and `/knowledge/return-window`, **0 failed**. `/` at concurrency 16 ~35 rps, p95 520 ms. `/knowledge` p95 rose above 1 s at concurrency 12 on some plateaus (1.75 s rerun). That is not the local 770 rps result and must not be treated as a reason to raise Forge workers.

Chat TTFB waves 1/2/4: **7/7** `event: done` both before and after embed batch, no `event: error`, no `CITES:`, no app 429 or 409. Pre-fix first `event: delta`: 2.89 s (n=1), 2.49–5.04 s (n=2), 2.22–9.65 s (n=4); the 1-worker process serializes concurrent streams (2.2 / 4.8 / 7.3 / 9.7 s tracks the previous stream’s `done`). After `2d6943d` (20:22:54Z): warm n=1 **1.37 s** (first request after Octane reload was 3.53 s), n=2 **1.80–4.58 s**, n=4 **1.36–6.38 s** (1.36 / 3.24 / 4.79 / 6.38 still serialized).

Octane default is one worker per CPU; `--workers` may exceed that. Live is still `--workers=1`. CareerForge pause 2026-10-26 22:10Z (`supervisorctl stop worker-987181:*`, not deleted):

| | Before (worker RUNNING) | After (STOPPED) |
| --- | --- | --- |
| MemAvailable | 349 MiB | **361 MiB** (+12) |
| Swap used | 269 MiB | **203 MiB** (−66) |
| AnonPages | 198 MiB | 185 MiB |
| Load | 0.08 | 0.13 |
| CareerForge RSS | 46 MB, CPU 0.1% | gone |
| FrankenPHP RSS | 127 MB | 127 MB |
| SupportFlow queue RSS | 80 MB | 80 MB |
| GET `/up` TTFB | 264 ms, HTTP 200 | 248 ms, HTTP 200 |
| GET `/` TTFB | 255 ms, HTTP 200 | 258 ms, HTTP 200 |

CareerForge’s RSS was mostly in swap, so the pause did not free a hot 46 MB for a new worker. Idle HTTP is unchanged.

### `--workers=2` trial (2026-10-26 22:19Z), reverted

Forge PUT on `daemon-1104270` only (`numprocs=1`). CareerForge stayed STOPPED. Same-session 1-worker baseline, then 2-worker, then revert. Chat 7/7 `event: done` both sides, no `event: error`, no `CITES:`, no 429/409. GET smoke 4/4 and load 3/3 **0 failed** both sides.

| | `--workers=1` | `--workers=2` |
| --- | --- | --- |
| Chat n=1 first delta | 2.70 s | 1.79 s |
| Chat n=2 first delta | 1.44–**3.07 s** (serialized) | 1.46–**1.54 s** (overlap) |
| Chat n=4 first delta | 1.86–**6.92 s** (1.86/3.25/5.08/6.92) | 1.60–**3.29 s** (two pairs) |
| GET `/` conc 4 × 15 s | 39.2 rps, p95 **121 ms** | 32.9 rps, p95 **187 ms** |
| GET `/knowledge` conc 4 | 21.9 rps, p95 262 ms | 22.2 rps, p95 213 ms |
| Idle FrankenPHP RSS | 127 MB | 142 MB |
| Load FrankenPHP RSS | 122 MB | **195 MB** |
| After-load MemAvailable | 362 MiB | **283 MiB** |
| Active swap-out (`vmstat so`) | **0** every sample | Chat n=4: 1 s at 264 KB/s. GET load: 1 s at **4456 KB/s**, swpd 133808→138344 |
| Active swap-in (`si`) | bursts 4–220 KB/s (cold pages) | bursts plus 372 KB/s after the so spike |
| CPU during GET load | id often 0–5%, us 80–90% | id 0%, us 75–92% |

Reverted at 22:21Z: `--workers=1`, `/up` 200, `numprocs=1`. Idle after revert: si=0 so=0, MemAvailable 338 MiB, swap used 133 MiB. Two workers overlap OpenAI I/O (n=2 wall 1.90 s vs 3.39 s) but on this 1 vCPU/1 GB box GET `/` slows and GET load **swap-out**s. Do not copy local 16. Do not raise Supervisor process count.

GET flake: first `/` capacity conc 4 had **1** k6 `http_req_failed` after the long weekday ladder. SupportFlow nginx `access_log off`; error log empty. A later `GET /` conc 4 × 15 s recorded **503/503 HTTP 200**. Treat the original fail as transport-level unless origin access_log is on.

## How to rerun GET tests

From a checkout, with Stressless excluded from `composer test`:

```bash
STRESS=true STRESS_URL=https://supportflow-ai.test composer test:stress:smoke
```

Forge staging is off-peak only: `https://supportflow-ai-lqojsjr5.on-forge.com`. Start with smoke. Stop if it fails. GET `/up`, `/`, `/knowledge`, and `/knowledge/return-window` only.

Pest Evals are local-only. Run `composer test:evals` (`vendor/bin/pest --evals --group=evals`). They call live OpenAI through ChatService, TicketIntakeService, and SuggestedReplyService against KnowledgeSeeder. Do not set `PEST_EVALS=1` in CI. `composer test` and GitHub Actions exclude group `evals`. Keep `OPENAI_SMOKE` until you decide the triage evals replace it. Rector `--dry-run` is part of `composer test` and GitHub Actions; applying Rector stays local (`composer rector`).

## CI and Forge (2026-09-19)

Pushed `500e465` to `origin/main`. GitHub Actions Tests passed. Forge Octane is serving staging; GET and chat benches above ran against that origin. See [forge.md](forge.md).

