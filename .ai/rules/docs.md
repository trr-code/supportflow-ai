---
paths:
  - docs/forge.md
  - docs/performance.md
  - docs/manual-regression.md
  - README.md
---

# Docs

## Forge worker timeout is not changed by queue restart
The live worker timeout is the Forge queue-worker command (--timeout=120). `queue:restart` during deploy restarts workers but does not edit the command. After raising job $timeout, change the worker in the Forge UI or workers keep the old kill time. Set DB_QUEUE_RETRY_AFTER=150.

## Local Octane capacity is documented, not copied to Forge
Document measured local GET/chat numbers in docs/performance.md. Do not commit native Windows FrankenPHP binaries, CA paths, or vendor signal guards. Do not copy the local 16-worker count onto Forge.

## Forge ssh:test hangs on Windows OpenSSH
On Windows, `forge ssh:test` hangs at Establishing secure connection because the CLI uses SSH ControlMaster. Do not run ssh:configure. Prove access with `ssh -o BatchMode=yes -o ControlMaster=no -o ControlPath=none -o ControlPersist=no forge@167.172.14.46`. Resource CLI commands that shell out to SSH hang the same way; API commands (site:list, env:pull/push, deployments/script) work.

## Octane Forge Nginx needs index.php
Forge nginxConfigs is the inner site.conf. Official Octane try_files needs `index index.php` so `/` internally redirects to /index.php then @octane. `index index.html` returns nginx 403 on `/` because public/ is a directory. Keep Forge includes, proxy_buffering off, and proxy_read_timeout 120s for SSE. PUT `/orgs/{org}/servers/{server}/sites/{id}/nginx` with `{config}`.

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
