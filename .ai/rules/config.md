---
paths:
  - config/supportflow.php
---

# Config

## Raised demo ticket and chat allowances
Local demo allowances are 30 tickets per browser, 200 shared visitor tickets, 30 Harbor questions per conversation, 30 Harbor and Preview chat requests per minute, and 15 ticket submits per minute. The visitor cap sentence reads those config values. Leave file size, document count, disk floor, extraction time, regenerate, dictation, and agent-login limits unchanged.

## /up heartbeat must name ai and default
DiagnosingHealth requires a cache heartbeat whose queues include ai and default (config supportflow.health.required_queues). Forge CACHE_STORE must be database. queue:listen only pops jobs, so RecordQueueHeartbeat also listens for JobPopping. Feature coverage: tests/Feature/HealthCheckTest.php.
