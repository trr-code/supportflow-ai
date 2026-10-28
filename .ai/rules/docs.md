---
paths:
  - docs/forge.md
  - docs/performance.md
  - docs/manual-regression.md
  - README.md
  - docs/release-readiness.md
  - '**'
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
Live site 3397748 is supportflow-ai-lqojsjr5.on-forge.com on server 1228434. Forge API `provider` is `laravel` (Laravel VPS, New York 1). It is not a linked DigitalOcean (`ocean2`) droplet. ZDD is off. PHP 8.5, Laravel. Octane command: `php8.5 artisan octane:start --server=frankenphp --host=127.0.0.1 --port=8001 --workers=1 --max-requests=500` at the site root, graceful shutdown 120s. Nginx proxy_pass 127.0.0.1:8001. Health checks on /up are enabled. Push to deploy is disabled; GitHub Actions deploys only after tests pass. Queue worker: database, ai,default, sleep 3, timeout 120, tries 3, memory 128 MB, Forge-generated `--daemon --quiet`. Scheduler: `php …/supportflow-ai-lqojsjr5.on-forge.com/artisan schedule:run` every minute. Deploy: git pull, npm ci/build, migrate --force, optimize, octane:reload, queue:restart. No PHP-FPM reload. No ZDD macros. Port 8001 is permanent. CareerForge (worker-987181) stays untouched. Keep 1 worker, never copy local 16. Do not raise the Octane background-process count (second `octane:start` on :8001). Do not publish deployment-hook tokens.

## Former ZDD site is deleted
Site 3362425/supportflow-ai-ou1b5gvy.on-forge.com has been deleted. Its Octane process, queue worker, and scheduler have been removed. The shared supportflow_ai database remains intact. Live site is 3397748/supportflow-ai-lqojsjr5.on-forge.com.

## Forge deploy script has its own API path
Update the deploy script at PUT `/orgs/{org}/servers/{server}/sites/{id}/deployments/script` with `{content}`. Do not PUT the site resource for script-only edits.

## Never use Forge CLI SSH commands on Windows
Do not use Forge CLI commands that shell out to SSH (`ssh:test`, `php:status`, `nginx:status`, `nginx:restart`). Native Windows OpenSSH works with `-o ControlMaster=no -o ControlPath=none -o ControlPersist=no`. Inspect servers over that SSH session or the Forge HTTP API.

## Manual regression is a post-eval checklist, not a deploy
The complete customer/agent/Forge manual checklist lives at docs/manual-regression.md. Run it only after composer test and local composer test:evals pass. Do not treat it as a deploy. Harbor KB is the authority: unused size exchanges are free within 30 days; prepaid UPS labels belong to approved returns, not exchanges; Ohio tax and embroidery thread colors are undocumented. Local URL is https://supportflow-ai.test; Forge staging is https://supportflow-ai-lqojsjr5.on-forge.com (off-peak, no Stressless writes).

## CareerForge stays unused during Forge verification
CareerForge on the shared VM is unused and not deleted. Supervisor program `worker-987181` is paused (`sudo -n supervisorctl stop worker-987181:*`); restore with `start`. Do not skip or shrink Forge verification for it. Do not delete CareerForge or shared Lucid/SupportFlow resources without a separate approval. Process table: docs/forge.md.

## Forge live checklist is verification after local tests
Run the Live Forge staging checklist in docs/manual-regression.md only after local composer test and composer test:evals pass. It is verification, not a deploy.

## Chat TTFB is a separate live check
Live AI-chat TTFB and concurrency use concurrent POST /chat/stream. They are not Stressless and not a single quality turn. See docs/performance.md chat streaming notes.

## queue:listen --timeout is a child-process wall clock
Local composer run dev uses queue:listen --queue=ai,default --timeout=120. That 120s is Symfony Process wall-clock on each queue:work --once child, not Laravel job $timeout. Laravel --timeout has no effect with --once, and job timeouts need PCNTL (not on Windows). Forge uses queue:work --timeout=120. Do not raise either limit because a listen child froze after screen lock or sleep. retry_after stays 150.

## Release-readiness is the living gate
Status of Stages 0–7 and P0–P3 lives in docs/release-readiness.md. Link it from README, AGENTS.md, the showroom plan, and docs/manual-regression.md. A local Feature pass is not Forge verification. A Forge deploy is not readiness to show clients. Do not mark Forge, GitHub CI, device, or file-picker checks passed from local tests. Push to main runs CI; the dependent deploy job triggers Forge only after tests pass.

## Chat TTFB is required release verification
Concurrent POST /chat/stream is required for release verification (local current-path numbers, then Forge). It is not Stressless, not a single quality turn, and not optional because you are not claiming concurrency. 2026-09-19 numbers do not close it. GET Stressless does not close it.

## Forge deployment waits for GitHub CI
Site 3397748 push-to-deploy is disabled. tests.yml has a deploy job with needs: tests for pushes to main, using the site-specific FORGE_DEPLOY_HOOK secret. .github/forge-deploy.sh rejects missing tested commits, moved main, and unsuccessful GitHub Actions tests before fast-forwarding to the exact tested SHA. Keep the live Forge script synchronized with that file. No branch is required for this user-approved direct-main workflow.

## Forge Octane stays at one worker after the 2-worker trial
Site 3397748 is --workers=1. A 2026-10-26 --workers=2 trial overlapped chat (n=2 1.46–1.54 s vs 1.44–3.07 s) but GET / conc 4 p95 rose 121→187 ms and GET load had active swap-out (so 4456 KB/s for 1 s). Reverted. Octane’s default is not a cap; this 1 vCPU/1 GB Laravel VPS cannot keep 2 workers without GET-load swap-out. Do not copy local 16. Do not raise the Octane Supervisor process count. Response-time targets live in docs/performance.md.

## careerforge is Laravel VPS not DigitalOcean
Server 1228434 (careerforge) has Forge API provider=laravel (Laravel VPS, region New York 1, type app). It is not a linked DigitalOcean ocean2 server. Billing is on the Forge invoice. Resize only via Forge Settings Size for Laravel VPS (upsize only, brief downtime); do not recommend a DigitalOcean droplet plan. Do not raise the Octane background-process count (that starts a second octane:start on :8001). Octane concurrency is --workers inside the existing command. Live SupportFlow queue:work includes Forge-generated --daemon --quiet; Laravel 13 supervisor examples omit --daemon—leave it unless a measured cause says otherwise.

## CareerForge worker pause is reversible
Supervisor program worker-987181 is paused with sudo -n supervisorctl stop worker-987181:* (status STOPPED). Do not DELETE the Forge background process. Restore with sudo -n supervisorctl start worker-987181:*. A server reboot autostarts it. Octane --workers may exceed the one-per-CPU default; a --workers=2 trial edits the existing octane:start command and restarts only daemon-1104270. Do not raise Supervisor process count on that daemon.

## Reverted Forge --workers=2 after GET-load swap-out
A measured --workers=2 trial on daemon-1104270 (numprocs=1) overlapped concurrent chat but caused GET / conc 4 p95 121→187 ms and active swap-out (vmstat so 4456 KB/s for 1 s). Live command is --workers=1 again. Do not re-raise workers on this 1 vCPU/1 GB Laravel VPS without more RAM. Do not raise Supervisor process count.

## Carry audit runtime evidence gaps through every fix package
For Lucid audit/fix work, read docs/system-design-audit.md and its Herd verification follow-up. Preserve valid code-review/test evidence; fill missing runtime evidence per package using Herd site info and fresh, site-scoped Insights with examples off. Record workflow, environment, observed result and remaining gaps before closing findings; reopen findings if evidence contradicts them. Herd does not capture test runs or prove Forge/CI, provider billing, backups, restore, mobile/accessibility or file-picker readiness. Finish with cross-workflow verification and an explicit unresolved-gap review. Check tool availability each chat and flag inaccessible tools/docs; the owner should not need to repeat this reminder. Do not expand package scope or bypass deletion, dependency, commit/deploy or production-load approvals.

## Verify answers against the official documentation relevant to the question
The owner wants documentation checks by default, without repeating the request. Before substantive technical/product guidance or implementation, consult current official documentation for the subject: OpenAI/Codex for agent behavior/setup, Herd for local tooling, installed-version Laravel/Livewire/package docs for application work, and the relevant primary authority otherwise. Reuse sufficient verified sources already in context. Cite the supporting source concisely and distinguish documented behavior, observed local evidence, engineering judgment and unverified claims. Explicitly flag inaccessible documentation or missing coverage; never imply documentation established something it did not. Persistent instructions improve continuity but do not guarantee every future agent will comply.

## Use Conventional Commits for future commit messages
The owner adopted Conventional Commits 1.0.0 for this project going forward. Use lowercase type and a short meaningful lowercase scope where useful: fix(security): prevent unsafe uploads. Common types: feat, fix, docs, refactor, perf, test, build, ci, chore. Keep descriptions concise, imperative and without a trailing period; use ! and a BREAKING CHANGE footer for actual breaking changes. Scope is optional under the specification; do not invent one for changes with no useful single area. Preserve existing history. This message-format preference does not authorize commits, pushes or deployment; obtain the owner's approval as already required.
