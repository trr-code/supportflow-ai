# SupportFlow AI

Harbor Outfitters/SupportFlow is a Laravel 13 portfolio demo of a support copilot: OpenAI triage, PostgreSQL/pgvector retrieval, and human-approved replies.

Nothing reaches the customer until a human support agent sends it.

## Stack

- PHP 8.5
- Laravel 13/Livewire 4/Flux
- PostgreSQL/pgvector
- `laravel/ai` with live OpenAI models—not canned scripts

## Try it locally

1. Copy `.env.example` to `.env` and set `APP_URL`, PostgreSQL, and `OPENAI_API_KEY`.
2. Enable `vector` in your database (`CREATE EXTENSION vector`). See [docs/pgvector.md](docs/pgvector.md).
3. Run `composer setup` (or `composer install`, `php artisan key:generate`, `php artisan migrate`, `npm install`, `npm run build`).
4. Seed once: `php artisan db:seed`.
5. Run `composer run dev` for Vite and a queue listener for `ai,default`. Laravel Herd already serves the site.

Open the home page, ask the knowledge assistant, or submit a demo ticket. Use **Agent desk** in the sidebar for the review workspace—there is no public login form.

Do not enter real personal, order, or payment information.

## Docker environment for Package Five

Herd remains available. The separate Docker stack runs Nginx, Octane/FrankenPHP, PostgreSQL/pgvector, a database queue worker and the scheduler. Application code and dependencies are built into Linux images rather than mounted from Windows; database/uploads/cache use isolated named volumes. Only `127.0.0.1:8088` is published. No production database or uploads are copied.

The ignored `.env.docker` requires its own `APP_KEY`, a random `LUCID_DB_PASSWORD`, and your local `OPENAI_API_KEY`. `.env.docker.example` lists the inputs. Never copy production credentials or commit this file. Always pass `--env-file .env.docker`; do not substitute the Herd `.env`.

```bash
docker compose --env-file .env.docker build app postgres
docker compose --env-file .env.docker up -d
# Fresh Docker database only: seed once (real OpenAI embeddings).
docker compose --env-file .env.docker exec app php artisan db:seed --no-interaction
docker compose --env-file .env.docker ps
```

Open `http://localhost:8088`. `/up` requires both the database and a fresh worker heartbeat. Normal startup runs idempotent migrations after PostgreSQL becomes healthy, then starts the app, queue and scheduler; Nginx waits for app health. Seeding is deliberately not part of startup.

Use the default configuration for stable measurements (one Octane worker, 500 requests per worker). After source/dependency changes, rebuild and recreate the stack. For automatic rebuilds during editing, opt into the development override (one request per worker); do not benchmark that mode:

```bash
docker compose --env-file .env.docker -f compose.yaml -f compose.development.yaml up --build --watch
```

The existing Herd/Vite refresh workflow is unchanged. Stop Docker without deleting its data using `docker compose --env-file .env.docker down`. Never add `--volumes` unless deliberately discarding this local database and uploads. Runtime versions, verification evidence and known differences from Forge are recorded in [performance notes](docs/performance.md) and the current [handoff](docs/release-readiness.md).

To enable the local Redis chat limiter, run `docker compose --env-file .env.docker -f compose.yaml -f compose.redis-limiter.yaml up -d --build --wait`. The override uses Redis for Harbor and Ask Lucid chat sends. Livewire and other limiters retain the default store. Restore the default with `docker compose --env-file .env.docker up -d --wait`, then stop and remove only the Redis limiter container with `docker compose --env-file .env.docker -f compose.yaml -f compose.redis-limiter.yaml rm -sf limiter-redis`.

The optional Linux test runner has a separate PostgreSQL database/volume and derives its PHPUnit connection settings inside the image without modifying the host test configuration:

```bash
docker compose --env-file .env.docker build test
docker compose --env-file .env.docker run --rm test
```

This runs formatting, static analysis and the existing regular Pest suite. Paid evals and Stressless remain separate. Do not overlap test runners. The Docker test database is never the application database or Herd's testing database.

Official basis: [Laravel Octane Docker](https://laravel.com/docs/13.x/octane#frankenphp-via-docker), [FrankenPHP images](https://frankenphp.dev/docs/docker/), [Compose startup order](https://docs.docker.com/compose/how-tos/startup-order/), [Compose Watch](https://docs.docker.com/compose/how-tos/file-watch/), and [Docker WSL guidance](https://docs.docker.com/desktop/features/wsl/best-practices/).

## Tests

```bash
composer test
```

CI runs Pint, the configured Blade formatter, Rector (`--dry-run`), Larastan, and Pest on PHP 8.5 with `pgvector/pgvector:pg17`. HTTP Stressless tests and paid Pest Evals are **not** in that quality gate. Apply Rector locally with `composer rector` after a dry-run; never apply it in CI.

Use `composer lint` to format ordinary PHP with Pint and Blade templates with Prettier, or `npm run format:blade` for templates alone. `composer lint:check` checks both. Install the locked Node development dependencies with `npm ci` first. Configure any editor Blade format-on-save integration to use this project's Prettier configuration; do not run a second Blade formatter or enable Pint's separate Blade rule. The Blade/PHP plugins handle embedded PHP and the Tailwind plugin reads `resources/css/app.css`; email templates are excluded. Standalone JavaScript/CSS files are outside this template-only command.

Local paid Evals (live OpenAI, seeded Harbor catalog):

```bash
composer test:evals
```

Release status for Stages 0–7 and P0–P3 lives in the [release-readiness checklist](docs/release-readiness.md). After `composer test` and local `composer test:evals`, use the [manual regression checklist](docs/manual-regression.md) for click-paths. A local pass is not Forge verification. A Forge deploy is not readiness to show clients.

### HTTP performance (Pest Stressless)

Read-only GET checks against a live URL. They use Pest Stressless (k6 under the hood). There is no separate `k6/` suite.

Do **not** stress-test ticket create, chat send, dictation, or regenerate—those call OpenAI and trip demo caps.

`GET /up` is isolated server health. `/`, `/knowledge`, and `/knowledge/return-window` are separate representative routes. Capacity discovery climbs constant-concurrency plateaus per route until the first unhealthy plateau, then records the last healthy level and the first failing level. That approximates Grafana-style breakpoint testing; Stressless cannot stage arrival-rate or report p99.

Local (Herd already serving):

```powershell
$env:STRESS = "true"
$env:STRESS_URL = "https://supportflow-ai.test"
# Approved safety rail—not a claimed capacity:
$env:STRESS_MAX_CONCURRENCY = "16"
# Pest Stress tests persist k6 cookies across iterations (returning visitors).
# For ad-hoc `pest stress`, also set:
# $env:K6_NO_COOKIES_RESET = "true"
composer test:stress:smoke
# Stop if smoke fails. Then:
composer test:stress:load
composer test:stress:stress
composer test:stress:stability
composer test:stress:capacity
```

`composer test:stress` runs smoke, load, stress, and stability. Capacity is a separate command.

For a latency-bounded capacity run, set `STRESS_CAPACITY_P95_MS` to the chosen positive millisecond budget before running `composer test:stress:capacity`. Each route stops at its first plateau with a request failure or p95 above the budget. Leaving the variable unset measures availability. Label the two results accordingly.

Forge staging is [https://supportflow-ai-lqojsjr5.on-forge.com](https://supportflow-ai-lqojsjr5.on-forge.com). It shares a VM with CareerForge. Run the same progressive Stressless sequence **off-peak** against that URL (`$env:STRESS_URL = "https://supportflow-ai-lqojsjr5.on-forge.com"`). Start with smoke and stop if it fails. Load, stress, stability, and capacity may follow at `STRESS_MAX_CONCURRENCY=16`. Do not treat this as permission to load-test an unknown production system.

Ad-hoc (no assertions): `./vendor/bin/pest stress supportflow-ai.test/up --concurrency=2 --duration=5`.

## Deploy

Forge staging notes live in [docs/forge.md](docs/forge.md). Staging URL: [https://supportflow-ai-lqojsjr5.on-forge.com](https://supportflow-ai-lqojsjr5.on-forge.com). That site is non-ZDD Octane on port 8001. CareerForge is unused: do not shrink Forge checks for it; if `worker-987181` contends, stop that worker. Do not delete CareerForge or shared Lucid resources without a separate approval.

## License

MIT. See [LICENSE](LICENSE) and [CONTRIBUTING.md](CONTRIBUTING.md).
