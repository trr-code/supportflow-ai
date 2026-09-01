# Laravel Forge

SupportFlow AI is a single-tenant Laravel 13 app. Use Forge (not Laravel Cloud).

## PostgreSQL + pgvector

See [pgvector.md](pgvector.md). Install `postgresql-XX-pgvector` and enable `CREATE EXTENSION vector` **before** migrating. SupportFlow AI stores embeddings as native `vector(1536)` and retrieves with `whereVectorSimilarTo`.

## Environment

Set at least:

- `APP_NAME=SupportFlow AI`
- `APP_URL` (HTTPS site URL)
- `DB_*` PostgreSQL
- `QUEUE_CONNECTION=database`
- `CACHE_STORE=database`
- `OPENAI_API_KEY`
- `OPENAI_TRIAGE_MODEL=gpt-5.6-luna`
- `OPENAI_REPLY_MODEL=gpt-5.6-terra`
- `OPENAI_CHAT_MODEL=gpt-5.6-luna`
- `OPENAI_EMBEDDINGS_MODEL=text-embedding-3-small`
- `DEMO_STALE_MINUTES=45`
- `SUPPORTFLOW_MIN_SIMILARITY=0.45`

Do **not** publish a demo-agent password. Evaluators use **Open Agent Dashboard** (`POST /demo/enter-agent`). Credential screens (`/login`, `/register`, password reset, passkeys, 2FA) return 404. Guests who hit agent URLs are sent to the demo home. HTTPS is required for microphone dictation.

## Queue worker

Create a Forge daemon (timeout ≥ 90s):

```bash
php artisan queue:work --queue=ai,default --sleep=1 --tries=3 --timeout=90
```

## Scheduler

Forge scheduled job every minute:

```bash
php artisan schedule:run
```

The app schedule:

- `demo:prune-stale` every 15 minutes (idle visitor data only; seeded tickets stay)
- `queue:prune-failed` daily

There is **no** hourly global wipe.

## Deploy script

Include:

```bash
$FORGE_PHP artisan migrate --force
$FORGE_PHP artisan queue:restart
```

After first deploy: `php artisan db:seed --force` once to create `demo_agent`, the Harbor & Co knowledge base, and showcase tickets.

## Production reset

- **Reset stale demo data** in the agent queue UI (and `php artisan demo:prune-stale`) is safe for `demo_agent`.
- `php artisan demo:reset --force` is an owner/CLI escape hatch. It is **not** exposed in the demo-agent UI.

## Logs

Use the Forge log viewer. Do not enable Telescope or Horizon on a public demo.
