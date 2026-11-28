# Performance

## Package 5 local load, chat and memory verification — 2026-11-27

On the isolated one-worker Octane/FrankenPHP stack, Pest Stressless passed all 19 smoke, normal load, higher load, stability and capacity cases. The run covered 35,469 read-only GETs with zero failures. All three representative pages met the 500 ms p95 budget at 24 virtual users (420 ms home, 437 ms knowledge index, 370 ms return-window article). At 32, their p95 values were 580, 596 and 513 ms. Latency set the limit in all three ladders. See the [Stressless measurement documentation](https://pestphp.com/docs/stress-testing).

Separate one-shot Harbor tests completed all eight live answers with sources. The first visible delta was 2.082 s on the initial sample and 1.458 s on the warm sample. The slowest first delta was 2.672 s for two overlapping visitors and 5.892 s for four. The warm and overlapping samples met the existing 2/5/8-second gates.

Four worker samples taken across the GET and chat tests were 4.70, 2.53, 3.45 and 3.90 MiB. App container memory stayed between 260 and 267.3 MiB in those samples, with zero container restarts and no OOM kill. The worker request counter advanced from 2,722 to 35,646 with `--workers=1 --max-requests=500` confirmed. There was no independent worker-recycle count. This is a bounded local observation, not extended production memory evidence. See [Octane memory guidance](https://laravel.com/docs/13.x/octane#managing-memory-leaks) and [FrankenPHP worker telemetry](https://frankenphp.dev/docs/metrics/#threads-state-endpoint).

The prior fixed-guide and live SQL samples remain 16–17 and 29–31. Reusing fixed-guide policy data could remove one source-label query, but this batch did not change the answer or citation path for that projected saving. No shared session, cancellation or production configuration changed.

## Package 5 Harbor final-save serialization — 2026-11-27

Harbor uses a short [Laravel transaction with a PostgreSQL row lock](https://laravel.com/docs/13.x/queries#pessimistic-locking) on its conversation. The final cancellation read and assistant insert share the lock with visible-text Stop and New chat. The model stream and retrieval run outside that lock. The former stream-level lock still controls concurrent sends and cancellation still uses PostgreSQL.

On local Octane/FrankenPHP, three fixed-guide answers used 17, 16 and 16 logged SQL statements, up from 14. They now have one additional cancellation read and one explicit row-lock statement; the query log measures statements and omits transaction boundaries. Three live answers used 29, 30 and 31 logged statements with sources. The live path added the row lock and already had the final state read; variation in provider events can mask its cost. These samples do not quantify latency or throughput impact under contention.

## Package 5 Harbor fixed-guide query breakdown — 2026-11-27

One whole-request trace of the fixed Trail Pack question on the isolated local Octane/FrankenPHP stack with the Redis send limiter used 14 PostgreSQL statements. Seven covered the framework session, visitor, chat conversation and messages. Five covered cancellation cache and stream locks. Two loaded policy chunks and source titles. The cited answer completed without a model call or candidate search. The fixed path is a useful control for evaluating session and message savings that may also help live answers. The temporary counter reported operation and table names only and was removed from source.

The seven session and chat statements were a Laravel session read and save, a demo visitor lookup, a conversation lookup, a user message insert, a read for the latest question and turn cap, and an assistant message insert. This sample had no separate visitor heartbeat write. The [Laravel session driver](https://laravel.com/docs/13.x/session#configuration) is application-wide, and the web stack uses session state for [CSRF token verification](https://laravel.com/docs/13.x/csrf#preventing-csrf-requests). Moving the two framework statements to Redis would require a separate session and outage trial. No redundant read was identified in these seven statements. A narrower candidate is reusing the fixed guide's loaded policy data for the stream's source labels.

## Package 5 Harbor final cancellation check — 2026-11-27

Normal completed Harbor streams use one fresh PostgreSQL cancellation check after final answer preparation. Regular answers and provider refusals share that save path. Replacement-tool turns and interrupted streams keep their immediate post-stream check. The surviving final check runs after optional overview work, closer to the assistant insert.

Three cited live replays on local Octane/FrankenPHP with the Redis send limiter used 31, 30, and 29 PostgreSQL statements. The prior samples used 31, 29, and 32. The code removes one read on a normal completion. Provider event counts vary and these samples do not establish a latency improvement. Three fixed-guide runs still used 14 statements.

Added Stop and New chat cases for a streamed refusal to the existing interruption regression. All 46 focused tests passed with 305 assertions. Full `composer test` passed with 1,123 passed, four optional skips, and 11,380 assertions. Removed the duplicate refusal save branch and the temporary query counter. Verified the clean local Docker build returned cited answers with the counter absent. Existing answer, citation, replacement and interruption tests still cover active behavior.

## Package 5 batched vector and text candidates — 2026-11-27

The candidate search now sends lexical and vector branches in one PostgreSQL statement. Each branch retains its own corpus filters, Laravel [vector distance and similarity clauses](https://laravel.com/docs/13.x/queries#vector-similarity-clauses) or lexical score, and ordered candidate limit. The vector subqueries retain the [pgvector ORDER BY and LIMIT shape](https://github.com/pgvector/pgvector#querying). Bound Laravel subqueries return each ordered list separately for fusion. Duplicate lexical inputs share a subquery and keep their existing fusion contributions. Final passage hydration, grounding, and cancellation were left alone.

Four before/after prompts on local Docker returned identical ordered passage IDs, including the representative return question, multi-part returns, cross-policy comparison, and duffel care. With local Redis chat rate limiting, three real Harbor replays used 31, 29, and 32 PostgreSQL statements, each with one candidate SQL statement and a cited completion. The prior live range was 32–33 and event counts vary. Fixed guide controls stayed at 14, as they bypass candidate search. Aggregate-only instrumentation was removed from source and the local app image.

Removed the old per-search vector and rank helpers and the separate lexical batch API. Updated the database-round-trip regression to cover vector and text together and added a vector-only test with an empty lexical variant. Existing tests still cover current behavior. The focused suite passed (105 tests, 546 assertions), all 12 live Harbor grounding cases passed (58 assertions), and the live two-document workspace test passed on all three models (six assertions). Full `composer test` passed (1,121 passed, four optional skips, 11,376 assertions). The 10-query target remains open, with stream coordination still on PostgreSQL.

## Package 5 Harbor controls during a local Redis outage — 2026-11-27

Before the fix, two real local Octane browser exercises confirmed Redis was offline while Harbor was streaming. Both Stop and the second-tab New chat action returned HTTP 500. The installed Livewire 4 checksum guard consults Laravel's default rate limiter before component actions. The former global Redis limiter configuration therefore made those controls depend on the optional chat rate-limiter service.

Scoped the optional Redis store to the Harbor and Ask Lucid send routes with Laravel's [named throttle middleware](https://api.laravel.com/docs/13.x/Illuminate/Routing/Middleware/ThrottleRequests.html) and [cache store selection](https://laravel.com/docs/13.x/cache#accessing-multiple-cache-stores). Both routes still share the same IP/session limits. The framework's default limiter and checksum guard continue to use the regular cache. Removed the superseded global override and updated the existing route-coverage assertion. Added tests for the separate store with the shared send budget and two interruption cases when the chat limiter store cannot resolve. Focused tests passed (61 tests, 458 assertions).

After the fix, Edge verified Redis was stopped while visible text was streaming. Stop returned HTTP 200 and the reloaded thread displayed the stopped status without a completed assistant answer. The post-recovery follow-up completed with a citation through normal Harbor Send. In a separate session, the second-tab Livewire New chat action returned HTTP 200 during the outage, a reload showed the cleared thread, and a cited follow-up completed after Redis recovered. Test scripts were run inline; Redis was restored after each outage. Full `composer test` passed every stage (1,120 passed, four optional skips, 11,373 assertions). No service was deployed.

Cancellation state and stream locks remain on PostgreSQL. This test verifies the current separation during an outage and does not establish safe operation when the cancellation store itself is unavailable. No new SQL count was captured in this outage run; the latest measured 32–33-statement range remains the prior baseline.

## Package 5 batched Harbor and workspace text retrieval

Lexical retrieval now sends its query variants in one PostgreSQL statement. Each Laravel subquery keeps the existing corpus filter, bound search terms, score order and candidate limit. PostgreSQL's [ordered JSON aggregate](https://www.postgresql.org/docs/18/functions-aggregate.html) returns the ranked IDs for each list. Fusion still receives distinct lists in their original sequence. Vector queries, embedding behavior, passage selection and citation formatting are unchanged.

Removed the superseded per-variant text-search helper. The existing tests still cover supported behavior, so none were retired. A new regression test verifies that a multi-part Harbor prompt runs its lexical variants in a single SQL statement and still retrieves the box and label headings. The focused suite passed (102 tests, 537 assertions), and twelve real Harbor grounding evaluations passed (58 assertions). Static checks passed after using Laravel's bound subquery builder instead of concatenating compiled SQL into a raw expression.

Full `composer test` passed (1,117 passed, four optional skips, 11,364 assertions). With Redis rate limiting on local Octane, three real replays of the same Harbor question used 33, 32 and 32 PostgreSQL statements. Each used one lexical SQL statement and completed with citations. The three fixed guide controls each used 14 statements and no lexical SQL. Comparison is to the previous live range of 32–35, which varied with stream events. Temporary aggregate instrumentation was removed and the clean image rebuilt with the local Redis limiter still active.

## Package 5 Harbor citation label query

The Harbor SSE completion uses Eloquent's [relationship subselect API](https://api.laravel.com/docs/13.x/Illuminate/Database/Eloquent/Concerns/QueriesRelationships.html#method_withAggregate) to read chunk IDs, headings and article titles in one statement. The shared citation helper still groups and orders the same saved IDs. The projection avoids loading full text, embeddings and full article records for these labels. Retrieval, persistence and cancellation were unchanged. Pint, Rector, PHPStan and 60 focused tests (395 assertions) passed.

With local Redis rate limiting, fixed guide answers used 14–15 PostgreSQL statements versus 15. Real answers used 32–35 versus 35–36. The single query removed is the separate article load for SSE labels. Fresh session activity and number of streamed events varied. One 32-statement trace included 11 cache reads or writes, two lock statements, eight knowledge statements, two AI-usage writes, and nine session/conversation/message statements. Cited completions passed in every measured sample.

Some local streams reported 2.0–4.0 s of aggregate SQL time. Follow-up probes found no container restarts or OOM and no persistent PostgreSQL wait in the idle snapshot. A subsequent live replay reported 67 ms of SQL time, so the outlier's cause is unproven. Keep this open before using these samples to claim a latency gain. Removed the temporary aggregate-only probe and kept the local Redis rate-limiter override active.

## Package 5 Redis restart and Harbor cancellation state

Tested disposable Redis instances on the local Docker network with the same digest-pinned Redis image as the limiter. The probe used Laravel's [Redis cache store and owner-scoped locks](https://laravel.com/docs/13.x/cache#atomic-locks) to write fictional Stop and generation values plus an owned stream lock. After an abrupt process kill and restart, the in-memory configuration lost the state and let another owner acquire the lock. With [Redis append-only persistence](https://redis.io/docs/latest/operate/oss_and_stack/management/persistence/) configured to sync on every write, refuse truncated logs, and avoid eviction, the Stop flag, generation and lock survived. A different owner could neither acquire nor release the retained lock; the original owner could release it.

The first harness attempt was discarded because changing config after Laravel boot did not isolate the already-resolved Redis manager. The temporary keys from that attempt were removed. The corrected probe used its own Redis manager explicitly pointed at the fixture. Both test containers and their anonymous volumes were removed. The real local Redis limiter was not restarted and the app health check returned 200. No application or Compose configuration changed.

This proves recovery of acknowledged cache writes and owner-scoped locks in the tested process restart. It does not prove that an in-flight Harbor answer behaves correctly when Redis disconnects mid-stream, restarts between checks, or recovers without its data volume. Keep Harbor cancellation and locking on PostgreSQL until an end-to-end outage and recovery test verifies stale answers cannot be saved in those cases. The current local Redis limiter remains enabled.

## Package 5 Harbor turn reads with local Redis enabled

Harbor now uses the same conversation model to record and answer a question and gets the latest user message and turn count in one SQL statement. The count uses a [PostgreSQL window aggregate](https://www.postgresql.org/docs/18/tutorial-window.html) over user turns, before the latest-row limit, via Laravel's [selectRaw](https://laravel.com/docs/13.x/queries#selectraw). The chat cap, prompt history, ranking, citations, and cancellation checks retain their existing behavior. Focused checks passed (78 tests, 739 assertions).

With Redis rate limiting active on local Octane, three fixed guide responses each used 15 PostgreSQL queries versus 17 before. Three live responses used 35–36 versus 38. All six completed with citations. The guide result confirms two statements removed, and live totals vary with stream events. Temporary query instrumentation was removed and the clean local Redis override was left active. Pint, Rector and PHPStan passed. After simplifying redundant null-safe syntax flagged by PHPStan, the 24 stream and rate-limit tests passed again. The restored app health endpoint returned 200 with Redis still selected.

Stop, New chat and stream locks still use the database cache. The local Redis limiter is configured with persistence off, so it is not currently appropriate as the sole authority for cancellation during a Redis restart. Prove cancellation and lock behavior across a store outage or restart before considering that change. The local 10-query target and production validation remain open.

## Package 5 measured Redis limiter comparison

Compared the database limiter and dedicated Redis limiter on the same one-worker local Octane app image, using Laravel's [database query instrumentation](https://laravel.com/docs/13.x/database#listening-for-query-events) and the [documented limiter store setting](https://laravel.com/docs/13.x/rate-limiting#cache-configuration). Each arm ran three fixed guide answers and three real AI answers with fresh visitor sessions and the same prompts. The counter covered the HTTP request middleware and streamed response through completion. All 12 answers completed with citations.

- Fixed guide: 31 PostgreSQL statements per answer with the database limiter and 17 with Redis. All three saved 14.
- Live AI: 51–52 PostgreSQL statements per answer with the database limiter and 38 with Redis. These samples saved 13–14. Shared stream-state reads remained 12–13.
- Redis recorded 12 counter increments for its six responses. The change moves limiter traffic to Redis. It does not remove that backend work. Three samples per arm do not establish an end-to-end latency improvement.

Removed the temporary query instrumentation and rebuilt the default image. The opt-in Redis Compose override remains available for repeatable testing. This is local evidence only; production Redis, failover behavior, operating cost and hosted validation remain separate. The 10-statement target is still open.

## Package 5 isolated Redis rate-limiter trial

The opt-in Compose override uses Laravel's [dedicated limiter store](https://laravel.com/docs/13.x/rate-limiting#cache-configuration), the [official Redis Docker image](https://redis.io/docs/latest/operate/oss_and_stack/install/install-stack/docker/) pinned by digest, and [Compose override merging](https://docs.docker.com/compose/how-tos/multiple-compose-files/merge/). Only the web application's rate-limiter store was switched for the trial. Database sessions, general cache, stream locks, and the queue stayed on their existing stores. Redis published no host port and did not persist trial counters.

Under default one-worker Octane, a real Harbor chat completed with a source. One valid turn and 29 validation-only requests reached the current 30-request threshold. The next request received 429 with a retry header, and a fresh visitor on the same IP also received 429. A separate Edge Stop check returned HTTP 200. Redis recorded 60 increments for the 30 admitted requests, which confirms the two counters moved to Redis. These measurements did not capture a paired whole-request PostgreSQL query total. The prior 18 SQL statements describe the observed database-backed limiter trace, not an exact saving for every answer. One Docker stats sample reported 9.95 MiB for the Redis container.

Audited the installed Laravel route list and all manual RateLimiter callers. Removed unused named registrations for tickets, regenerate, and demo prune; the active Livewire checks for those actions remain. The Harbor and Ask Lucid named limiter shares a budget and checks both IP and visitor session. The IP ceiling covers new sessions on the same network; the session ceiling still follows a session when its IP changes. Neither was removed. Workspace chat, agent entry, dictation and the Livewire upload route retain their existing limits. Any decision to split the shared Harbor/Ask Lucid budget or change thresholds needs usage evidence and a separate policy review. Focused regressions passed (83 tests, 620 assertions).

Restored the default Docker stack and removed the trial container. The pinned override remains for repeatable testing. App, PostgreSQL, queue, scheduler and web were healthy afterward. The trial did not change Herd, Forge, or the production deployment. The installed-version documentation and cleanup requirement was consolidated in the existing project-wide verification rule.

## Package 5 Harbor history and local Octane verification

Harbor reuses the history already fetched for the handoff check to find the latest assistant message and previous safe user turn. The temporary collection is released before retrieval. No request history is retained in worker-level state. The model's own history read and cancellation checkpoints remain fresh. A new Herd trace (`436f30`) confirmed both old lookups were absent and the answer completed with a source. It recorded 55 total statements, including 11 state checks. Whole-request counts vary with session/cache lifecycle, so the removed statements are the verified improvement. Focused chat, grounding, returns, and replacement checks passed (89 cases, 692 assertions).

Rebuilt the default local Docker app. Octane used one FrankenPHP worker and the configured 500-request limit. Four-route smoke and 45-second stability stages passed with 12,327 read-only GETs and zero failures. All eight real chat streams completed with a citation. First visible delta was 3.196 s on the initial solo sample, 1.699 s on the repeat, at most 2.948 s for two and 6.765 s for four. The warm and overlapping samples met the documented 2/5/8 s targets.

FrankenPHP's worker memory counter measured 2.68 MiB before the chat waves, 4.79 MiB afterward and 3.31 MiB after stability. Docker app container memory at those points was 234.9, 248.4 and 253.3 MiB. Worker-thread telemetry reported 12,360 cumulative requests at the final sample. The app container had zero restarts and no OOM kill. These are short-run memory observations across traffic exceeding the configured recycling interval, with no independent restart counter collected. They do not establish long-duration memory stability. See [Octane memory guidance](https://laravel.com/docs/13.x/octane#managing-memory-leaks) and [FrankenPHP thread telemetry](https://frankenphp.dev/docs/metrics/#threads-state-endpoint).

Full host `composer test` passed every stage (1,116 passed, four optional skips, 11,360 assertions). The initial focused run caught an unsupported collection method; the supported last-match callback passed the focused rerun and full gate.

Of the 55 statements in the fresh Herd trace, 35 touched cache, cache locks, or framework sessions. The remaining 20 covered the demo session, conversations and messages, retrieval and citations, and AI usage. Even moving all 35 statements off PostgreSQL would leave 20 in this sample. Any shared store trial must measure total backend work and preserve session isolation, throttling, locking and queue behavior. No shared storage change was made.

The middleware trace had 18 cache statements, all matching rate-limit counter/timer pairs. This makes rate limiting a measurable candidate for an isolated comparison. Laravel 13 documents selecting a dedicated store through [the cache limiter setting](https://laravel.com/docs/13.x/rate-limiting#cache-configuration). The default Lucid Compose stack has no Redis service. An isolated Redis trial would require approval for that additional service. Measure SQL and Redis operations together and verify existing IP/session limits and Stop behavior before considering any broader rollout. This is an evidence checkpoint, with no application or configuration change.

## Package 5 Harbor text stream polling trial — 2026-11-26

Owner approved trialing a 100 ms cadence for shared state reads during ordinary Harbor text streaming. Use PHP's [monotonic clock](https://www.php.net/manual/en/function.hrtime.php) to measure elapsed time. The first text event, non-text events, client aborts, replacement-tool turns and the existing final persistence checkpoints still make fresh checks. This limits reads during a burst of text events. It does not schedule an independent timer while the provider is silent. Prompt, retrieval, citation and provider settings were unchanged.

Three live equivalent Harbor prompts all completed with citations, using 53–54 SQL statements and 11 state reads per sample versus 98 and 51 on the prior trace. Database time was 81–82 ms versus 96 ms on that single prior sample. One fresh Edge Stop probe before and after returned HTTP 200 for the Stop action; the Stop control disappeared in approximately 319 ms and 305 ms respectively. These single click-to-hidden observations include browser polling, do not measure INP or the exact server cancellation time, and do not establish a user-visible speed improvement. Focused tests passed (68 cases, 515 assertions), including added cases that interrupt after text has been emitted and verify Stop or reset prevents a completed answer from being saved.

The full host `composer test` gate passed with 1,116 tests, four optional skips, and 11,360 assertions. The initial gate stopped at Rector's typed-constant requirement; the corrected rerun passed every stage. Findings apply to Herd and the host test suite. Updated Docker measurements and Forge validation are pending.

The local query target remains 10. The next candidates are database-backed session/cache middleware and duplicate history reads. Laravel [supports a dedicated rate-limiter store](https://laravel.com/docs/13.x/rate-limiting#cache-configuration). Review its impact independently before changing shared storage or search ranking.

## Package 5 Harbor query accounting — 2026-11-26

Target for a normal Harbor answer: 10 or fewer PostgreSQL statements, with fewer preferred if answer quality, citations, visitor isolation, Stop and New chat remain reliable. This is an owner target for Lucid. Count the whole request, including middleware.

One fresh real Herd answer for the same fictional return-window question returned HTTP 200 and completed with a source. Herd trace `018893` recorded 98 SQL statements and 96 ms of database work. Breakdown: 51 repeated stream-state reads, four other stream coordination statements (generation read, lock acquire/release and Stop flag cleanup), 20 framework session/cache statements, two demo-session statements, ten chat-history and message statements, nine retrieval and citation statements, and two usage-accounting writes. Only aggregate counts are retained here. Comparison with the earlier 76-query answer shows why query counts must be interpreted with the number of stream events.

Even removing all 51 repeated checks would leave 47 statements in this trace. The 100 ms trial alone cannot meet the target. Next review the database-backed middleware/cache operations and repeated conversation/history reads, then quantify safe ways to reduce retrieval round trips without changing ranking or evidence. Measure browser Stop and server cancellation before changing check frequency. No application code, configuration or benchmark timing changed in this accounting pass.

## Package 5 retrieval query cleanup — 2026-11-26

Removed unused article eager loads from vector and lexical candidate ranking. Final ranked passages still load articles for selection and citations. The ranking queries, corpus filters and citation rules are unchanged. Focused retrieval, workspace, reply and grounding checks passed (99 tests, 529 assertions). A real Harbor replay completed with a source and no raw citation marker. Fresh Herd Insights through cursor 875 no longer flagged repeated article hydration and reported 76 total queries, including 29 stream-state reads, versus 99 and 48 on the previous sample. Stream event counts varied across the two answers, so the total difference cannot be attributed entirely to the retrieval change.

Stream-state read frequency is still open. [NN/g's 100 ms perceived-response guidance](https://www.nngroup.com/articles/response-times-3-important-limits/) and [Google's 200 ms INP benchmark](https://web.dev/articles/inp) address user-visible responsiveness. They do not specify a polling interval or guarantee server-side stream cancellation time. Establish a click-to-visible-Stop and server-cancellation baseline before evaluating a polling change, and verify stale streams cannot save after Stop or reset.

## Package 5 Harbor stream state reads — 2026-11-26

Harbor reads the Stop flag and stream generation together on each cancellation check using Laravel's cache multi-read. The installed database cache store fetches both keys in one query. Each check still reads shared state, and generation mismatch keeps precedence. The obsolete private single-check helpers were removed.

One live Herd replay of the baseline Harbor question completed with a source and no raw citation marker. Fresh Insights through cursor 720, with SQL examples off, reported 99 total queries versus 147, including 48 combined state reads versus 97 separate reads. Reported database time was 122 ms versus 264 ms across the two individual traces. Provider time varied, so this comparison establishes reduced database work without establishing an end-to-end latency improvement. Retrieval and article hydration remain visible in the trace.

Focused Harbor stream, conversation and rate-limit tests passed (50 tests, 538 assertions), including Stop and stale-stream reset. Full host `composer test` passed all stages (1,114 passed, four optional skips, 11,356 assertions). The post-change live replay was on Herd. Docker and Forge measurements above or below this checkpoint do not validate the updated code.

## Package 5 local chat and query baseline — 2026-11-26

Default one-worker Docker, real Harbor chat, independent visitor sessions and one fictional question per stream. All eight streams completed with a source and without raw citation markers. First visible SSE delta: initial solo 3.557 s, warm solo follow-up 1.103 s, two visitors 1.812–3.783 s, four visitors 1.341–7.405 s. The four-visitor wave completed in 1.774–7.796 s, with first deltas following earlier completions, consistent with serialization. The warm solo and overlap samples met the recorded 2/5/8 s targets locally.

Herd HTTPS succeeded using Windows PowerShell certificate validation after Node rejected the local certificate. Fresh site-scoped Insights after cursor 192, through cursor 504, with SQL examples off: Harbor 9 queries (52–71 ms DB), Documents 3 (44 ms), Invoices 3 (47 ms), Agent tickets 6 (68 ms), Knowledge 4 (45 ms). Agent entry redirected as expected with 15 queries (55 ms). One separate real Herd chat completed with a source and recorded 147 queries (264 ms DB), including 49 and 48 repeated reads at the two ChatService state-check sites, three lexical retrieval calls and repeated article hydration. Herd request elapsed time is not used as SSE timing.

Next: investigate the repeated stream-state reads first and verify concurrent Stop and generation changes remain visible before adopting any optimization. Recheck retrieval behavior and citations if retrieval queries change. No application code or runtime settings changed in this measurement batch. Forge and release verification remain pending.

## Package 5 local Docker GET baseline — 2026-11-26

The first four-route Stressless smoke passed (1,676 GETs, zero failures, 3.54 s maximum). On the default one-worker Docker stack, normal load, higher load, stability and capacity then passed (15 cases, 27 assertions, 31,804 GETs, zero request failures). The earlier long stall did not recur during those four stages.

The capacity ladder used the previous local Octane benchmark of 500 ms p95 with the existing zero-failure check. All three pages stayed within budget at 24 concurrent virtual users: home 425 ms, knowledge 445 ms, return window 377 ms. Each stopped at 32 after exceeding budget: 622 ms, 552 ms and 529 ms respectively. These are separate per-route plateaus on Docker. The capacity run reports the stopping reason and optional p95 budget using `STRESS_CAPACITY_P95_MS`; availability-only runs omit it. See [Pest Stress Testing](https://pestphp.com/docs/stress-testing) for the measured result fields.

Next: capture current chat streaming and query baselines. Forge capacity and release verification remain pending.

## Package 5 Docker preparation — 2026-11-26 (setup validated)

Owner authorizes local Docker setup and handoff preparation only. No application dependencies, business/UI code, CI/deployment gate, Herd `.env`/database, or production configuration is changed. Default Compose project `lucid-local` publishes only `127.0.0.1:8088`; PostgreSQL has no host port. Source/vendor/assets are copied into Linux images, not mounted from Windows. Named volumes persist application data, cache and PostgreSQL; test services use a separate database and volumes. `.env.docker` is ignored, generated with a separate key/random local DB password and the existing local OpenAI key, and excluded from build contexts. No production data or credentials copied.

Actual production component reads and actual running Docker versions agree: Laravel 13.42.0 / Octane 2.19.1 (locked), PHP 8.5.13, FrankenPHP 1.13.6 / Caddy 2.11.9, PostgreSQL 18.4, pgvector 0.8.6 and Nginx 1.31.9. Node build stage 22.23.4 matches the production build tool. Base images are digest pinned. PostgreSQL is the official 18.4 Debian image with pgvector 0.8.6 compiled in a separate stage using the maintainer's portable `OPTFLAGS=""` instructions; the compiler is absent from the final DB image. Runtime extensions needed by the app are installed; unused production services/extensions are not represented as an exact OS clone. Docker uses Debian Bookworm versus Forge Ubuntu 24.04, its PHP CLI is ZTS versus Forge CLI NTS, and host hardware/resource limits differ. This is runtime/version parity, not proof of equal throughput or a full VM replica.

Default Octane: one worker, 500 requests, 120-second application execution limit, upload 12M/post 68M. Separate queue: database, ai/default, sleep 3, timeout 120, tries 3, memory 128, retry_after 150; scheduler runs schedule:work locally. Explicit development override uses one request/worker plus Compose rebuild watch. Do not benchmark that mode. Herd/Vite refresh remains unchanged. The Nginx proxy uses Docker DNS resolution so app recreation does not leave a stale upstream IP; streaming buffering is off and timeout 120 seconds. The app runs as www-data with writable storage/cache only. HTTP `/up` checks the actual database and queue heartbeat; non-web commands disable the image's inherited HTTP health probe.

Verified so far: clean Compose validation for both modes, image builds, migrations, fresh fictional seed (22 articles, 33 embedded chunks, five seeded tickets), real cited support chat, sample submitted ticket reaching awaiting_review through the real worker (zero pending/failed jobs), scheduler activity, named-volume marker persistence and retained counts through app/queue/scheduler/web recreation (six tickets after the sample), seven final routes HTTP 200. Temporary marker removed. Nginx syntax check passes. Initial IAB module globals were undefined on both Docker and the unchanged production baseline, so IAB console observations alone do not establish a new app regression or full browser acceptance; real Chromium tests remain the authority. No microphone capture attempted.

Final exact Linux composer test passed every stage: config clearing, Pint, Blade formatter, Rector dry-run, PHPStan and 1,114 regular tests/four optional skips/11,356 assertions (690.71 seconds). The test service adapts only DB host/user/password in the image's PHPUnit file; host phpunit.xml remains untouched. Validation history retained: host full run had one invoice timeout (1,113 passed/four skips), then the six-case affected scenario passed (357 assertions). An earlier Linux run had one New chat click timeout (1,113 passed/four skips); unchanged isolated rerun passed (seven assertions), and the final complete Composer run also passed that case. First interrupted Linux attempt needed the test-only .env.example fixture; runtime image still contains no .env, .env.docker, node_modules or public/hot. No assertion or timeout was weakened.

Initial two-concurrent-user read-only Stressless smoke failed: /up 2,198 successful requests (p95 7.42ms), home 391 (p95 37.29ms), knowledge index 84 (p95 75.82ms, max 11,155ms; 35-second elapsed instead of five). Return-window smoke produced no http_reqs summary and the runner errored. App logs showed intermittent tens-of-seconds requests, including one cold home ~108 seconds. After builds/tests were idle, both 127.0.0.1 and original localhost four-route smoke runs passed (four tests/eight assertions each, zero failed requests). Original-hostname p95: /up 4.22ms, home 42.54ms, knowledge 63.24ms, return window 48.38ms; worst request 464.81ms. Worker recycling occurred successfully. No container restart, OOM-limit failure or cgroup CPU throttling observed. This establishes a passing smoke, not the cause of the prior transient or capacity acceptance. Root cause remains unproven; retain this evidence and investigate if it recurs during Package 5 baselines. Higher load was not run in setup.

Development override startup and three ordinary page GETs passed with actual max-requests=1; restored default max-requests=500 afterward and confirmed healthy services. Compose Watch configuration is valid, but an edit-triggered automatic rebuild has not been exercised end to end. This is optional editing tooling, not a measurement requirement.

Final post-restoration/post-suite localhost smoke also passed all four tests/eight assertions, zero failures: /up 2,221 requests, home 412, knowledge 313, return window 344; p95 7.95/35.75/36.66/49.34ms, worst request 361.12ms. No higher load followed. Final proxy syntax check passed; application data retained 22 articles/six tickets/vector 0.8.6. Runtime services healthy, PostgreSQL test service stopped. Evidence: storage/logs/docker-linux-composer-final.log and docker-stress-restored-final.log (ignored local files).

Cleanup: removed the temporary persistence marker and abandoned initial startup/test-attempt logs; meaningful failure/final evidence remains in ignored storage/logs/docker*.log. No ephemeral test runner remains; test PostgreSQL stopped after validation, with its isolated volume preserved. App PostgreSQL, app, queue, scheduler and web remain running. No blanket Docker prune, data removal, commit, push or deploy. Earlier 150 owner artifacts remain for Package 6.

Next chat: read AGENTS.md, current readiness, audit and this section before Package 5. Run baseline smoke first against the Docker target, then the documented progressive read-only GET ladder and separate chat-stream timings; monitor the prior transient stall and stop/investigate if it recurs. Measure before application changes. Resource/OS differences mean local throughput does not predict the shared Forge VM. Hosted measurements retain their separate authorization/off-peak rules. Package 5 optimization/capacity acceptance is not completed by this setup batch.

Operational commands and safe teardown are in README. Official basis: https://laravel.com/docs/13.x/octane#frankenphp-via-docker ; https://frankenphp.dev/docs/docker/ ; https://docs.docker.com/desktop/features/wsl/best-practices/ ; https://docs.docker.com/compose/how-tos/startup-order/ ; https://docs.docker.com/compose/how-tos/networking/ ; https://docs.docker.com/compose/how-tos/file-watch/ ; https://github.com/pgvector/pgvector#installation ; https://hub.docker.com/_/postgres ; https://nginx.org/en/docs/http/ngx_http_proxy_module.html#proxy_pass .

SupportFlow measures HTTP capacity with Pest Stressless only. There is no raw k6 suite. Chat streaming is a separate one-shot `POST /chat/stream` client, never a repeating Stressless GET loop.

## Current local Herd GET verification — 2026-11-09

Ran the 21 previously gated Stressless cases against https://supportflow-ai.test only, sequentially: four smoke, five normal-load/runtime, six higher-load, three stability and three capacity cases. All passed; 20,591 total GET requests had zero failures. Scope was /up, /, /knowledge and /knowledge/return-window; no repeating chat/ticket/dictation writes, Forge traffic, server changes or Octane worker changes. Cookies persisted across iterations. Local PHP test runners used a process-only 512 MB memory allowance; application configuration was unchanged.

At four simultaneous requests, normal-load page p95 was approximately 211–218 ms; 45-second stability page p95 was approximately 214–220 ms. Capacity ladders used the existing levels 2,4,8,12,16,24,32,48,64,96,128 for each representative page. No HTTP-failing plateau was found through 128. The existing capacity predicate tests availability, not latency: all three pages exceeded 500 ms p95 at concurrency 16 (approximately 550–599 ms), and p95 at 128 was 3,882 ms for /, 4,391 ms for /knowledge and 4,093 ms for /knowledge/return-window. Maximum observed capacity request duration was 4,520 ms. Preserve this as a Package 5 latency follow-up; do not equate successful requests at 128 with fast service, a measured maximum capacity, streaming-chat capacity or Forge capacity. Historical Octane measurements below describe a different runtime and remain historical.

## Local GET capacity (2026-09-19)

Machine: Windows 11, Intel Core i9-10850K (20 logical CPUs / 10 cores), 32 GB RAM, Herd PostgreSQL (`max_connections=100`).

Runtimes compared:

- Herd nginx FastCGI: `https://supportflow-ai.test`
- Octane/FrankenPHP: `http://127.0.0.1:8000`

Healthy gate used for the machine-level Octane ladder: p95 ≤ 500 ms, success ≥ 99.9%, failure < 0.1%. Stressless durations stayed under 60 s (`K6_NO_COOKIES_RESET=true`).

### Herd FastCGI vs four Octane workers

Identical 10 s GET ladders on `/up`, `/`, `/knowledge`, and `/knowledge/return-window`. Both runtimes stopped on confirmed RPS plateau, not an assumed concurrency-16 cap. Zero HTTP failures.

| Path | Herd peak RPS | Octane 4-worker peak RPS |
|---|---|---|
| `/up` | ~193 | ~3,022 |
| `/` | ~50 | ~304 |
| `/knowledge` | ~48 | ~244 |
| `/knowledge/return-window` | ~49 | ~282 |

Four Octane workers were a **worker-count ceiling**, not this machine’s maximum.

### Machine-level Octane maximum on `/`

Worker counts 4, 8, 12, 16, then 20. Concurrency ladder 4, 8, 16, 24, 32, 48, 64, 96, 128. 15 s plateaus, cookies persisted, warm-up after every restart.

**Maximum healthy `/` result: 770 successful RPS at 16 workers and concurrency 32, p95 47 ms, median 39 ms, TTFB p95 46 ms, 100% success (11,609/11,609).** CPU ~84% average / 85% max. RAM ~44%. FrankenPHP RSS ~365 MB. PostgreSQL 25/100 connections.

At that configuration, `/up` reached ~4,392 rps (p95 9 ms), `/knowledge` ~579 rps (p95 57 ms), and `/knowledge/return-window` ~708 rps (p95 50 ms).

20 workers collapsed (~85 rps) and concurrency 48 exceeded p95 500 ms. 24 workers failed to bind port 8000 after that collapse. RAM and `max_connections` were not the limit.

That 770 rps figure is about **2.5×** the four-worker `/` result (~304–309 rps).

Do **not** copy 16 workers onto Forge. Choose Forge workers from that server’s CPU/RAM and CareerForge contention.

## Post-Rector local Octane check

After Rector, `composer test` passed (223 tests, 1 skipped). Official `php artisan octane:start --server=frankenphp` on this Windows host now throws: FrankenPHP binaries are only available for Linux and macOS. On Windows, use WSL or Docker. The 770 rps figure above remains the last measured local Octane `/` maximum; it was not re-run after Rector because Laravel no longer downloads a native Windows binary.

## Windows FrankenPHP limitation

Laravel Octane documents Docker for Windows FrankenPHP. The local 770 rps run used a native Windows FrankenPHP binary plus Herd’s `cacert.pem` so OpenAI TLS would verify. That workaround is **not** the supported production path and must not be committed (binaries, `frankenphp-worker.php`, local CA paths, vendor signal guards).

Linux/Forge: `php artisan octane:frankenphp` (or `octane:start --server=frankenphp`) behind Nginx. Set `OCTANE_HTTPS=true` when Nginx terminates TLS. `config/octane.php` `max_execution_time` is 120.

## Chat streaming

`POST /chat/stream` is the sole write path: validation, `throttle:chat` (IP + session, 30/minute), fail-fast `Cache::lock('chat-stream:{demoSessionId}')`, `ChatService::ask()`, SSE `delta`/`done`/`error`/`stopped`. Livewire `send()` is UX-only. Stop stays unthrottled.

Historical local waves (2026-09-19): 14/14 real OpenAI turns on Herd and Octane, grounded, persisted, no exposed `CITES:`, no app 429s, first token ~1.6–2.2 s concurrent.

Current-path waves (2026-10-26, throttle 30, separate cookie jars, `POST /chat/stream` only): **7/7** `event: done` on Herd `https://supportflow-ai.test` (concurrency 1/2/4). No `event: error`, no `CITES:`, no app 429 or 409.

Before embed batch: first `event: delta` 3.45 s (n=1), 1.88–2.47 s (n=2), 4.00–8.14 s (n=4). After one `embedMany` per search: 1.47 s (n=1), 1.23–1.32 s (n=2), 1.31–1.47 s (n=4). Confirm after the dialog-focus change (chat path unchanged), 7/7 done: first n=1 2.56 s, warm n=1 **1.28 s**, n=2 **1.83–2.34 s**, n=4 **1.17–1.60 s**. Herd PHP-FPM overlaps concurrent streams; Forge’s 1 worker does not. Provider-dominated remainder. Do not claim Octane makes answers faster for visitors. Do not add Forge workers to chase concurrent TTFB.

## Response-time targets (2026-10-26)

These are showroom gates from measured current-path numbers on this 1 vCPU/961 MiB Forge VM and Herd. They are not a reason to raise Octane workers or skip grounding.

| Workflow | Metric | Target | Measured |
| --- | --- | --- | --- |
| Harbor chat, one visitor | Time to first `event: delta` | ≤ 2.0 s warm | Forge warm **1.37 s**; local warm **1.28 s** (first local this pass 2.56 s) |
| Harbor chat, two overlapping visitors | Slowest first `event: delta` | ≤ 5.0 s | Forge **1.80–4.58 s** |
| Harbor chat, four overlapping visitors | Slowest first `event: delta` | ≤ 8.0 s | Forge **1.36–6.38 s** |
| GET `/` on Forge | p95 at last-healthy 16 VU | ≤ 750 ms | **520 ms**, ~35 rps |
| GET `/up` | HTTP 200 | ≤ 300 ms | 200 after deploy |
| Replacement Confirm/Cancel | No OpenAI round-trip | Local commit only | Live Forge **HR-5A6717** / **Nothing was saved.** |

Ticket triage and suggested drafts stay queued (`wire:poll.5s.visible`). Preview chat uses the same SSE+embed path as Harbor; use the Harbor row until a separate Preview wave exists. Visible character pacing (two characters/~32 ms) is intentional demo UX and is not part of the SSE target.

Nielsen’s 0.1 s / 1 s bars are for UI chrome, not a grounded model call. First useful chat text is provider-dominated after one embed batch (~1.3–1.5 s). Concurrent chat on 1 worker is serialized (~prior `done` + 1.5–2 s). Keep those as the overlapping-visitor targets; do not add workers to shrink them.

## Forge staging (2026-09-19)

Historical GET/chat benches targeted `https://supportflow-ai-ou1b5gvy.on-forge.com` on the shared CareerForge VM (1 vCPU, 961 MiB), Octane/FrankenPHP **1 worker** on `127.0.0.1:8000` behind Nginx TLS. Live staging is now `https://supportflow-ai-lqojsjr5.on-forge.com` with **1 worker** on `127.0.0.1:8001`. GET benches use the HTTPS URL, so they do not depend on that loopback port. Port **8001** is permanent. Scheduler was not paused for the 19 Sep run. GET benches used Pest Stressless only (`K6_NO_COOKIES_RESET=true`, `STRESS_MAX_CONCURRENCY=16`). Chat used one-shot `POST /chat/stream` with separate cookie jars; `throttle:chat` was 10/minute on that date. Live config is now 30/minute (`supportflow.rate_limits.chat_per_minute`).

Smoke, load, stress, stability, and capacity all passed with **zero HTTP failures**. Capacity last-healthy concurrency was 16 on `/`, `/knowledge`, and `/knowledge/return-window` (the 16-VU safety rail, not a local-style p95 gate). `/` peaked around **39 rps** (concurrency 12–16, p95 362–465 ms). `/knowledge` p95 rose above 1 s at concurrency 12. That is not the local 770 rps, 47 ms p95 result and must not be treated as a reason to raise Forge workers.

Browser checks: `/`, `/knowledge`, `/knowledge/return-window`, agent dashboard/tickets, mobile-width home, SSE chat with citations and a persisted follow-up, no exposed `CITES:`. Chat waves 1/2/4: 7/7 `event: done`, no `event: error`, no `CITES:`, no app 429s.

## Forge staging (2026-10-26)

Live hostname `https://supportflow-ai-lqojsjr5.on-forge.com`, commit `2d6943d` (GET ladder and pre-fix chat TTFB were on `d93755f`), Octane **1 worker** on `127.0.0.1:8001`. Weekday. CareerForge `worker-987181` was running for GET/TTFB, then **STOPPED** at 22:10Z for a reversible pause. `K6_NO_COOKIES_RESET=true`, `STRESS_MAX_CONCURRENCY=16`. Chat throttle 30.

GET Stressless: smoke 4/4, load 3/3, stress 6/6, stability 3/3, all **0 failed**. Capacity first pass marked `/` last-healthy **2** after 1 failed request at concurrency 4 (following the long GET session). Cooldown rerun: last-healthy **16** on `/`, `/knowledge`, and `/knowledge/return-window`, **0 failed**. `/` at concurrency 16 ~35 rps, p95 520 ms. `/knowledge` p95 rose above 1 s at concurrency 12 on some plateaus (1.75 s rerun). That is not the local 770 rps result and must not be treated as a reason to raise Forge workers.

Chat TTFB waves 1/2/4: **7/7** `event: done` both before and after embed batch, no `event: error`, no `CITES:`, no app 429 or 409. Pre-fix first `event: delta`: 2.89 s (n=1), 2.49–5.04 s (n=2), 2.22–9.65 s (n=4); the 1-worker process serializes concurrent streams (2.2 / 4.8 / 7.3 / 9.7 s tracks the previous stream’s `done`). After `2d6943d` (20:22:54Z): warm n=1 **1.37 s** (first request after Octane reload was 3.53 s), n=2 **1.80–4.58 s**, n=4 **1.36–6.38 s** (1.36 / 3.24 / 4.79 / 6.38 still serialized).

Octane default is one worker per CPU; `--workers` may exceed that. Live is still `--workers=1`. CareerForge pause 2026-10-26 22:10Z (`supervisorctl stop worker-987181:*`, not deleted):

| | Before (worker RUNNING) | After (STOPPED) |
| --- | --- | --- |
| MemAvailable | 349 MiB | **361 MiB** (+12) |
| Swap used | 269 MiB | **203 MiB** (−66) |
| AnonPages | 198 MiB | 185 MiB |
| Load | 0.08 | 0.13 |
| CareerForge RSS | 46 MB, CPU 0.1% | gone |
| FrankenPHP RSS | 127 MB | 127 MB |
| SupportFlow queue RSS | 80 MB | 80 MB |
| GET `/up` TTFB | 264 ms, HTTP 200 | 248 ms, HTTP 200 |
| GET `/` TTFB | 255 ms, HTTP 200 | 258 ms, HTTP 200 |

CareerForge’s RSS was mostly in swap, so the pause did not free a hot 46 MB for a new worker. Idle HTTP is unchanged.

### `--workers=2` trial (2026-10-26 22:19Z), reverted

Forge PUT on `daemon-1104270` only (`numprocs=1`). CareerForge stayed STOPPED. Same-session 1-worker baseline, then 2-worker, then revert. Chat 7/7 `event: done` both sides, no `event: error`, no `CITES:`, no 429/409. GET smoke 4/4 and load 3/3 **0 failed** both sides.

| | `--workers=1` | `--workers=2` |
| --- | --- | --- |
| Chat n=1 first delta | 2.70 s | 1.79 s |
| Chat n=2 first delta | 1.44–**3.07 s** (serialized) | 1.46–**1.54 s** (overlap) |
| Chat n=4 first delta | 1.86–**6.92 s** (1.86/3.25/5.08/6.92) | 1.60–**3.29 s** (two pairs) |
| GET `/` conc 4 × 15 s | 39.2 rps, p95 **121 ms** | 32.9 rps, p95 **187 ms** |
| GET `/knowledge` conc 4 | 21.9 rps, p95 262 ms | 22.2 rps, p95 213 ms |
| Idle FrankenPHP RSS | 127 MB | 142 MB |
| Load FrankenPHP RSS | 122 MB | **195 MB** |
| After-load MemAvailable | 362 MiB | **283 MiB** |
| Active swap-out (`vmstat so`) | **0** every sample | Chat n=4: 1 s at 264 KB/s. GET load: 1 s at **4456 KB/s**, swpd 133808→138344 |
| Active swap-in (`si`) | bursts 4–220 KB/s (cold pages) | bursts plus 372 KB/s after the so spike |
| CPU during GET load | id often 0–5%, us 80–90% | id 0%, us 75–92% |

Reverted at 22:21Z: `--workers=1`, `/up` 200, `numprocs=1`. Idle after revert: si=0 so=0, MemAvailable 338 MiB, swap used 133 MiB. Two workers overlap OpenAI I/O (n=2 wall 1.90 s vs 3.39 s) but on this 1 vCPU/1 GB box GET `/` slows and GET load **swap-out**s. Do not copy local 16. Do not raise Supervisor process count.

GET flake: first `/` capacity conc 4 had **1** k6 `http_req_failed` after the long weekday ladder. SupportFlow nginx `access_log off`; error log empty. A later `GET /` conc 4 × 15 s recorded **503/503 HTTP 200**. Treat the original fail as transport-level unless origin access_log is on.

## How to rerun GET tests

From a checkout, with Stressless excluded from `composer test`:

```bash
STRESS=true STRESS_URL=https://supportflow-ai.test composer test:stress:smoke
```

Forge staging is off-peak only: `https://supportflow-ai-lqojsjr5.on-forge.com`. Start with smoke. Stop if it fails. GET `/up`, `/`, `/knowledge`, and `/knowledge/return-window` only.

Pest Evals are local-only. Run `composer test:evals` (`vendor/bin/pest --evals --group=evals`). They call live OpenAI through ChatService, TicketIntakeService, and SuggestedReplyService against KnowledgeSeeder. Do not set `PEST_EVALS=1` in CI. `composer test` and GitHub Actions exclude group `evals`. Keep `OPENAI_SMOKE` until you decide the triage evals replace it. Rector `--dry-run` is part of `composer test` and GitHub Actions; applying Rector stays local (`composer rector`).

## CI and Forge (2026-09-19)

Pushed `500e465` to `origin/main`. GitHub Actions Tests passed. Forge Octane is serving staging; GET and chat benches above ran against that origin. See [forge.md](forge.md).

