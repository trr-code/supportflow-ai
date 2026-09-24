# Laravel Forge

SupportFlow AI is a single-tenant Laravel 13 app. Use Forge (not Laravel Cloud).

GitHub: [trr-code/supportflow-ai](https://github.com/trr-code/supportflow-ai). Deploy branch `main`.

Public staging URL: [https://supportflow-ai-lqojsjr5.on-forge.com](https://supportflow-ai-lqojsjr5.on-forge.com). Set `APP_URL` to that HTTPS origin.

Do **not** publish deployment-hook tokens. Rotate a token in Forge if it was pasted into chat or a screenshot; never commit it.

## Status (2026-09-24)

The non-ZDD SupportFlow site is **live**. Cutover is complete. CareerForge on the same server is untouched.

Forge CLI is **2.0.3**. Organization `terry-lafferty`, server `careerforge` (`1228434`, `167.172.14.46`).

`forge ssh:test` hangs on Windows at “Establishing secure connection.” Do **not** run `forge ssh:configure`. Prove access with OpenSSH:

```bash
ssh -o BatchMode=yes -o ControlMaster=no -o ControlPath=none -o ControlPersist=no forge@167.172.14.46
```

CLI commands that shell out to SSH hang the same way. API commands (`site:list`, `env:pull`/`env:push`, `deployments/script`) work.

Official Forge: **do not use zero-downtime deployments with Laravel Octane**. This site has ZDD **off**. Deploy with `git pull` in the site root, then `octane:reload` and `queue:restart`. Do **not** use `$CREATE_RELEASE()`, `$ACTIVATE_RELEASE()`, or `$RESTART_QUEUES()`.

Do **not** copy the local 16-worker Octane count onto this 1 vCPU/~1 GB VM. Keep **1** worker.

Rector `--dry-run` is in both `composer test` and GitHub Actions.

### Verified live SupportFlow site (non-ZDD)

| Item | Value |
| --- | --- |
| Site ID | `3397748` |
| Hostname/root | `supportflow-ai-lqojsjr5.on-forge.com` (`/home/forge/supportflow-ai-lqojsjr5.on-forge.com`) |
| Framework | Laravel, PHP **8.5** |
| Zero-downtime | **Off** |
| Push to deploy | **On** for `main` |
| Database | Existing PostgreSQL `supportflow_ai` (pgvector already enabled) |
| Layout | Standard site root (no `current`/`releases` symlink) |
| Octane | **SupportFlow Octane**, running, one process, site-root working directory, graceful shutdown 120s |
| Octane command | `php8.5 artisan octane:start --server=frankenphp --host=127.0.0.1 --port=8001 --workers=1 --max-requests=500` |
| Port | **8001** (permanent; visitors never see it) |
| Nginx | `@octane` `proxy_pass http://127.0.0.1:8001`; `index index.php`; `proxy_buffering off`; `proxy_read_timeout 120s` |
| Health check | Enabled and operational: [https://supportflow-ai-lqojsjr5.on-forge.com/up](https://supportflow-ai-lqojsjr5.on-forge.com/up) |
| Queue | **SupportFlow AI Queue Worker**, running, PHP 8.5, site-root working directory |
| Queue settings | Connection `database`; queue `ai,default`; 1 process; sleep 3; timeout 120; tries 3; memory 128 MB; **no** `--daemon` |
| Scheduler | **SupportFlow AI Laravel Scheduler**, installed, every minute: `php /home/forge/supportflow-ai-lqojsjr5.on-forge.com/artisan schedule:run` |

Manual deployment succeeded. `/up` passed. Chat streaming passed. A complete production ticket test passed: submission, AI triage, retrieval, grounded suggested reply, agent approval, and customer-visible response.

`config/octane.php` `max_execution_time` is 120. Job `$timeout` is 120. `DB_QUEUE_RETRY_AFTER=150`.

PHP-FPM 8.5 remains installed. This site does not use it for HTTPS. `index index.html` with official `try_files $uri $uri/ @octane` returns nginx 403 on `/`.

### CareerForge (do not touch)

Same VM. Supervisor `worker-987181` runs CareerForge queues (`careerforge-vec7voun.on-forge.com`, `--timeout=600 --queue=default`). Leave that worker, that site, its Nginx, scheduler, database, and deploys unchanged.

### Retired ZDD site (cleanup only)

Site `3362425`, hostname `supportflow-ai-ou1b5gvy.on-forge.com`, is **not** the live SupportFlow site. It is temporarily retained for cleanup and has **not** been deleted.

- Old Octane (`daemon-1094048`, port 8000) and the old queue worker (`1055641`) are **stopped**.
- The old scheduler is **paused**.
- Do **not** restart those processes while the live worker and scheduler run against `supportflow_ai`.
- Do **not** point Stressless, `APP_URL`, or the manual regression checklist at this hostname.
- Port **8001** stays the documented SupportFlow Octane port. Do not move it to 8000.

### Unavailable Forge documentation

These URLs returned 404: `/docs/sites/nginx`, `/docs/resources/daemons`, `/docs/sites/deployments.html`. Use Laravel’s [Octane Nginx example](https://laravel.com/docs/13.x/octane#serving-your-application-via-nginx), [Forge deployments](https://forge.laravel.com/docs/sites/deployments), and the [Forge CLI](https://forge.laravel.com/docs/cli) instead.

## Deploy script

Current live script (ZDD off). Do **not** reload PHP-FPM. Do not use ZDD macros.

```bash
cd $FORGE_SITE_PATH
git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

npm ci
npm run build

$FORGE_PHP artisan migrate --force
$FORGE_PHP artisan optimize
$FORGE_PHP artisan octane:reload
$FORGE_PHP artisan queue:restart
```

`public/build` is gitignored, so `npm ci` and `npm run build` are required. `queue:restart` needs a persistent cache (`CACHE_STORE=database`, not `array`). `$FORGE_PHP` follows the site PHP version—keep the site and the queue worker on **PHP 8.5**.

Update the deploy script via PUT `/orgs/{org}/servers/{server}/sites/{id}/deployments/script` with `{content}`. Do not PUT the site resource for script-only edits.

Do **not** run `db:seed --force` again on this database. Seeding duplicates `demo_agent` and Harbor data.

Shared ~1 GB VM: queue storms and Stressless compete with CareerForge. Run progressive Pest Stressless **off-peak** against the **public** staging URL only. That is not permission to load-test an unknown production system.

## Octane/FrankenPHP

Native Windows FrankenPHP is **not** the supported production path. Linux staging uses FrankenPHP behind Nginx on **`127.0.0.1:8001`**.

Keep the queue worker and scheduler **separate** from Octane. Environment keys (set values in Forge, do not commit them):

- `OCTANE_SERVER=frankenphp`
- `OCTANE_HTTPS=true` (Nginx terminates TLS)

Update Nginx via PUT `{config}` to `/orgs/{org}/servers/{server}/sites/{id}/nginx`. Keep Forge `include` lines and the HTTPS wrapper. `index index.php`, `proxy_buffering off`, `proxy_read_timeout 120s`, `proxy_pass http://127.0.0.1:8001`.

## Environment

Set at least:

- `APP_NAME=SupportFlow AI`
- `APP_URL` (the public HTTPS origin above)
- `DB_*` PostgreSQL (`supportflow_ai`, not CareerForge’s database)
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

## PostgreSQL + pgvector

See [pgvector.md](pgvector.md). `postgresql-XX-pgvector` and `CREATE EXTENSION vector` are already in place on `supportflow_ai`. SupportFlow AI stores embeddings as native `vector(1536)` and retrieves with `whereVectorSimilarTo`.

## Queue worker

The live timeout is the **Forge worker command**. `queue:restart` during deploy restarts processes but does not edit the command. After raising job `$timeout`, change the worker in the Forge UI or workers keep the old kill time.

Live worker: connection `database`, `--queue=ai,default`, `--sleep=3`, `--timeout=120`, `--tries=3`, memory 128 MB, one process, PHP 8.5, site root. Do not add deprecated `--daemon`.

## Scheduler

Forge scheduled job every minute:

```bash
php /home/forge/supportflow-ai-lqojsjr5.on-forge.com/artisan schedule:run
```

App schedule:

- `demo:prune-stale` every 15 minutes (idle visitor data only; seeded tickets stay)
- `queue:prune-failed` daily

There is **no** hourly global wipe.

## Production reset

- **Reset stale demo data** in the agent queue UI (and `php artisan demo:prune-stale`) is safe for `demo_agent`.
- `php artisan demo:reset --force` is an owner/CLI escape hatch. It is **not** exposed in the demo-agent UI.

## Logs

Use the Forge log viewer. Do not enable Telescope or Horizon on a public demo.

## Stressless against staging

From a local checkout, with `STRESS=true`, `STRESS_URL=https://supportflow-ai-lqojsjr5.on-forge.com`, and `STRESS_MAX_CONCURRENCY=16`:

1. Start with `composer test:stress:smoke`. Stop if it fails.
2. If smoke passes, you may run load, stress, stability, then capacity at that 16-VU safety rail.
3. GET `/up`, `/`, `/knowledge`, and `/knowledge/return-window` only. Never hit ticket create, chat send, dictation, or regenerate.
