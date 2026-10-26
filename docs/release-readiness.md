# Release-readiness

Living status of Stages 0–7 and P0–P3. The showroom follow-on plan is the historical gate; this file is the operator checklist. Last updated **2026-10-26**.

Do not treat a local Feature pass as Forge verification. Do not treat a Forge deploy as readiness to show clients.

## Gates

| Gate | Meaning | Current status |
| --- | --- | --- |
| **Landing** | Code may be committed to `main` | Ready after you approve the proposed commits. Local `composer test` passed (428 passed, 1 skipped). Local GET Stressless smoke passed (4/4, 0 failed). |
| **Deploy for verification** | Code is on live Forge so the staging checklist can run | **Not started.** `origin/main` is still `0b39d67`. Site `3397748` has **push to deploy on for `main`**. A GitHub push webhook starts `git pull` immediately. GitHub Actions does **not** gate that deploy (no `deploy.yml`, `main` is not branch-protected). Tests.yml runs in parallel and a red check will not stop or roll back Forge. Do not push until you want that verification deploy. |
| **Client show** | Safe to walk a client through the showroom | **Not ready.** Needs the verification deploy, independent GitHub Tests green, Live Forge staging (including GET Stressless **and** chat TTFB/concurrency), and the device checks you still have to run. |

GitHub Flow in [CONTRIBUTING.md](../CONTRIBUTING.md) remains the standing rule. This landing is a direct `main` push only if you approve it. [.github/workflows/tests.yml](../.github/workflows/tests.yml) is unchanged (Pint, Rector `--dry-run`, Larastan, Pest excluding `stress` and `evals`, Playwright). To make CI gate deploys later: turn Forge push-to-deploy **off** and call `forge deploy` from Actions after Tests.

## Missing information and docs that could not be opened

| Need | What happened | Blocks |
| --- | --- | --- |
| Forge doc URLs `/docs/sites/nginx`, `/docs/resources/daemons`, `/docs/sites/deployments.html` | HTTP 404. Already recorded in [docs/forge.md](forge.md). Used Laravel Octane Nginx, Forge deployments, and Forge CLI instead. | No |
| Worker logs for 2026-10-23 13:17 `ProcessTicketIntake` cluster | Not on disk. Cause (stopped `ai` worker vs other) stays unconfirmed. `tries`/`uniqueFor` unchanged. | No. Do not change unique/`tries` without a measured cause. |
| Other Lucid sites on Forge server `1228434` | Needs `forge site:list` or SSH. Windows `forge ssh:test` hangs (ControlMaster). Native OpenSSH with `ControlMaster=no` works. | **You.** List sites before stopping anything beyond CareerForge `worker-987181`. |
| Live Forge `CACHE_STORE` / env this pass | Not inspected. Heartbeat and `queue:restart` need `database`, not `array`. | **Verify** after deploy. |
| Whether the machine slept during the 120s listen child | Screen lock observed; system sleep unconfirmed. | No. Limits not changed. |
| Bakeoff retrieved passage IDs from 2026-10-26 | Gone (`RefreshDatabase`). Feature proof replaced that gap. | No |
| GitHub Actions on this change set | Not run. Workflow fires on push to `main` in parallel with Forge. It does not gate the deploy. | **Verify** (quality). Does not sequence Forge. |
| HealthSmokeTest seed | Plan asked to seed it. That test hits live `STRESS_URL`, so a Pest seed writes the testing cache, not Herd/Forge. Feature tests seed. Live `/up` needs a running worker. | No |

## How to use the tables

**Status** is `Closed`, `Local closed`, `Open`, or `Out of scope`. Open items name who or what they need. They are **not** passed.

**Blocks:** `Landing` (commit/push), `Verify` (release verification after deploy), `Client` (walkthrough), or `No`.

## Replacement Confirm (why this is not a call-ID swap)

The plan said persist the provider `call_id` (`call_…` / `ToolCall.resultId`) and resume OpenAI with `Decisions::approve()`. Stage 4 already showed that was the wrong fix.

**What broke.** Confirm saved the Harbor request in PHP, then resumed the stored conversation. Laravel/AI `PendingApproval.id` is the tool-call `fc_…`. OpenAI `function_call_output` wants `call_…`. The live 400 was worse than that namespace mix-up: stored pauses replay **reasoning** blocks without the matching `function_call` items, so OpenAI returned `No tool call found for function call output` (Stage 4 used LookupOrder’s `call_AGgXr…`). Ticket **HR-C09B3B** was already saved; `ai_runs` 663 failed.

**What shipped.** Confirm and cancel no longer call OpenAI. `executeVisitorDecision` commits or cancels locally, writes the assistant line, completes the `AiRun`, and deletes the SDK conversation so a later turn cannot replay the broken pause. `storeProposal` still records `snapshot.provider_call_id` when the model returns one; Confirm does not send it.

**Verified**

- Feature `confirming a proposal saves a request without resuming OpenAI tool output`: `Http::preventStrayRequests()` + `Http::fake()`, `beginConfirm()`, `HR-…` request, proposal `confirmed`, `ai_runs` `completed` with `error` null, assistant body contains the reference, `conversation_id` null, `Http::assertNothingSent()`.
- Live Herd 2026-10-26 17:13:32, new case: **HR-5EFB44**, proposal `confirmed`, `tool_call_id` still `fc_050b98ef…`, snapshot `provider_call_id` `call_Wq9L833JvQPA0zGKaAXBkOcJ`, `ai_runs.id` **671** `completed`, payload `decision: approve`, `error` null.

Forge Confirm is still open (undeployed).

## Stages 0–7

| Item | Status | Evidence | Next action | Blocks |
| --- | --- | --- | --- | --- |
| Stage 0 unused `KnowledgeArticlePolicy` / `AiRunPolicy::viewRawPrompt` | Out of scope | Not called. Product gap, not this pass. | Leave. | No |
| Demo agent desk RBAC vs org tenancy | Closed as documented | Policies + gates in use; shared desk is intentional. Org/SSO is future product. | Do not add org tenancy here. | No |
| Ticket AI-unavailable leftover `ai_failed` | Open | Local rows: `5` `SF-10493`; `86–87` `SF-PLLRE`/`SF-QPVSE`; `89–92` `SF-ZQ9JS`/`SF-BOOA2`/`SF-VKBTJ`/`SF-GCV3A`. Retry restored 88 and 93 earlier. | Optional Retry AI from the Agent desk if the queue should look clean. Out of scope as a code change. | **Client** polish only |
| 2026-10-23 13:17 failed-job cluster | Open (cause) | Eight `MaxAttemptsExceededException`. No worker log from that minute. | Keep Retry AI. Do not change `tries`. | No |
| Preview tone/length one-file three-heading | Local closed | Feature: workspace returns Shipping, Returns, and Warranty. Harbor diversity stays at 2. Bakeoff filter passed 3/3 after ceiling assertion `≤ 80`. | No eval rerun. | No |
| `/up` boot-only | Local closed, Forge open | Feature 500s for missing/stale/wrong-queue/DB-down. Live Herd `/up` 200 with heartbeat `{ai, default}` at 2026-10-26 18:36Z. | Forge `/up` after deploy. | **Verify** |
| GET Stressless (Stage 6) | Local smoke closed; Forge open | Local 2026-10-26: `/up`, `/`, `/knowledge`, `/knowledge/return-window`, concurrency 2, 5s, 0 failed. Load/stress/stability/capacity not rerun this pass. | After deploy, off-peak Forge smoke first, then the rest. Stop `worker-987181` if it contends. | **Verify** |
| Chat TTFB / concurrent `POST /chat/stream` | Open | Required release verification. Docs still cite 2026-09-19 waves; those do not close the current-path check after throttle 30. GET smoke and one grounded turn do not close it. | Local concurrent `POST /chat/stream` with separate cookie jars, then the same on Forge after deploy. | **Verify**, **Client** |
| Invoice extract current file | Open | No file-input in this browser. Historical INV-1001 and faked Feature tests do not count. | **You:** pick a current PDF on `/invoices`. | **Client** for that demo |
| Preview visitor upload | Open | Bakeoff used `UploadedFile::fake()`. Stage 1 “not live-tested.” | **You:** real picker → Ready → cited SSE. | **Client** for that demo |
| Encoded/base64 and multilingual jailbreaks | Open (verification-only) | Classified; never live-run. | Stay verification-only. Do not live-run. | No (do not claim them) |
| Skip link, one h1, Lucid title, 390px wrap, wrapping chips | Local closed | Feature `LucidShellTest` / `LandingPageTest`. Visitor titles use Lucid in `head.blade.php`; `APP_NAME` stays SupportFlow AI. | iPad still open below. | No |
| iPad viewport | Open | Stage 3 blocked. | **You:** ~768–1024px and a real device if you have one. | **Client** if you demo iPad |
| Live mic | Open | Automated dictation does not open a microphone. | **You:** Chrome permission + spoken transcript. | **Client** if you demo dictation |
| Local Herd badge covering footer | Closed (dispose) | Local-only, not Forge. | Ignore. | No |
| Phone Preview launcher closed | Closed (dispose) | Intentional. | Leave. | No |

## P0–P3

| Item | Status | Evidence | Next action | Blocks |
| --- | --- | --- | --- | --- |
| P0 Replacement Confirm | Local closed | Skip-resume, not a call-ID swap. Feature: no OpenAI HTTP. Live **HR-5EFB44** / `ai_runs` 671 completed. | Repeat Confirm on Forge after deploy (new case only). | **Verify** |
| P0 Sources after send | Local closed | Feature: TicketShow cites the approved reply. Live SF-PTERU-style Sources observed in the implementation session. | Repeat on Forge after deploy. | **Verify** |
| P1 Lucid footer / Privacy / Contact on `/preview` and `/invoices` | Local closed | Footer component + demo-environment links (no `wire:navigate` hash no-op). `LucidShellTest`. | Spot-check Forge after deploy. | **Verify** |
| P1 Refusal citations | Local closed | `ChatInjectionGate::isRefusal` expanded; SuggestedReply and Preview strip trailer citations. Regex gate unchanged as demo control. | Encoded/multilingual stay incomplete. | No |
| P1 Docs throttle 10 → 30 | Closed | [docs/performance.md](performance.md) matches `supportflow.rate_limits.chat_per_minute` 30. | None. | No |
| P1 `/up` heartbeat must list `ai` and `default` | Local closed, Forge open | `DiagnosingHealth` + `RecordQueueHeartbeat` (including `JobPopping` because `queue:listen` never fires Idle). Feature `HealthCheckTest`. | After deploy: live `/up` 200 and heartbeat payload. Do not take Postgres down. | **Verify** |
| P2 Workspace retrieval cap | Local closed | Workspace raises per-article cap to uniquely covered asked-part headings (capped by `retrieval.limit`). Harbor stays 2. `WorkspacePreviewRetrievalTest`. | None. | No |
| P2 Bakeoff length vs ceiling | Closed | Concise `toBeLessThanOrEqual(80)`. Filter rerun once, 3/3. | Do not rerun. | No |
| P2 Skip link / invoices h1 / Lucid title / 390px / wrap | Local closed | Layouts, skip-link, demo-chrome “Try” vs “Try the workspace”, wrapping mode chips. | iPad still **You**. | No |
| P3 Lasting rules | Local closed | `.ai/rules` via `record-rule` (docs, config, evals, stress, jobs, partials). No new skill. | Keep using `record-rule`. | No |
| P3 Live Forge verification | Open | Fixes are **not** on `origin/main`. | After you approve commits: push when you want the verification deploy (Forge starts immediately). Watch the Forge deploy log **and**, separately, GitHub Tests. Then [manual-regression.md](manual-regression.md) Live Forge staging off-peak. | **Verify**, **Client** |
| P3 Chat TTFB track | Open | Required sibling of GET Stressless. A single grounded turn does not close it. 2026-09-19 numbers do not close it. | Local concurrent `POST /chat/stream`, then Forge. | **Verify**, **Client** |
| Unique/`tries` | Closed (no change) | 120s listen death was Symfony Process wall-clock on `queue:work --once`, not a Laravel job timeout, not a Cursor timeout. | Do not raise limits. | No |

## Local quality (this machine)

| Check | Status | Evidence | Next action | Blocks |
| --- | --- | --- | --- | --- |
| `composer test` (Pint, Rector dry-run, Larastan, Pest exclude stress/evals) | Closed | Passed this session after the PHPStan `normalizeQueues` fix. | Re-run only if PHP changes after this file. | Landing (already green) |
| `composer test:evals --filter="follows tone and length"` | Closed | 3/3 after the retrieval + ceiling change. | Do not rerun. | No |
| Local GET Stressless smoke | Closed | 2026-10-26 `STRESS_URL=https://supportflow-ai.test`, 4 tests, 0 failed. Worker heartbeat present. | Forge GET after deploy. Do not copy 16 Octane workers. | No for landing; **Verify** for Forge |
| Local GET load/stress/stability/capacity | Not run this pass | Prior 2026-09-19 numbers in [performance.md](performance.md). | Optional locally. Required progressive sequence is on Forge after smoke. | No |
| Local `queue:listen --queue=ai,default` | Running | Listener pid 30344 since 2026-10-26 18:10Z. Intended local path is `composer run dev` (Herd already serves `https://supportflow-ai.test`; the listen child is what `/up` needs). `--timeout=120` is Symfony wall-clock on each `--once` child. Job timeouts need PCNTL (not on Windows). | Keep a listener up while using local `/up` or tickets. | No |
| 120s listen child exit | Closed (investigated) | `ProcessTimedOutException` at 18:09Z after screen lock. No job `RUNNING`. Latest `failed_jobs` is 2026-10-25 23:37. Limits unchanged. | Restart listen if it dies; do not raise timeout. | No |
| GitHub CI | Open | Unpushed. Workflow preserved. `main` is not branch-protected. | After push, wait for Tests **in parallel with** Forge. A red check does not undeploy. | **Verify** (quality, not a deploy gate) |

## Deployed Forge verification

Target [https://supportflow-ai-lqojsjr5.on-forge.com](https://supportflow-ai-lqojsjr5.on-forge.com). Off-peak. Full click-path: [manual-regression.md](manual-regression.md) Live Forge staging.

**Deployment for verification** = `main` is on site `3397748`, deploy log shows `git pull`, `npm ci`/`build`, `migrate --force`, `optimize`, `octane:reload`, `queue:restart`, no PHP-FPM reload, no ZDD macros.

**Readiness to show clients** = that deploy **plus** the rows below **plus** the device checks you still own.

| Check | Status | Evidence | Next action | Blocks |
| --- | --- | --- | --- | --- |
| Push / Forge deploy | Open | Branch is up to date with `origin/main` at `0b39d67`. Push-to-deploy is **on**. Forge does not wait for GitHub checks. | After commit approval, push only when you want the verification deploy. Confirm the deploy log (`git pull`, `npm ci`/`build`, `migrate --force`, `optimize`, `octane:reload`, `queue:restart`). | **Verify** |
| GitHub Actions Tests green | Open | Not run on this change set. Independent of Forge. | Watch Tests on the same push. Do not treat it as a deploy gate. | **Verify** |
| Octane daemon `daemon-1104270`, 1 worker, `:8001` | Open this pass | Last recorded in [forge.md](forge.md). | Confirm after deploy. Do not copy local 16 workers. | **Verify** |
| Worker `--queue=ai,default`, database, sleep 3, timeout 120, tries 3 | Open this pass | Documented. Not re-inspected. | Confirm process table after deploy. | **Verify** |
| Heartbeat names `ai` and `default`, `CACHE_STORE=database` | Open | Local Herd only. | Tinker/cache or log after deploy. CareerForge `default` must not satisfy this. | **Verify** |
| Scheduler installed and recent | Open this pass | File lists prune/purge commands. Need Forge job recent, not only PHP. | Check Forge scheduler after deploy. | **Verify** |
| `/up` 200 with DB up and SupportFlow worker | Open | Must not take Forge Postgres down (Feature covers 500s). | Hit live `/up` after deploy. If 500 from CareerForge starve, stop `worker-987181`. | **Verify** |
| One grounded chat + one ticket triage on Forge | Open | Quality, not TTFB, not GET Stressless. | Manual-regression after deploy. | **Verify**, **Client** |
| Confirm + Sources on Forge | Open | Local Feature + prior live session only. | New replacement case; send a ticket and check Sources. | **Verify**, **Client** |
| GET Stressless on Forge | Open | Do not shrink for CareerForge. GET does not close chat TTFB. | Smoke first, then load/stress/stability/capacity at the 16-VU start rail. | **Verify** |
| Chat TTFB on Forge | Open | Required. Needs a current-path local bench first (2026-09-19 is historical). | Concurrent `POST /chat/stream` after local numbers exist. GET Stressless does not close this. | **Verify**, **Client** |
| Other vhosts on server `1228434` | Open | Uncertain. | **You:** `site:list` / SSH before stopping more than `worker-987181`. | **You** before extra process stops |
| CareerForge deletion / server rename | Out of scope | Unused, not deleted. | Separate approval. | No |

## Device and access checks (require you)

These stay **Open**. They are not passed.

| Check | Why this session cannot close it | Blocks |
| --- | --- | --- |
| Current invoice PDF extract | No file picker in this browser | **Client** for `/invoices` |
| Preview real upload → Ready → cited SSE | Same | **Client** for `/preview` |
| Live microphone (chat and ticket) | Needs OS/browser permission and speech | **Client** if you demo dictation |
| iPad / ~768–1024px real device | No iPad here | **Client** if you demo iPad |
| Encoded and multilingual jailbreaks | Stay verification-only; do not live-run | No |
| Concurrent chat TTFB bench (local, then Forge) | Timed POST `/chat/stream` with separate cookie jars; OpenAI cost. Required, not optional. | **Verify**, **Client** |
| Forge `site:list` / SSH | Your Forge CLI/SSH | Before stopping processes beyond CareerForge worker |
| Approve commits, push, and the verification deploy | Explicit hold | **Landing** wait; **Verify** after you push |
| Optional Retry AI on leftover `ai_failed` tickets | Shared Agent desk will show them | **Client** polish |

## Intentional non-goals (do not reopen here)

Passwordless Agent desk, simulated send, caps/TTL, seeded Harbor catalog, regex injection gate as a demo control, estimated `AiRun` cost. No org tenancy, email, or evals-in-CI. Do not delete CareerForge or shared Lucid resources. Do not change unique/`tries`.

## Next action

1. You approve or edit the proposed commits (this session will not commit until then).
2. Commit locally.
3. Push to `main` only when you want Forge to deploy for verification. That push starts Forge immediately and starts GitHub Tests in parallel. It is not client-ready.
4. Confirm the Forge deploy log. Separately wait for GitHub Tests (they do not gate step 3).
5. Run Live Forge staging off-peak: GET Stressless smoke-first **and** chat TTFB/concurrency (local current-path numbers first).
6. You run the remaining device checks before any client walkthrough.
