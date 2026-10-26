---
paths:
  - 'tests/Stress/**'
---

# Stress

## Stressless-only GET performance tests
HTTP performance uses Pest Stressless only—no raw k6 suite. GET /up is isolated health. Climb /, /knowledge, and /knowledge/return-window as separate constant-concurrency plateaus until the first unhealthy level. Never hit ticket create, chat send, dictation, or regenerate. Exclude group stress from composer test and CI. Forge staging (https://supportflow-ai-lqojsjr5.on-forge.com) is off-peak progressive Stressless against the public hostname only: smoke first, stop if it fails, then load/stress/stability/capacity. The previous 16-VU rail is a shared-VM safety start, not this machine’s Octane worker count. Do not copy the local 16-worker/770 rps result onto Forge. That is not permission to load-test an unknown production system.

## Keep Stressless duration under 60s
Stressless starts k6 via Symfony Process without setTimeout, so the process default of 60s applies. Composer disableProcessTimeout does not change that. Keep each Stressless duration well under 60 seconds (stability uses 45) so k6 can write the summary and exit.

## Persist k6 cookies across Stressless iterations
k6 resets the per-VU cookie jar after each iteration unless K6_NO_COOKIES_RESET=true. Stressless GET helpers and the Stress Pest beforeEach set that env so HTML routes measure returning visitors, not one demo_sessions insert per request. Ad-hoc `pest stress` must set the env itself. Do not disable Laravel cookie encryption to chase Stressless row growth.

## Keep Forge load tests separate
GET Stressless against Forge is off-peak, smoke-first, and targets the SupportFlow public hostname only. Local Herd GET Stressless is the default capacity check before that. Never loop POST /chat/stream, ticket create, dictation, or regenerate in Stressless. Do not run Stressless against CareerForge.

## Forge GET Stressless is the post-deploy GET check
After deploy, Forge GET Stressless is the GET capacity check: smoke first, then load/stress/stability/capacity against the public hostname. Do not copy 16 Octane workers. Do not reduce the run because CareerForge is unused. GET Stressless does not close chat TTFB. See docs/performance.md.

## Stressless failed GETs need duration_max; site access_log is off
k6 http_req_failed is a transport/error rate, not an HTTP status histogram. SupportFlow nginx has access_log off, so a single Stressless failure after a long GET ladder has no origin status. Report duration_max_ms and ttfb_max_ms. Do not raise Forge Octane workers or shrink the ladder. A cooldown rerun that is 0-failed is evidence of a transient, not a pass to ignore.
