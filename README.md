# SupportFlow AI

Harbor & Co/SupportFlow is a Laravel 13 portfolio demo of a support copilot: OpenAI triage, PostgreSQL/pgvector retrieval, and human-approved replies.

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
5. Run `composer run dev` and a queue worker: `php artisan queue:work --queue=ai,default --timeout=120`.

Open the home page, ask the knowledge assistant, or submit a demo ticket. Use **Open Agent Dashboard** for the review workspace—there is no public login form.

Do not enter real personal, order, or payment information.

## Tests

```bash
composer test
```

CI runs Pint, Larastan, and Pest on PHP 8.5 with `pgvector/pgvector:pg17`. HTTP Stressless tests and paid Pest Evals are **not** in that quality gate.

Local paid Evals (live OpenAI, seeded Harbor catalog):

```bash
composer test:evals
```

After those pass, use the [manual regression checklist](docs/manual-regression.md) before any deploy. Do not ship from this change set.

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

Forge staging is [https://supportflow-ai-ou1b5gvy.on-forge.com](https://supportflow-ai-ou1b5gvy.on-forge.com). It shares a VM with CareerForge. Run the same progressive Stressless sequence **off-peak** against that URL (`$env:STRESS_URL = "https://supportflow-ai-ou1b5gvy.on-forge.com"`). Start with smoke and stop if it fails. Load, stress, stability, and capacity may follow at `STRESS_MAX_CONCURRENCY=16`. Do not treat this as permission to load-test an unknown production system.

Ad-hoc (no assertions): `./vendor/bin/pest stress supportflow-ai.test/up --concurrency=2 --duration=5`.

## Deploy

Forge staging notes live in [docs/forge.md](docs/forge.md). Staging URL: [https://supportflow-ai-ou1b5gvy.on-forge.com](https://supportflow-ai-ou1b5gvy.on-forge.com).

## License

MIT. See [LICENSE](LICENSE) and [CONTRIBUTING.md](CONTRIBUTING.md).
