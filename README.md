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
5. Run `composer run dev` and a queue worker: `php artisan queue:work --queue=ai,default --timeout=90`.

Open the home page, ask the knowledge assistant, or submit a demo ticket. Use **Open Agent Dashboard** for the review workspace—there is no public login form.

Do not enter real personal, order, or payment information.

## Tests

```bash
composer test
```

CI runs Pint, Larastan, and Pest on PHP 8.5 with `pgvector/pgvector:pg17`.

## Deploy

Forge notes live in [docs/forge.md](docs/forge.md). Production URL, screenshots, and k6 results will be added after the live site exists.

## License

MIT. See [LICENSE](LICENSE) and [CONTRIBUTING.md](CONTRIBUTING.md).
