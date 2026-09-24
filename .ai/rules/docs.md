---
paths:
  - docs/forge.md
  - docs/performance.md
  - docs/manual-regression.md
  - README.md
---

# Docs

## Forge daemon timeout is not changed by queue restart
The live worker timeout is the Forge daemon command (--timeout=120). `$RESTART_QUEUES()`/`queue:restart` during deploy restarts workers but does not edit the daemon. After raising job $timeout, change the daemon in the Forge UI or workers keep the old kill time. Set DB_QUEUE_RETRY_AFTER=150.

## Local Octane capacity is documented, not copied to Forge
Document measured local GET/chat numbers in docs/performance.md. Do not commit native Windows FrankenPHP binaries, CA paths, or vendor signal guards. Do not copy the local 16-worker count onto Forge.

## Forge ssh:test hangs on Windows OpenSSH
On Windows, `forge ssh:test` hangs at Establishing secure connection because the CLI uses SSH ControlMaster. Do not run ssh:configure. Prove access with `ssh -o BatchMode=yes -o ControlMaster=no -o ControlPath=none -o ControlPersist=no forge@167.172.14.46`. Resource CLI commands that shell out to SSH hang the same way; API commands (site:list, env:pull/push, deployments/script) work.

## Octane Forge Nginx needs index.php
Forge nginxConfigs is the inner site.conf. Official Octane try_files needs `index index.php` so `/` internally redirects to /index.php then @octane. `index index.html` returns nginx 403 on `/` because public/ is a directory. Keep Forge includes, proxy_buffering off, and proxy_read_timeout 120s for SSE. PUT `/orgs/{org}/servers/{server}/sites/{id}/nginx` with `{config}`.

## Octane plus Forge ZDD restarts the daemon
Official Forge: do not use zero-downtime deployments with Octane. Site 3362425 still has ZDD; it cannot be turned off (no UI toggle, site PUT 403). Keep the working workaround until cutover: copy frankenphp into the new release (or octane:install), then `sudo supervisorctl restart daemon-1094048:*` after $ACTIVATE_RELEASE(). SSH 24 Sep 2026: daemon-1094048 is RUNNING on 127.0.0.1:8000, 1 worker; worker-1055641 is the queue; GET /up is 200; Forge health checks are off. `queue:work --daemon` is accepted and Deprecated. Forge Processes lists queue workers only—Octane is a daemon, not missing. Size workers from this 1 vCPU/~1 GB VM; keep 1 worker, never copy local 16.

## Replace SupportFlow with a non-ZDD site on the same server
Approved target: new site on server 1228434, ZDD off at creation. CareerForge (worker-987181, careerforge-vec7voun.on-forge.com) stays untouched. Overlap port is 8001 (old site owns 8000); Nginx proxy_pass must match. Keep 8001 after cutover—moving to 8000 later is optional and needs another Octane restart. Never run both sites' queue workers or schedulers against supportflow_ai at once. Keep the replacement worker and scheduler disabled, and push-to-deploy off, until cutover. Enable /up health checks on the replacement. Standard deploy on the new site: git pull, npm ci/build, migrate --force, optimize, octane:reload, queue:restart. No PHP-FPM reload. No ZDD macros. Do not publish deployment-hook tokens. Cutover and rollback steps live in docs/forge.md.

## Forge deploy script has its own API path
Update the deploy script at PUT `/orgs/{org}/servers/{server}/sites/{id}/deployments/script` with `{content}`. Do not PUT the site resource for script-only edits.

## Never use Forge CLI SSH commands on Windows
Do not use Forge CLI commands that shell out to SSH (`ssh:test`, `php:status`, `nginx:status`, `nginx:restart`). Native Windows OpenSSH works with `-o ControlMaster=no -o ControlPath=none -o ControlPersist=no`. Inspect servers over that SSH session or the Forge HTTP API.

## Manual regression is a post-eval checklist, not a deploy
The complete customer/agent/Forge manual checklist lives at docs/manual-regression.md. Run it only after composer test and local composer test:evals pass. Do not treat it as a deploy. Harbor KB is the authority: unused size exchanges are free within 30 days; prepaid UPS labels belong to approved returns, not exchanges; Ohio tax and embroidery thread colors are undocumented. Local URL is https://supportflow-ai.test; Forge staging is https://supportflow-ai-ou1b5gvy.on-forge.com (off-peak, no Stressless writes).
