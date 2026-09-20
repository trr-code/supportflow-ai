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
