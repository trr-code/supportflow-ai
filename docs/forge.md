# Laravel Forge

SupportFlow AI is a single-tenant Laravel 13 app. Use Forge (not Laravel Cloud).

GitHub: [trr-code/supportflow-ai](https://github.com/trr-code/supportflow-ai). Deploy branch `main`.

Public staging URL: [https://supportflow-ai-lqojsjr5.on-forge.com](https://supportflow-ai-lqojsjr5.on-forge.com). Set `APP_URL` to that HTTPS origin.

Do **not** publish deployment-hook tokens. Rotate a token in Forge if it was pasted into chat or a screenshot; never commit it.

## Status (2026-09-24)

The non-ZDD SupportFlow site is **live**. Cutover is complete. CareerForge on the same server is unused (not deleted).

Forge CLI is **2.0.3**. Organization `terry-lafferty`, server `careerforge` (`1228434`, `167.172.14.46`). Forge API `provider` is **`laravel`** (Laravel VPS, region New York 1 / `nyc1`, type `app`, Ubuntu 24.04). It is **not** a linked DigitalOcean (`ocean2`) server. Resize, if ever approved, is Forge Settings → Size (Laravel VPS upsize only).

`forge ssh:test` hangs on Windows at “Establishing secure connection.” Do **not** run `forge ssh:configure`. Prove access with OpenSSH:

```bash
ssh -o BatchMode=yes -o ControlMaster=no -o ControlPath=none -o ControlPersist=no forge@167.172.14.46
```

CLI commands that shell out to SSH hang the same way. API commands (`site:list`, `env:pull`/`env:push`, `deployments/script`) work.

Official Forge: **do not use zero-downtime deployments with Laravel Octane**. This site has ZDD **off**. Deploy by fast-forwarding to the CI-tested commit in the site root, then `octane:reload` and `queue:restart`. Do **not** use `$CREATE_RELEASE()`, `$ACTIVATE_RELEASE()`, or `$RESTART_QUEUES()`.

Do **not** copy the local 16-worker Octane count onto this 1 vCPU/961 MiB Laravel VPS. Octane’s default (one worker per CPU) is not a cap. A `--workers=2` trial on 2026-10-26 overlapped chat (n=2 1.46–1.54 s vs 1.44–3.07 s) but GET `/` conc 4 p95 rose 121→187 ms and GET load had **active swap-out** (`so` 4456 KB/s for 1 s). **Reverted to `--workers=1`.** CareerForge `worker-987181` stays **STOPPED**. Do not raise the Octane Supervisor process count.

Rector `--dry-run` is in both `composer test` and GitHub Actions.

### Verified live SupportFlow site (non-ZDD)

| Item | Value |
| --- | --- |
| Site ID | `3397748` |
| Hostname/root | `supportflow-ai-lqojsjr5.on-forge.com` (`/home/forge/supportflow-ai-lqojsjr5.on-forge.com`) |
| Framework | Laravel, PHP **8.5** |
| Zero-downtime | **Off** |
| Push to deploy | **Off**. GitHub Actions tests must succeed before its dependent deploy job calls the hook. The live Forge script also verifies CI and deploys exactly that tested SHA. |
| Database | Existing PostgreSQL `supportflow_ai` (pgvector already enabled) |
| Layout | Standard site root (no `current`/`releases` symlink) |
| Octane | **SupportFlow Octane**, running, one process, site-root working directory, graceful shutdown 120s |
| Octane command | `php8.5 artisan octane:start --server=frankenphp --host=127.0.0.1 --port=8001 --workers=1 --max-requests=500` |
| Port | **8001** (permanent; visitors never see it) |
| Nginx | `@octane` `proxy_pass http://127.0.0.1:8001`; `index index.php`; `proxy_buffering off`; `proxy_read_timeout 120s` |
| Health check | Enabled and operational: [https://supportflow-ai-lqojsjr5.on-forge.com/up](https://supportflow-ai-lqojsjr5.on-forge.com/up) |
| Queue | **SupportFlow AI Queue Worker**, running, PHP 8.5, site-root working directory |
| Queue settings | Connection `database`; queue `ai,default`; 1 process; sleep 3; timeout 120; tries 3; memory 128 MB; Forge-generated **`--daemon --quiet`** (Laravel 13 supervisor examples omit `--daemon`; leave it) |
| Scheduler | **SupportFlow AI Laravel Scheduler**, installed, every minute: `php /home/forge/supportflow-ai-lqojsjr5.on-forge.com/artisan schedule:run` |

Manual deployment succeeded. `/up` passed. Chat streaming passed. A complete production ticket test passed: submission, AI triage, retrieval, grounded suggested reply, agent approval, and customer-visible response.

`config/octane.php` `max_execution_time` is 120. Job `$timeout` is 120. `DB_QUEUE_RETRY_AFTER=150`.

PHP-FPM 8.5 remains installed. This site does not use it for HTTPS. `index index.html` with official `try_files $uri $uri/ @octane` returns nginx 403 on `/`.

### CareerForge (unused, not deleted)

Same VM. Supervisor program is `worker-987181` (not `daemon-987181`). Command: `queue:work` `--timeout=600 --queue=default` under `careerforge-vec7voun.on-forge.com/current`. **Paused 2026-10-26 22:10Z** with `sudo -n supervisorctl stop worker-987181:*`. Status `STOPPED`. The Forge background-process record was not deleted. Restore with `sudo -n supervisorctl start worker-987181:*`. A server reboot will autostart it (Forge queue workers start on reboot). Do not delete CareerForge or shared Lucid/SupportFlow resources. Before stopping anything else, list Forge sites and treat unknown vhosts as keep-until-identified.

### Former ZDD site (deleted)

Site `3362425`, hostname `supportflow-ai-ou1b5gvy.on-forge.com`, has been **deleted**. Its Octane process, queue worker, and scheduler have been **removed**. The shared PostgreSQL database `supportflow_ai` remains intact. Live SupportFlow is still site `3397748`/`supportflow-ai-lqojsjr5.on-forge.com`. Port **8001** stays the documented SupportFlow Octane port.

### Unavailable Forge documentation

These URLs returned 404 (rechecked 2026-10-26): `https://forge.laravel.com/docs/sites/nginx`, `https://forge.laravel.com/docs/resources/daemons`, `https://laravel.com/forge/docs/sites/nginx.md`, `https://forge.laravel.com/docs/sites/deployments.html`. Daemons are now [Background processes](https://laravel.com/forge/docs/resources/background-processes). Use Laravel’s [Octane Nginx example](https://laravel.com/docs/13.x/octane#serving-your-application-via-nginx), [Forge deployments](https://laravel.com/forge/docs/sites/deployments), [Laravel sites / Octane](https://laravel.com/forge/docs/sites/laravel), and the [Forge CLI](https://laravel.com/forge/docs/cli). Canonical Forge docs live under `https://laravel.com/forge/docs/`.

## Deploy script

Current live script (ZDD off): [.github/forge-deploy.sh](../.github/forge-deploy.sh). It verifies the tested commit before updating code. Do **not** reload PHP-FPM or use ZDD macros.

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
- `workspaces:purge-expired` every 15 minutes (expired private previews only; it does not call `demo:prune-stale`)
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

## Private preview uploads

Before the first client document upload on this server:

- Set Forge `OPENAI_STORE=false`. Leave model-feedback sharing, evaluation/fine-tuning sharing, and API input/output sharing Disabled in the OpenAI organization. This app has no switch that turns those on.
- Livewire posts every file selected in one Preview batch as `files[]` on a single request. Eight 8 MiB files are one 64 MiB body plus multipart framing, so `10M`/`12M` cannot hold a full batch. Keep the app limits at 8 MB per file and 8 files per batch.
- In `/etc/php/8.5/cli/php.ini` only, `upload_max_filesize = 12M` and `post_max_size = 68M`. `12M` matches Livewire's temporary rule `max:12288` (12 MiB), so a file between 8 MiB and 12 MiB reaches “Each file must be 8 MB or smaller.” `68M` is 64 MiB of file bytes plus about 4 MiB for multipart framing. `64M` is smaller than that body. Leave the FPM ini at `2M` / `8M`. Forge’s server max upload size stays `2`, because that control writes one number into every PHP version. PUT `/orgs/{org}/servers/{server}/php/versions/{phpVersion}/configs/cli` with `{config}` needs the `server:manage-php` scope.
- The SupportFlow Octane process is the static `frankenphp` binary in the site root. It does not read `/etc/php/8.5/cli/php.ini`. It reads `php.ini` from that same site root. That file contains only the two lines above. Do not point FrankenPHP at the CLI ini: the CLI extension directory is not FrankenPHP's. After either ini changes, restart only daemon `daemon-1104270`. Do not restart CareerForge's worker `worker-987181`.
- The site Nginx config sets `client_max_body_size 68m;` after `index index.php;`. Keep `68m`. A 13 MiB POST reaches Laravel. A 69 MiB POST is rejected by Nginx.
- Stored client originals are capped at 2 GiB, and an upload is refused when free space on `/` would fall below 8 GiB.

## CI-gated deployment (2026-10-28)

Push-to-deploy is disabled for site 3397748. GitHub Actions runs the existing quality suite, then the dependent `deploy` job calls the site-specific `FORGE_DEPLOY_HOOK` secret only for a push to main. No branch or pull request is required for this explicitly approved workflow.

The live Forge deploy script matches [.github/forge-deploy.sh](../.github/forge-deploy.sh). It requires `ci_commit`, fetches the configured branch without changing the working tree, rejects a moved branch, checks GitHub Actions `tests` success for that exact SHA, and only then fast-forwards to it. A manual Deploy Now without that tested commit is blocked. GitHub/API failures fail closed. Existing Composer/build/migrate/Octane/queue steps remain. Do not print the deploy hook. The HTTP hook acknowledges submission; verify Forge deployment completion and health separately.

Run `bash .github/forge-deploy-test.sh` to verify rejected missing/stale commits, failed/pending/skipped CI, foreign checks, network/JSON errors, and successful deployment. This uses isolated command doubles and does not deploy. The workflow runs these checks too.