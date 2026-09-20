---
paths:
  - docs/forge.md
  - docs/performance.md
---

# Docs

## Forge daemon timeout is not changed by queue restart
The live worker timeout is the Forge daemon command (--timeout=120). $RESTART_QUEUES() / queue:restart during deploy restarts workers but does not edit the daemon. After raising job $timeout, change the daemon in the Forge UI or workers keep the old kill time. Set DB_QUEUE_RETRY_AFTER=150.

## Local Octane capacity is documented, not copied to Forge
Document measured local GET/chat numbers in docs/performance.md. Do not commit native Windows FrankenPHP binaries, CA paths, or vendor signal guards. Do not copy the local 16-worker count onto Forge.

## Forge ssh:test hangs on Windows OpenSSH
On Windows, `forge ssh:test` hangs at Establishing secure connection because the CLI uses SSH ControlMaster. Do not run ssh:configure. Prove access with OpenSSH BatchMode to the server IP. Resource CLI commands that shell out to SSH hang the same way; API commands (site:list, env:pull/push, deployments/script) work.

## Octane Forge Nginx needs index.php
Forge nginxConfigs is the inner site.conf. Official Octane try_files needs `index index.php` so `/` internally redirects to /index.php then @octane. `index index.html` returns nginx 403 on `/` because public/ is a directory. Keep Forge includes, proxy_buffering off, and proxy_read_timeout 120s for SSE. PUT `/orgs/{org}/servers/{server}/sites/{id}/nginx` with `{config}`.

## Octane plus Forge ZDD restarts the daemon
Official Forge: do not use zero-downtime deployments with Octane. This SupportFlow site still has ZDD; site PUT is 403 for this token. FrankenPHP resolves the release realpath, so octane:reload after symlink switch is not enough. Copy frankenphp into the new release (or octane:install), then `sudo supervisorctl restart daemon-1094048:*` after $ACTIVATE_RELEASE(). Size workers from this 1 vCPU/~1 GB VM; keep 1 worker, never copy local 16.

## Forge deploy script has its own API path
Update the deploy script at PUT `/orgs/{org}/servers/{server}/sites/{id}/deployments/script` with `{content}`. Do not PUT the site resource for script-only edits.
