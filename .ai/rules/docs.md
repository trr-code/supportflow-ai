---
paths:
  - docs/forge.md
---

# Docs

## Forge daemon timeout is not changed by queue restart
The live worker timeout is the Forge daemon command (--timeout=120). $RESTART_QUEUES() / queue:restart during deploy restarts workers but does not edit the daemon. After raising job $timeout, change the daemon in the Forge UI or workers keep the old kill time. Set DB_QUEUE_RETRY_AFTER=150.
