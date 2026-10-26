---
paths:
  - docs/forge.md
  - docs/performance.md
  - docs/manual-regression.md
  - README.md
  - docs/release-readiness.md
---

# Docs

## Forge worker timeout is not changed by queue restart
The live worker timeout is the Forge queue-worker command (--timeout=120). `queue:restart` during deploy restarts workers but does not edit the command. After raising job $timeout, change the worker in the Forge UI or workers keep the old kill time. Set DB_QUEUE_RETRY_AFTER=150.

## Local Octane capacity is documented, not copied to Forge
Document measured local GET/chat numbers in docs/performance.md. Do not commit native Windows FrankenPHP binaries, CA paths, or vendor signal guards. Do not copy the local 16-worker count onto Forge.

## Forge ssh:test hangs on Windows OpenSSH
On Windows, `forge ssh:test` hangs at Establishing secure connection because the CLI uses SSH ControlMaster. Do not run ssh:configure. Prove access with `ssh -o BatchMode=yes -o ControlMaster=no -o ControlPath=none -o ControlPersist=no forge@167.172.14.46`. Resource CLI commands that shell out to SSH hang the same way; API commands (site:list, env:pull/push, deployments/script) work.

## Octane Forge Nginx needs index.php
Forge nginxConfigs is the inner site.conf. Official Octane try_files needs `index index.php` so `/` internally redirects to /index.php then @octane. `index index.html` returns nginx 403 on `/` because public/ is a directory. Keep Forge includes, `client_max_body_size 68m`, proxy_buffering off, and proxy_read_timeout 120s for SSE. Preview sends one batch as a single multipart body (eight 8 MiB files plus framing), so 12m is too small and 64M is smaller than that body. The static FrankenPHP binary reads `<site-root>/php.ini` (`upload_max_filesize=12M`, `post_max_size=68M`) and does not read the CLI ini. Restart daemon `daemon-1104270` after changing it. Leave the FPM ini and CareerForge's worker alone. PUT `/orgs/{org}/servers/{server}/sites/{id}/nginx` with `{config}`.

## SupportFlow Forge is non-ZDD Octane on 8001
Live site 3397748 is supportflow-ai-lqojsjr5.on-forge.com on server 1228434. ZDD is off. PHP 8.5, Laravel. Octane command: `php8.5 artisan octane:start --server=frankenphp --host=127.0.0.1 --port=8001 --workers=1 --max-requests=500` at the site root, graceful shutdown 120s. Nginx proxy_pass 127.0.0.1:8001. Health checks on /up are enabled. Push to deploy is on for main. Queue worker: database, ai,default, sleep 3, timeout 120, tries 3, memory 128 MB, no --daemon. Scheduler: `php …/supportflow-ai-lqojsjr5.on-forge.com/artisan schedule:run` every minute. Deploy: git pull, npm ci/build, migrate --force, optimize, octane:reload, queue:restart. No PHP-FPM reload. No ZDD macros. Port 8001 is permanent. CareerForge (worker-987181) stays untouched. Keep 1 worker, never copy local 16. Do not publish deployment-hook tokens.

## Former ZDD site is deleted
Site 3362425/supportflow-ai-ou1b5gvy.on-forge.com has been deleted. Its Octane process, queue worker, and scheduler have been removed. The shared supportflow_ai database remains intact. Live site is 3397748/supportflow-ai-lqojsjr5.on-forge.com.

## Forge deploy script has its own API path
Update the deploy script at PUT `/orgs/{org}/servers/{server}/sites/{id}/deployments/script` with `{content}`. Do not PUT the site resource for script-only edits.

## Never use Forge CLI SSH commands on Windows
Do not use Forge CLI commands that shell out to SSH (`ssh:test`, `php:status`, `nginx:status`, `nginx:restart`). Native Windows OpenSSH works with `-o ControlMaster=no -o ControlPath=none -o ControlPersist=no`. Inspect servers over that SSH session or the Forge HTTP API.

## Manual regression is a post-eval checklist, not a deploy
The complete customer/agent/Forge manual checklist lives at docs/manual-regression.md. Run it only after composer test and local composer test:evals pass. Do not treat it as a deploy. Harbor KB is the authority: unused size exchanges are free within 30 days; prepaid UPS labels belong to approved returns, not exchanges; Ohio tax and embroidery thread colors are undocumented. Local URL is https://supportflow-ai.test; Forge staging is https://supportflow-ai-lqojsjr5.on-forge.com (off-peak, no Stressless writes).

## CareerForge stays unused during Forge verification
CareerForge on the shared VM is unused. Do not skip or shrink Forge verification for it. If worker-987181 contends, stop that worker rather than reducing tests. Do not delete CareerForge or shared Lucid/SupportFlow resources without a separate approval. Process table: docs/forge.md.

## Forge live checklist is verification after local tests
Run the Live Forge staging checklist in docs/manual-regression.md only after local composer test and composer test:evals pass. It is verification, not a deploy.

## Chat TTFB is a separate live check
Live AI-chat TTFB and concurrency use concurrent POST /chat/stream. They are not Stressless and not a single quality turn. See docs/performance.md chat streaming notes.

## queue:listen --timeout is a child-process wall clock
Local composer run dev uses queue:listen --queue=ai,default --timeout=120. That 120s is Symfony Process wall-clock on each queue:work --once child, not Laravel job $timeout. Laravel --timeout has no effect with --once, and job timeouts need PCNTL (not on Windows). Forge uses queue:work --timeout=120. Do not raise either limit because a listen child froze after screen lock or sleep. retry_after stays 150.

## Release-readiness is the living gate
Status of Stages 0–7 and P0–P3 lives in docs/release-readiness.md. Link it from README, AGENTS.md, the showroom plan, and docs/manual-regression.md. A local Feature pass is not Forge verification. A Forge deploy is not readiness to show clients. Do not mark Forge, GitHub CI, device, or file-picker checks passed from local tests. Push to main deploys because Forge push-to-deploy is on.

## Chat TTFB is required release verification
Concurrent POST /chat/stream is required for release verification (local current-path numbers, then Forge). It is not Stressless, not a single quality turn, and not optional because you are not claiming concurrency. 2026-09-19 numbers do not close it. GET Stressless does not close it.

## Forge push-to-deploy does not wait for GitHub CI
Site 3397748 push-to-deploy is on for main. Forge starts git pull on the GitHub push webhook. GitHub Actions tests.yml runs in parallel and does not gate or roll back that deploy. There is no deploy.yml and main is not branch-protected. To wait for CI, turn push-to-deploy off and trigger forge deploy from Actions after Tests.
