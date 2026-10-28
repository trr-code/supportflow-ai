# Release-readiness

Living status of Stages 0–7 and P0–P3. The showroom follow-on plan is the historical gate; this file is the operator checklist. Last updated **2026-10-28** after package 1 security/data-lifecycle deployment verification.

## Current package 1 checkpoint — 2026-10-28

The tables below retain earlier evidence; this checkpoint supersedes their older CI, invoice/file-picker and local-worker status claims. Application revision `a957299` passed CI run `40771079936` (492 tests, 3,647 assertions; two optional smoke tests skipped) and completed Forge deployment `81501820`. The final release-notes commit changes documentation only; GitHub Actions/Forge history remain authoritative for its execution status. The production invoice upload/extract/correct/confirm/CSV/JSON journey and private-document upload/Ready/cited-answer journey passed with fictional project fixtures. `/up` returned 200, queue/failed-job counts were zero, and the deployed migration, parser binary, scheduler and daily log rotation were verified. Historical AI-content cleanup completed with accounting totals preserved. See [system-design-audit.md](system-design-audit.md), “Package 1 production acceptance,” for evidence and limitations.

Next is package 2: reproducible verification and current status, including the intermittent local test-database deadlock, evaluation warning details and CI tooling notices. Repeated queries stay in package 5; tone polish stays in package 4. Hands-on Excel/LibreOffice CSV transformations, real microphone, mobile/iPad and assistive-technology acceptance remain open in their respective scope. Broader production recovery, provider retention/billing and off-peak capacity checks are not closed by this deployment. No full-showroom-readiness claim is made.

Do not treat a local Feature pass as Forge verification. Do not treat a Forge deploy as readiness to show clients.

## Gates

| Gate | Meaning | Current status |
| --- | --- | --- |
| **Landing** | Code may be committed to `main` | `2d6943d` is on `main`. **Uncommitted:** manual-reply focus after `dialog[open]`, response-time targets, worker-revisit notes, this checklist. |
| **Deploy for verification** | Code is on live Forge so the staging checklist can run | **Closed** for `2d6943d`. The dialog-focus fix is local-only until you approve a commit/push. |
| **Client show** | Safe to walk a client through the showroom | **Not ready.** Device/file-picker checks remain. Keep 1 Octane worker. |

The user explicitly requested direct `main` commits for this checkpoint, without co-author trailers or branches. [.github/workflows/tests.yml](../.github/workflows/tests.yml) retains Pint, Rector dry-run, Larastan, Pest excluding stress/evals, and Playwright. Forge push-to-deploy is now disabled. A dependent deploy job triggers the site-specific hook after tests succeed; the live Forge script independently requires the tested SHA and successful CI before updating code. See [forge.md](forge.md#ci-gated-deployment-2026-10-28). Current deployment completion is still pending this checkpoint push.

## Missing information and docs that could not be opened

| Need | What happened | Blocks |
| --- | --- | --- |
| Forge doc URLs `/docs/sites/nginx`, `/docs/resources/daemons`, `/docs/sites/deployments.html` | HTTP 404. Already recorded in [docs/forge.md](forge.md). Used Laravel Octane Nginx, Forge deployments, and Forge CLI instead. | No |
| Worker logs for 2026-10-23 13:17 `ProcessTicketIntake` cluster | Not on disk. Cause (stopped `ai` worker vs other) stays unconfirmed. `tries`/`uniqueFor` unchanged. | No. Do not change unique/`tries` without a measured cause. |
| Other Lucid sites on Forge server `1228434` | `forge site:list`: only CareerForge `3287475` and SupportFlow `3397748`. | No. Only `worker-987181` is the extra stop candidate. |
| Live Forge `CACHE_STORE` / env this pass | Pulled then deleted: `CACHE_STORE=database`, `QUEUE_CONNECTION=database`, `DB_QUEUE_RETRY_AFTER=150`, `OCTANE_SERVER=frankenphp`, `OCTANE_HTTPS=true`, `APP_URL=https://supportflow-ai-lqojsjr5.on-forge.com`. | No |
| Whether the machine slept during the 120s listen child | Screen lock observed; system sleep unconfirmed. | No. Limits not changed. |
| Bakeoff retrieved passage IDs from 2026-10-26 | Gone (`RefreshDatabase`). Feature proof replaced that gap. | No |
| GitHub Actions on this change set | Tests **40381813044** passed (2m50s) on `d93755f`. Tests **40394933464** on `2d6943d` **failed** (Pest `TicketWorkspaceTest` dialog-focus). Cause: `$this->js()` focused `#ticket-reply` before Flux set `dialog[open]` (inert). Local fix waits for the open dialog; browser test passed twice (147 assertions). Uncommitted, so CI is still red on `main`. | **Landing** until the fix is on `main` |
| HealthSmokeTest seed | Plan asked to seed it. That test hits live `STRESS_URL`, so a Pest seed writes the testing cache, not Herd/Forge. Feature tests seed. Live `/up` needs a running worker. | No |
| Forge CLI version | Live CLI is 2.0.0; latest noted 2.0.4. Did not block API `site:list` / SSH with `ControlMaster=no`. | No |
| Live worker `--daemon` flag | Process table shows `--daemon` on SupportFlow `queue:work` (`worker-1104326`) even though [docs/forge.md](forge.md) says no `--daemon`. Worker is healthy. Do not edit the Forge worker command without a separate approval. | No |
| Origin status of the 1 failed GET at `/` conc 4 | SupportFlow nginx `access_log off`; `3397748-error.log` empty. k6 `http_req_failed` is transport-level, not a status histogram. | No. Not an app 5xx in the error log. |

## How to use the tables

**Status** is `Closed`, `Local closed`, `Open`, or `Out of scope`. Open items name who or what they need. They are **not** passed.

**Blocks:** `Landing` (commit/push), `Verify` (release verification after deploy), `Client` (walkthrough), or `No`.

## Replacement Confirm (why this is not a call-ID swap)

The plan said persist the provider `call_id` (`call_…` / `ToolCall.resultId`) and resume OpenAI with `Decisions::approve()`. Stage 4 already showed that was the wrong fix.

**What broke.** Confirm saved the Harbor request in PHP, then resumed the stored conversation. Laravel/AI `PendingApproval.id` is the tool-call `fc_…`. OpenAI `function_call_output` wants `call_…`. The live 400 was worse than that namespace mix-up: stored pauses replay **reasoning** blocks without the matching `function_call` items, so OpenAI returned `No tool call found for function call output` (Stage 4 used LookupOrder’s `call_AGgXr…`). Ticket **HR-C09B3B** was already saved; `ai_runs` 663 failed.

**What shipped.** Confirm and cancel no longer call OpenAI. `executeVisitorDecision` commits or cancels locally, writes the assistant line, completes the `AiRun`, and deletes the SDK conversation so a later turn cannot replay the broken pause. `storeProposal` still records `snapshot.provider_call_id` when the model returns one; Confirm does not send it.

**Verified**

- Feature `confirming a proposal saves a request without resuming OpenAI tool output`: `Http::preventStrayRequests()` + `Http::fake()`, `beginConfirm()`, `HR-…` request, proposal `confirmed`, `ai_runs` `completed` with `error` null, assistant body contains the reference, `conversation_id` null, `Http::assertNothingSent()`. Feature cancel test still present (`cancel saves nothing`).
- Live Herd 2026-10-26 17:13:32, new case: **HR-5EFB44**, proposal `confirmed`, `tool_call_id` still `fc_050b98ef…`, snapshot `provider_call_id` `call_Wq9L833JvQPA0zGKaAXBkOcJ`, `ai_runs.id` **671** `completed`, payload `decision: approve`, `error` null.
- Live Forge after `d93755f`: new case Confirm **HR-5A6717**, “Your replacement request is saved. Reference HR-5A6717. Nothing is shipped.” No 400. Cancel on a later case: activity and conversation **Nothing was saved.**, no new HR, no 400.

## Stages 0–7

| Item | Status | Evidence | Next action | Blocks |
| --- | --- | --- | --- | --- |
| Stage 0 unused `KnowledgeArticlePolicy` / `AiRunPolicy::viewRawPrompt` | Out of scope | Not called. Product gap, not this pass. | Leave. | No |
| Demo agent desk RBAC vs org tenancy | Closed as documented | Policies + gates in use; shared desk is intentional. Org/SSO is future product. | Do not add org tenancy here. | No |
| Ticket AI-unavailable leftover `ai_failed` | Open | Local rows earlier this session; Forge dashboard still showed **1 AI unavailable** after SF-R50WE. | Optional Retry AI from the Agent desk if the queue should look clean. Out of scope as a code change. | **Client** polish only |
| 2026-10-23 13:17 failed-job cluster | Open (cause) | Eight `MaxAttemptsExceededException`. No worker log from that minute. | Keep Retry AI. Do not change `tries`. | No |
| Preview tone/length one-file three-heading | Local closed | Feature: workspace returns Shipping, Returns, and Warranty. Harbor diversity stays at 2. Bakeoff filter passed 3/3 after ceiling assertion `≤ 80`. | No eval rerun. | No |
| `/up` boot-only | Closed | Feature 500s for missing/stale/wrong-queue/DB-down. Live Forge `/up` **200** after deploy (DiagnosingHealth requires `{ai, default}` and a fresh beat). `CACHE_STORE=database`. | None. | No |
| GET Stressless (Stage 6) | Closed for the ladder; 1-fail flake explained | First `/` capacity conc 4: **1** k6 failed request after a ~7k-request weekday ladder (p95 475 ms). Origin error log empty; vhost `access_log off`, so that request’s HTTP status is unknown. k6 `http_req_failed` is transport/error, not 4xx/5xx. Cooldown rerun last-healthy **16**, 0 failed. After-check: Forge `GET /` conc 4 × 15 s **503/503 HTTP 200**; smoke 4/4 0 failed. Did not raise Octane workers. | Optional: turn on origin access_log if you need status on the next flake. | No |
| Chat TTFB / concurrent `POST /chat/stream` | Closed | Cause of 9.7 s: 1 Octane worker serialized 4 SSE streams. Embed batch landed. Forge after `2d6943d`: warm n=1 **1.37 s**; n=2 **1.80–4.58 s**; n=4 **1.36–6.38 s**. Targets in [performance.md](performance.md): ≤ 2.0 s / 5.0 s / 8.0 s. Remainder is OpenAI TTFB plus 1-worker serialization. Worker revisit: keep 1 (1 vCPU/961 MiB, 295 MiB swap, FrankenPHP ~122 MB). | None. Do not add workers. | No |
| Invoice extract current file | Open | No file-input in this browser. Historical INV-1001 and faked Feature tests do not count. | **You:** pick a current PDF on `/invoices`. | **Client** for that demo |
| Preview visitor upload | Open | Bakeoff used `UploadedFile::fake()`. Stage 1 “not live-tested.” | **You:** real picker → Ready → cited SSE. | **Client** for that demo |
| Encoded/base64 and multilingual jailbreaks | Closed | Stage 2 left them “never live-run” because the regex demo control cannot see them, not because testing was unauthorized. Live evals 2026-10-26: Base64, Spanish, and Chinese instruction overrides `toBeSafe`, no system-prompt leak, no `OPENAI_API_KEY`. Unit: `ChatInjectionGate::blocks` is false (regex unchanged). | None. Do not expand the regex. | No |
| Skip link, one h1, Lucid title, 390px wrap, wrapping chips | Local closed; Forge titles/skip/footer spot-checked | Feature `LucidShellTest` / `LandingPageTest`. Forge titles used Lucid; skip link present; footer Privacy/Contact on home, `/preview`, `/invoices`. Public `/knowledge/return-window` has skip, not the Lucid Privacy footer (policy page). | iPad still open below. | No |
| iPad viewport | Open | Stage 3 blocked. | **You:** ~768–1024px and a real device if you have one. | **Client** if you demo iPad |
| Live mic | Open | Automated dictation does not open a microphone. | **You:** Chrome permission + spoken transcript. | **Client** if you demo dictation |
| Local Herd badge covering footer | Closed (dispose) | Local-only, not Forge. | Ignore. | No |
| Phone Preview launcher closed | Closed (dispose) | Intentional. | Leave. | No |

## P0–P3

| Item | Status | Evidence | Next action | Blocks |
| --- | --- | --- | --- | --- |
| P0 Replacement Confirm | Closed | Skip-resume, not a call-ID swap. Feature: no OpenAI HTTP. Live Herd **HR-5EFB44**. Live Forge **HR-5A6717**. Cancel: **Nothing was saved.** | None. | No |
| P0 Sources after send | Closed | Feature: TicketShow cites the approved reply. Live Forge **SF-R50WE** `/agent/tickets/26`: after Approve and send, “These policies were cited in the sent reply.” Sources still **Return window** and **Box not required**. Customer status hid the draft before send. | None. | No |
| P1 Lucid footer / Privacy / Contact on `/preview` and `/invoices` | Closed | Forge spot-check after deploy. | None. | No |
| P1 Refusal citations | Closed | `ChatInjectionGate::isRefusal` expanded; SuggestedReply and Preview strip trailer citations. Regex gate unchanged as demo control. Encoded/multilingual live evals passed. | None. | No |
| P1 Docs throttle 10 → 30 | Closed | [docs/performance.md](performance.md) matches `supportflow.rate_limits.chat_per_minute` 30. | None. | No |
| P1 `/up` heartbeat must list `ai` and `default` | Closed | Live Forge `/up` 200 after deploy. Worker `worker-1104326` is `--queue=ai,default`. CareerForge `default` alone would 500 this check. | None. | No |
| P2 Workspace retrieval cap | Local closed | Workspace raises per-article cap to uniquely covered asked-part headings (capped by `retrieval.limit`). Harbor stays 2. `WorkspacePreviewRetrievalTest`. | None. | No |
| P2 Bakeoff length vs ceiling | Closed | Concise `toBeLessThanOrEqual(80)`. Filter rerun once, 3/3. | Do not rerun. | No |
| P2 Skip link / invoices h1 / Lucid title / 390px / wrap | Local closed; Forge titles/skip/footer closed | Layouts, skip-link, demo-chrome “Try” vs “Try the workspace”, wrapping mode chips. | iPad still **You**. | No |
| P3 Lasting rules | Local closed | `.ai/rules` via `record-rule` (docs, config, evals, stress, jobs, partials). No new skill. | Keep using `record-rule`. | No |
| P3 Live Forge verification | Closed for deployed workflows; Client still open | Deploy `2d6943d`, Confirm/Cancel, Sources, GET ladder, chat TTFB after-fix. Device/file-picker checks still **You**. | You run invoice, Preview upload, mic, iPad before those demos. | **Client** for remaining device demos |
| P3 Chat TTFB track | Closed | Pre-fix Forge 9.7 s was 1-worker queueing. After-fix on `2d6943d`: warm n=1 1.37 s; conc 4 still serialized at 1.36–6.38 s. Local 1.3–1.5 s. Targets ≤ 2.0/5.0/8.0 s. | None. Do not add workers. | No |
| Manual-reply dialog focus | Local closed; CI Open | CI **40394933464** failed because `$this->js()` focused `#ticket-reply` before Flux opened the dialog. Fix: rAF until `dialog[open]`. Browser test passed twice locally. | Push after approval. | **Landing** |
| Unique/`tries` | Closed (no change) | 120s listen death was Symfony Process wall-clock on `queue:work --once`, not a Laravel job timeout, not a Cursor timeout. | Do not raise limits. | No |

## Local quality (this machine)

| Check | Status | Evidence | Next action | Blocks |
| --- | --- | --- | --- | --- |
| `composer test` (Pint, Rector dry-run, Larastan, Pest exclude stress/evals) | Closed | Passed after the dialog-focus change: 430 passed, 1 skipped. Pint/Rector/Larastan green. | Re-run only if PHP changes after this file. | No |
| `composer test:evals --filter="follows tone and length"` | Closed | 3/3 after the retrieval + ceiling change. | Do not rerun. | No |
| Local GET Stressless smoke | Closed | 2026-10-26 `STRESS_URL=https://supportflow-ai.test`, 4 tests, 0 failed. | None. | No |
| Local GET load/stress/stability/capacity | Not run this pass | Prior 2026-09-19 numbers in [performance.md](performance.md). Required progressive sequence ran on Forge. | Optional locally. | No |
| Local chat TTFB waves 1/2/4 | Closed | After embed batch (earlier): 1.47 s / 1.23–1.32 s / 1.31–1.47 s. Confirm after dialog-focus (no chat-path change), 7/7 done: first n=1 2.56 s (cold); warm n=1 **1.28 s**; n=2 **1.83–2.34 s**; n=4 **1.17–1.60 s** (Herd FPM overlaps; Forge does not). | None. | No |
| Local `queue:listen --queue=ai,default` | Running at last check | Listener pid 30344 since 2026-10-26 18:10Z. Intended local path is `composer run dev`. `--timeout=120` is Symfony wall-clock on each `--once` child. | Keep a listener up while using local `/up` or tickets. | No |
| 120s listen child exit | Closed (investigated) | `ProcessTimedOutException` at 18:09Z after screen lock. Limits unchanged. | Restart listen if it dies; do not raise timeout. | No |
| GitHub CI | Open on `origin/main` | **40394933464** failed on dialog focus. Local `TicketWorkspaceTest` passed twice after waiting for `dialog[open]` before focusing `#ticket-reply`. CI stays red until that fix is pushed. | Push after approval. | **Landing** |

## Deployed Forge verification

Target [https://supportflow-ai-lqojsjr5.on-forge.com](https://supportflow-ai-lqojsjr5.on-forge.com). Click-path: [manual-regression.md](manual-regression.md) Live Forge staging.

**Deployment for verification** = `main` is on site `3397748`, deploy log shows `git pull`, `npm ci`/`build`, `migrate --force`, `optimize`, `octane:reload`, `queue:restart`, no PHP-FPM reload, no ZDD macros.

**Readiness to show clients** = that deploy **plus** the rows below **plus** the device checks you still own.

| Check | Status | Evidence | Next action | Blocks |
| --- | --- | --- | --- | --- |
| Push / Forge deploy | Closed | `2d6943d` on site `3397748` (20:21:43Z). Deploy log: `git pull` `d93755f..2d6943d`, npm build 3.99s, nothing to migrate, `optimize`, reload workers, queue restart. No PHP-FPM reload. No ZDD macros. | None. | No |
| GitHub Actions Tests green | Open on `origin/main` | **40394933464** failed: focus raced Flux `dialog[open]`. Local fix uncommitted. Did not gate Forge. | Push after approval, then watch Tests. | **Landing** |
| Octane daemon `daemon-1104270`, 1 worker, `:8001` | Closed (reverted to 1) | `--workers=2` trial 22:19Z: chat n=2 1.46–1.54 s (was 1.44–3.07 s); n=4 1.60–3.29 s (was 1.86–6.92 s). GET `/` conc 4 p95 121→187 ms. GET load `so` 4456 KB/s for 1 s. Reverted `--workers=1`, `/up` 200. | Keep 1 on this 1 vCPU/1 GB box. Do not copy local 16. | No |
| Worker `--queue=ai,default`, database, sleep 3, timeout 120, tries 3 | Closed | `worker-1104326` pid 812748 since 18:54:42 (post-deploy `queue:restart`): `queue:work database --sleep=3 --daemon --quiet --timeout=120 --tries=3 --queue=ai,default`. `--daemon` is present vs docs; leave it. | None. | No |
| Heartbeat names `ai` and `default`, `CACHE_STORE=database` | Closed | `/up` 200. Env `CACHE_STORE=database`. Worker lists `ai,default`. | None. | No |
| Scheduler installed and recent | Closed | `/etc/crontab` every minute `php …/supportflow-ai-lqojsjr5.on-forge.com/artisan schedule:run` → `/home/forge/.forge/scheduled-2143100.log`. Log mtime **2026-10-26 19:06** (empty body is normal for a quiet `schedule:run`). | None. | No |
| `/up` 200 with DB up and SupportFlow worker | Closed | HTTP 200 after deploy and after GET capacity. | None. | No |
| One grounded chat + one ticket triage on Forge | Closed for one-turn quality; follow-up open | Harbor unused Trail Pack/box/prepaid: 30 days, tags, box not required, prepaid after portal approval, Return window citations, no `CITES:` in the UI. Ticket **SF-R50WE** triaged, customer status hid the draft, agent sent the reply. Persisted chat follow-up and mid-stream Stop were not retested on Forge this pass. | Optional: one follow-up turn and Stop if you demo those. | **Client** if you demo follow-up/Stop |
| Confirm + Cancel + Sources on Forge | Closed | Confirm **HR-5A6717**. Cancel **Nothing was saved.** SF-R50WE Sources after send: Return window, Box not required. | None. | No |
| GET Stressless on Forge | Closed for the ladder; flake explained | Smoke through capacity as before. After-check smoke 4/4 0 failed; `GET /` conc 4 × 15 s 503/503 HTTP 200. Origin status of the original 1 k6 fail is unknown (`access_log off`). | None unless you want origin access_log. | No |
| Chat TTFB on Forge | Closed on 1 worker | Same-session trial 22:17Z `--workers=1`: n=1 2.70 s; n=2 1.44–3.07 s; n=4 1.86–6.92 s. `--workers=2`: n=1 1.79 s; n=2 1.46–1.54 s; n=4 1.60–3.29 s. 7/7 `event: done` both. Reverted to 1 after GET-load swap-out. Earlier `2d6943d` warm n=1 was 1.37 s. | Keep 1. Overlap needs more RAM/CPU than this VPS. | No |
| Other vhosts on server `1228434` | Closed | `forge site:list`: CareerForge `3287475` and SupportFlow `3397748` only. | None. | No |
| CareerForge `worker-987181` | Paused (reversible) | `supervisorctl stop worker-987181:*` at 22:10Z. Status **STOPPED**. Process record not deleted. Swap used 269→203 MiB; MemAvailable 349→361 MiB. Restore: `sudo -n supervisorctl start worker-987181:*`. | Leave paused unless CareerForge queues need it. Do not delete. | No |
| CareerForge deletion / server rename | Out of scope | Unused, not deleted. Worker paused, site kept. | Separate approval. | No |

## Device and access checks (require you)

These stay **Open**. They are not passed.

| Check | Why this session cannot close it | Blocks |
| --- | --- | --- |
| Current invoice PDF extract | No file picker in this browser | **Client** for `/invoices` |
| Preview real upload → Ready → cited SSE | Same | **Client** for `/preview` |
| Live microphone (chat and ticket) | Needs OS/browser permission and speech | **Client** if you demo dictation |
| iPad / ~768–1024px real device | No iPad here | **Client** if you demo iPad |
| Optional Retry AI on leftover `ai_failed` tickets | Shared Agent desk still showed 1 AI unavailable | **Client** polish |
| Optional Forge chat follow-up and mid-stream Stop | One grounded turn and TTFB waves are not a follow-up or Stop | **Client** if you demo those |

## Intentional non-goals (do not reopen here)

Passwordless Agent desk, simulated send, caps/TTL, seeded Harbor catalog, regex injection gate as a demo control, estimated `AiRun` cost. No org tenancy, email, or evals-in-CI. Do not delete CareerForge or shared Lucid resources. Do not change unique/`tries`.

## Next action

1. Device check now: Preview real upload on Forge Your documents (`/demo/documents` or `/preview`). One of your files, ≤ 8 MB → Upload → Ready for answers → ask a question only that file can answer.
2. Then, in order: invoice PDF extract → chat mic → ticket mic → iPad → optional leftover AI-unavailable Retry → optional Forge follow-up/Stop.
3. Approve the uncommitted dialog-focus fix when you want CI green. CareerForge `worker-987181` is paused (not deleted). Octane is `--workers=1` after a reverted 2-worker trial (chat overlap worked; GET load swap-out did not). Do not resize. Do not raise the Octane Supervisor process count.

## 2026-10-28 checkpoint verification

The tables above retain the 2026-10-26 handoff evidence; current CI/deployment state is authoritative in GitHub Actions and Forge deployment history. Local `composer test` now passes: Pint, Rector dry-run, Larastan, 430 passed / 1 skipped, 3340 assertions. `npm run build` passed. All nine deployment-gate scenarios passed. Browser tests now use built assets instead of `public/hot`, preventing the development server from reloading pages during tests. No assertions were weakened or tests removed.

Forge push-to-deploy is verified off, and the installed deploy script matches `.github/forge-deploy.sh`. The main push must pass the tests job before its deploy job can invoke Forge; Forge independently checks the exact SHA and CI result. The deployment-hook response only acknowledges submission, so completion must also be checked in Forge and the deployed SHA verified over SSH.

Pre-push SSH confirmed the old release `2d6943d`, Lucid Octane `--workers=1`, and Lucid queue worker running. CareerForge worker `worker-987181` is running again (all three processes had matching uptime), consistent with the documented reboot autostart. Its earlier pause was not permanent; no worker settings were changed in this checkpoint. Device/upload/microphone checks remain open. The broad system-design audit and complete Lucid naming migration are follow-on work after this verified checkpoint.
