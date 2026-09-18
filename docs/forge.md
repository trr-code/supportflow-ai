# Laravel Forge

SupportFlow AI is a single-tenant Laravel 13 app. Use Forge (not Laravel Cloud).

GitHub: [trr-code/supportflow-ai](https://github.com/trr-code/supportflow-ai). Deploy branch `main`.

This Forge site is **staging**, not production: [https://supportflow-ai-ou1b5gvy.on-forge.com](https://supportflow-ai-ou1b5gvy.on-forge.com). Set `APP_URL` to that HTTPS origin.

## Site checklist (existing CareerForge server)

1. Install **PHP 8.5** on the server if it is not already present. Select PHP 8.5 for this site and for the queue worker.
2. Create a new site pointed at `trr-code/supportflow-ai`, branch `main`.
3. Create logical database **`supportflow_ai`** on the existing PostgreSQL instance. Do not put SupportFlow tables in CareerForge’s database.
4. Install `postgresql-XX-pgvector` if needed (server-wide). Then in `supportflow_ai` only: `CREATE EXTENSION vector;`
5. Set environment variables below. Prefer `SESSION_ENCRYPT=true` in production.
6. Paste the deploy script, enable SSL, and deploy.
7. Queue worker: `--queue=ai,default --timeout=120` using PHP 8.5. Edit the Forge daemon command itself; `$RESTART_QUEUES()` does not change it.
8. Scheduler: `php artisan schedule:run` every minute.
9. After first deploy: `php artisan db:seed --force` once.
10. Confirm `/up` over HTTPS. Return screenshots of the site URL, PHP version, worker, scheduler, database list, `/up`, and first-deploy logs.

Shared ~1 GB VM: queue storms and Stressless compete with CareerForge. Run progressive Pest Stressless **off-peak** against this known staging URL only. That is not permission to load-test an unknown production system.

From a local checkout, with `STRESS=true`, `STRESS_URL=https://supportflow-ai-ou1b5gvy.on-forge.com`, and `STRESS_MAX_CONCURRENCY=16`:

1. Start with `composer test:stress:smoke`. Stop if it fails.
2. If smoke passes, you may run load, stress, stability, then capacity at that 16-VU safety rail.
3. GET `/up`, `/`, `/knowledge`, and `/knowledge/return-window` only. Never hit ticket create, chat send, dictation, or regenerate.

## PostgreSQL + pgvector

See [pgvector.md](pgvector.md). Install `postgresql-XX-pgvector` and enable `CREATE EXTENSION vector` **before** migrating. SupportFlow AI stores embeddings as native `vector(1536)` and retrieves with `whereVectorSimilarTo`.

## Environment

Set at least:

- `APP_NAME=SupportFlow AI`
- `APP_URL=https://supportflow-ai-ou1b5gvy.on-forge.com`
- `DB_*` PostgreSQL
- `QUEUE_CONNECTION=database`
- `CACHE_STORE=database`
- `OPENAI_API_KEY`
- `OPENAI_TRIAGE_MODEL=gpt-5.6-luna`
- `OPENAI_REPLY_MODEL=gpt-5.6-terra`
- `OPENAI_CHAT_MODEL=gpt-5.6-luna`
- `OPENAI_EMBEDDINGS_MODEL=text-embedding-3-small`
- `SESSION_ENCRYPT=true` (production)
- `DEMO_STALE_MINUTES=45`
- `SUPPORTFLOW_MIN_SIMILARITY=0.45`
- `DB_QUEUE_RETRY_AFTER=150`

Do **not** publish a demo-agent password. Evaluators use **Open Agent Dashboard** (`POST /demo/enter-agent`). Credential screens (`/login`, `/register`, password reset, passkeys, 2FA) return 404. Guests who hit agent URLs are sent to the demo home. HTTPS is required for microphone dictation.

## Queue worker

Create a Forge daemon (timeout ≥ 120s):

```bash
php artisan queue:work --queue=ai,default --sleep=1 --tries=3 --timeout=120
```

Set `DB_QUEUE_RETRY_AFTER=150` so reservation outlives the 120s job/worker timeout.

The live timeout is this **daemon command**. After a code change that raises job `$timeout` to 120, **edit the daemon in the Forge UI** from `--timeout=90` to `--timeout=120`. Deploy does not rewrite daemon commands.

`$RESTART_QUEUES()` (or `$FORGE_PHP artisan queue:restart` in the deploy script below) restarts workers during deployment. Restarted processes still launch with the existing daemon command. If the daemon still says `--timeout=90`, workers keep a 90s kill even after `$timeout = 120` ships.

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
