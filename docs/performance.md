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

`POST /chat/stream` is the sole write path: validation, `throttle:chat` (IP + session, 10/minute), fail-fast `Cache::lock('chat-stream:{demoSessionId}')`, `ChatService::ask()`, SSE `delta`/`done`/`error`/`stopped`. Livewire `send()` is UX-only. Stop stays unthrottled.

Local one-shot concurrent waves at 1, then 2, then 4 demo sessions on both Herd and Octane: 14/14 real OpenAI turns succeeded, grounded, persisted, no exposed `CITES:`, no app 429s. Time to first token is provider-dominated (~1.6–2.2 s concurrent). Do not claim Octane makes answers faster for visitors.

## How to rerun GET tests

From a checkout, with Stressless excluded from `composer test`:

```bash
STRESS=true STRESS_URL=https://supportflow-ai.test composer test:stress:smoke
```

Forge staging is off-peak only: `https://supportflow-ai-ou1b5gvy.on-forge.com`. Start with smoke. Stop if it fails. GET `/up`, `/`, `/knowledge`, and `/knowledge/return-window` only.

Pest Evals are deferred.
