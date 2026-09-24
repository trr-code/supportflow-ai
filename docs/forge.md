# Laravel Forge

SupportFlow AI is a single-tenant Laravel 13 app. Use Forge (not Laravel Cloud).

GitHub: [trr-code/supportflow-ai](https://github.com/trr-code/supportflow-ai). Deploy branch `main`.

Public staging URL today: [https://supportflow-ai-ou1b5gvy.on-forge.com](https://supportflow-ai-ou1b5gvy.on-forge.com). Set `APP_URL` to the hostname that currently serves visitors.

Do **not** publish deployment-hook tokens. Rotate a token in Forge if it was pasted into chat or a screenshot; never commit it.

## Status (2026-09-24)

Forge CLI is **2.0.3**. Organization `terry-lafferty`, server `careerforge` (`1228434`, `167.172.14.46`). SupportFlow site `3362425`. `origin/main` is `accc6f8`.

`forge ssh:test` hangs on Windows at “Establishing secure connection.” Do **not** run `forge ssh:configure`. Prove access with OpenSSH:

```bash
ssh -o BatchMode=yes -o ControlMaster=no -o ControlPath=none -o ControlPersist=no forge@167.172.14.46
```

CLI commands that shell out to SSH hang the same way. API commands (`site:list`, `env:pull`/`env:push`, `deployments/script`) work.

### Verified live SupportFlow site (ZDD)

Read-only SSH on 24 Sep 2026. CareerForge processes were listed only to avoid touching them.

| Item | Value |
| --- | --- |
| Site root | `/home/forge/supportflow-ai-ou1b5gvy.on-forge.com` |
| Layout | ZDD: `current` → `releases/78115879` (HEAD `accc6f8`) |
| PHP | **8.5.5** (`php` and `php8.5`) |
| Octane | Supervisor `daemon-1094048:daemon-1094048_00` **RUNNING** |
| Octane command | `php8.5 …/current/artisan octane:start --server=frankenphp --host=127.0.0.1 --port=8000 --workers=1 --max-requests=500` |
| Octane cwd | Supervisor `directory=` is `…/current/`; artisan and FrankenPHP run in the resolved release `…/releases/78115879` |
| `frankenphp` binary | Present in that release (~160 MB) |
| Port **8000** | FrankenPHP child of `daemon-1094048` |
| `GET /up` | **200** on `http://127.0.0.1:8000/up`; `php artisan octane:status` reports running |
| Nginx | Inner `site.conf` `@octane` `proxy_pass http://127.0.0.1:8000`; `proxy_buffering off`; `proxy_read_timeout 120s`; `index index.php` |
| Queue | Supervisor `worker-1055641:worker-1055641_00` **RUNNING** |
| Queue command | `php8.5 …/current/artisan queue:work database --sleep=3 --daemon --quiet --timeout=120 --tries=3 --queue=ai,default` (`stopwaitsecs=150`) |
| `--daemon` | **Accepted** on this Laravel 13 install; help marks it **Deprecated**. Not a start failure |
| Scheduler | Cron every minute: `php …/current/artisan schedule:run` (`php` is 8.5.5) |
| Forge health checks | **Off** |
| Push to deploy | On (existing site) |
| Last successful deploy | 20 Sep 01:35 (`provision-219950862`): `daemon-1094048_00: stopped` then `started`, then queue restart, then `Deployment complete` |

Forge **Processes** lists queue workers only. Octane is a **daemon**, which is why `daemon-1094048` looked missing in that tab. It is running.

PHP-FPM 8.5 remains installed. This site does not use it for HTTPS. `index index.html` with official `try_files $uri $uri/ @octane` returns nginx 403 on `/`.

`config/octane.php` `max_execution_time` is 120. Job `$timeout` is 120. `DB_QUEUE_RETRY_AFTER=150`.

### CareerForge (do not touch)

Same VM. Supervisor `worker-987181` runs CareerForge queues (`careerforge-vec7voun.on-forge.com`, `--timeout=600 --queue=default`). Leave that worker, that site, its Nginx, scheduler, database, and deploys unchanged.

### Official Octane vs this site

Official Forge: **do not use zero-downtime deployments with Laravel Octane**. Octane already restarts workers with `octane:reload`. ZDD `releases/` + `current` makes FrankenPHP bind a **release realpath**, so `octane:reload` after `$ACTIVATE_RELEASE()` is not enough.

This site still has ZDD **on**. Site PUT returns 403 for the API token. There is **no** Zero-downtime off toggle on site `3362425`. **ZDD cannot be disabled on the existing site.** Recreate with ZDD off at creation.

Until that replacement serves visitors, keep the working workaround: copy `frankenphp` into the new release (or `octane:install`) **before** `$ACTIVATE_RELEASE()`, then `sudo supervisorctl restart daemon-1094048:*` after activation.

Rector `--dry-run` is in both `composer test` and GitHub Actions; it has not run on `origin/main` yet.

Off-peak Stressless GET (16-VU rail, `K6_NO_COOKIES_RESET=true`) and real `POST /chat/stream` waves 1/2/4 completed against the current HTTPS URL. See [performance.md](performance.md). Scheduler was not paused.

### Unavailable Forge documentation

These URLs returned 404: `/docs/sites/nginx`, `/docs/resources/daemons`, `/docs/sites/deployments.html`. Use Laravel’s [Octane Nginx example](https://laravel.com/docs/13.x/octane#serving-your-application-via-nginx), [Forge deployments](https://forge.laravel.com/docs/sites/deployments), and the [Forge CLI](https://forge.laravel.com/docs/cli) instead.

## Target: replace SupportFlow with a new non-ZDD site

Approved direction: create a **new** SupportFlow site on **the same server**, with **ZDD off at creation**. Move public traffic to it, then delete site `3362425`. CareerForge stays untouched.

Do **not** try to flip ZDD on `3362425`. Do **not** copy the local 16-worker Octane count onto Forge. Size workers from this 1 vCPU/~1 GB VM (keep **1** worker).

### Why port 8001 during overlap, and after

The existing site owns **8000**. Two FrankenPHP processes cannot bind the same port.

The replacement Octane daemon must listen on **`127.0.0.1:8001`** for the whole overlap, and its Nginx `@octane` must `proxy_pass http://127.0.0.1:8001`. Visitors never see that port; Nginx terminates HTTPS.

**Keep 8001 permanently after cutover.** Moving to 8000 later is optional cosmetics: change the daemon `--port`, change Nginx, restart Octane. That is a second restart with no visitor-facing benefit. Reclaim 8000 only if some other process needs it. Laravel’s docs use 8000 as an example, not a requirement.

### Shared database rules

Both sites will use the existing `supportflow_ai` database (pgvector already enabled). **Never** run both sites’ **queue workers** or **schedulers** against that database at the same time. Duplicate workers steal jobs; two `schedule:run` crons double `demo:prune-stale` and `queue:prune-failed`.

Keep the replacement **queue worker created but disabled** (or not started) until cutover. Keep the replacement **scheduler disabled** until cutover. The old worker `1055641` and the old cron stay the only job/schedule runners until the cutover window.

Do **not** run `db:seed --force` on the replacement. Seeding again duplicates `demo_agent` and Harbor data.

`migrate --force` on the replacement is safe when there is nothing to migrate (live already matches `origin/main`). Schema-changing deploys wait until the old site is no longer serving visitors, or run only in the cutover window.

### Replacement setup (Terry in Forge, after approval)

Keep push-to-deploy **off** on the new site until setup and first verify are done. Do not deploy from this repo until that is approved.

1. Create a new site on server `1228434`. Repository `trr-code/supportflow-ai`, branch `main`. PHP **8.5**. Framework Laravel. **Zero-downtime: off.** Web directory `public`. Assign a temporary Forge hostname. Do not take over `supportflow-ai-ou1b5gvy.on-forge.com` yet.
2. Copy `.env` from the old site. Keep `APP_KEY` and `DB_*`. Set `APP_URL` to the **temporary** HTTPS origin. Keep `OCTANE_SERVER=frankenphp`, `OCTANE_HTTPS=true`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `DB_QUEUE_RETRY_AFTER=150`.
3. Deploy script: standard (below). No `$CREATE_RELEASE()`, `$ACTIVATE_RELEASE()`, or `$RESTART_QUEUES()`. Site root is `$FORGE_SITE_PATH` (no `current/`).
4. Octane daemon, directory = site root, **1 worker**, port **8001**:

```bash
php8.5 artisan octane:start --server=frankenphp --host=127.0.0.1 --port=8001 --workers=1 --max-requests=500
```

First start may need `php artisan octane:install --server=frankenphp --no-interaction` if the gitignored `frankenphp` binary is missing.

5. Nginx: same Octane `site.conf` as today (`index index.php`, `proxy_buffering off`, `proxy_read_timeout 120s`) but `proxy_pass http://127.0.0.1:8001`. Keep Forge `include` lines and the HTTPS wrapper.
6. Create the queue worker **disabled**:

```bash
php8.5 artisan queue:work database --sleep=3 --quiet --timeout=120 --tries=3 --queue=ai,default
```

Omit `--daemon` (deprecated). `stopwaitsecs=150`. Do **not** start it until cutover.

7. Create the scheduler (`php artisan schedule:run` every minute) **disabled**. Do not enable it while the old cron still runs.
8. Enable Forge deployment **health checks** against `/up`.
9. First deploy of the same SHA the old site already runs. Confirm `GET /up` over the **temporary** HTTPS hostname and `php artisan octane:status`. Do not point Stressless at the temp hostname until cutover unless you are only checking `/up`.
10. Leave site `3362425` serving the public URL on port 8000 until cutover.

Shared ~1 GB VM: queue storms and Stressless compete with CareerForge. Run progressive Pest Stressless **off-peak** against the **public** staging URL only. That is not permission to load-test an unknown production system.

## Octane/FrankenPHP

Native Windows FrankenPHP is **not** the supported production path. Linux staging uses FrankenPHP behind Nginx.

Keep the queue daemon and scheduler **separate** from Octane. Environment:

- `OCTANE_SERVER=frankenphp`
- `OCTANE_HTTPS=true` (Nginx terminates TLS)

Update Nginx via PUT `{config}` to `/orgs/{org}/servers/{server}/sites/{id}/nginx`. Update the deploy script via PUT `/orgs/{org}/servers/{server}/sites/{id}/deployments/script` with `{content}`. Do not PUT the site resource for script-only edits.

### Replacement (ZDD off, standard deploy)

Point the Octane daemon at the site root. After `git pull`, install PHP and Node deps, migrate, reload Octane, restart queues. Do **not** reload PHP-FPM. Do not use ZDD macros.

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

Until the replacement queue worker is enabled, `queue:restart` is a no-op for SupportFlow jobs (the old worker is still the one running). After cutover it signals the new worker.

### Existing site (ZDD, until deleted)

Copy `frankenphp` into the new release (or `octane:install`) **before** `$ACTIVATE_RELEASE()`, then restart the Octane daemon after activation:

```bash
$CREATE_RELEASE()

cd $FORGE_RELEASE_DIRECTORY

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

npm ci || npm install
npm run build

if [ ! -x frankenphp ]; then
    if [ -x "$FORGE_SITE_PATH/current/frankenphp" ]; then
        cp "$FORGE_SITE_PATH/current/frankenphp" ./frankenphp
        chmod +x frankenphp
    else
        $FORGE_PHP artisan octane:install --server=frankenphp --no-interaction
    fi
fi

$FORGE_PHP artisan optimize
$FORGE_PHP artisan storage:link
$FORGE_PHP artisan migrate --force

$ACTIVATE_RELEASE()

sudo supervisorctl restart daemon-1094048:*
$RESTART_QUEUES()
```

`octane:reload` is not enough while FrankenPHP has resolved `releases/{id}` as the real path.

## Cutover

Do this in one window. CareerForge stays up.

1. Confirm replacement `/up` is 200 on 8001 via its temporary hostname. Confirm old `/up` is still 200 on 8000.
2. Turn **push to deploy off** on the old site if it is still on.
3. **Stop** old queue worker `1055641`. **Disable** the old SupportFlow scheduler cron. Do not stop `worker-987181`.
4. **Start** the replacement queue worker. **Enable** the replacement scheduler.
5. Move the public hostname/SSL onto the replacement site (or switch DNS). Set replacement `APP_URL` to that public HTTPS origin. Reload Octane (`octane:reload`) so cached config picks it up.
6. Confirm public `GET /up`, one chat turn, and one ticket job.
7. Keep the old site’s Octane on 8000 and its Nginx as rollback for a soak period. Do not delete `3362425` yet.
8. After soak: delete the old site (that removes `daemon-1094048`, worker `1055641`, and the old cron). Leave the replacement on **8001**. Optionally turn push to deploy **on** for the new site.

### Rollback

If the replacement misbehaves before the old site is deleted:

1. Point the public hostname/SSL (or DNS) back to site `3362425`.
2. **Stop** the replacement queue worker and **disable** its scheduler.
3. **Start** old worker `1055641` and **enable** the old SupportFlow cron.
4. Confirm public `/up` on 8000. Old Octane should still be running; if it is not, `sudo supervisorctl start daemon-1094048:*`.
5. Leave the replacement site in place (8001, push to deploy off) for a later retry. Do not delete it during rollback unless it is fighting the old site for the hostname.

Do not enable both queue workers or both schedulers during rollback.

## Environment

Set at least:

- `APP_NAME=SupportFlow AI`
- `APP_URL` = the HTTPS origin that currently serves visitors
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

The live timeout is the **Forge worker command**. `$RESTART_QUEUES()`/`queue:restart` restarts processes but does not edit the command. After raising job `$timeout`, change the worker in the Forge UI or workers keep the old kill time.

Live (old site, until cutover): `--timeout=120 --queue=ai,default --sleep=3 --daemon --quiet --tries=3`. `--daemon` is deprecated and may be omitted on the replacement; do not treat it as a reason to restart the live worker.

## Scheduler

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

From a local checkout, with `STRESS=true`, `STRESS_URL=https://supportflow-ai-ou1b5gvy.on-forge.com`, and `STRESS_MAX_CONCURRENCY=16`:

1. Start with `composer test:stress:smoke`. Stop if it fails.
2. If smoke passes, you may run load, stress, stability, then capacity at that 16-VU safety rail.
3. GET `/up`, `/`, `/knowledge`, and `/knowledge/return-window` only. Never hit ticket create, chat send, dictation, or regenerate.

Until cutover, that URL is the current ZDD site. After cutover, point `STRESS_URL` at the public hostname on the replacement.
