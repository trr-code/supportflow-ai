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

Local Octane HTML capacity on a 20-thread desktop was **770 rps at 16 workers**. Do **not** copy that worker count here. Size Forge Octane workers from this VM’s CPU/RAM and CareerForge load. See [performance.md](performance.md).

## Octane / FrankenPHP (Linux staging)

Laravel documents Octane behind Nginx with FrankenPHP on `127.0.0.1:8000`. Native Windows FrankenPHP is **not** the supported production path.

1. Keep the queue daemon and scheduler **separate** from Octane.
2. Add a Forge daemon, directory = the site path (the `current` release if isolation is on):

```bash
php artisan octane:start --server=frankenphp --host=127.0.0.1 --port=8000 --workers=2 --max-requests=500
```

Start with **2 workers** on this shared ~1 GB VM unless measured otherwise. `config/octane.php` `max_execution_time` is 120.

3. Environment:

- `OCTANE_SERVER=frankenphp`
- `OCTANE_HTTPS=true` (Nginx terminates TLS)

4. Replace the site Nginx config with Laravel’s [Octane Nginx example](https://laravel.com/docs/13.x/octane#serving-your-application-via-nginx): static files from `public`, `proxy_pass http://127.0.0.1:8000` for application routes, WebSocket upgrade map, `X-Forwarded-*` headers. After TLS, keep HTTPS `listen` as Forge already configured.

5. Deploy script must reload in-memory workers after the new release is in place:

```bash
$FORGE_PHP artisan migrate --force
$FORGE_PHP artisan octane:reload --server=frankenphp
$FORGE_PHP artisan queue:restart
```

If `octane:reload` cannot reach the daemon (isolated releases / cwd mismatch), restart the Octane daemon instead. Zero-downtime isolation conflicts with Octane when the daemon’s working directory is a now-replaced release path—point the daemon at `current` or disable isolation for this site.

6. Confirm `GET /up` over HTTPS after deploy. Octane process health: daemon running, `octane:status` via `forge command` if the CLI is authenticated.

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
- `OCTANE_SERVER=frankenphp`
- `OCTANE_HTTPS=true`

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
$FORGE_PHP artisan octane:reload --server=frankenphp
$FORGE_PHP artisan queue:restart
```

After first deploy: `php artisan db:seed --force` once to create `demo_agent`, the Harbor & Co knowledge base, and showcase tickets. If Octane is not yet the site runtime, omit `octane:reload` until the daemon and Nginx proxy are in place.

## Production reset

- **Reset stale demo data** in the agent queue UI (and `php artisan demo:prune-stale`) is safe for `demo_agent`.
- `php artisan demo:reset --force` is an owner/CLI escape hatch. It is **not** exposed in the demo-agent UI.

## Logs

Use the Forge log viewer. Do not enable Telescope or Horizon on a public demo.
