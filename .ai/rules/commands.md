---
paths:
  - 'app/Console/Commands/**'
---

# Commands

## Cleanup is automatic but preserves active content and accounting
The owner approved verified-safe removal of expired runtime data and abandoned uploads. Keep previews as the default for content/file cleanup commands; scheduled runs use explicit --execute, overlap protection, ownership checks and a grace period. Preserve active workspaces/invoices, seeded/demo catalog data, unrelated drafts and AI usage totals. Retry failed cleanup without starving the rest of a batch. This is not blanket permission to delete source, design assets, fixtures, backups, other projects, or to commit/deploy. Local automatic maintenance uses the named Lucid Local Maintenance Windows task while Herd is running; production still needs the approved deployment and scheduler verification.

## Local maintenance must not launch flashing interactive terminals
Launching powershell.exe with -WindowStyle Hidden from the interactive Lucid Local Maintenance scheduled task was observed spawning Windows Terminal every minute. Exiting Codex does not stop an OS scheduled task. Do not recreate or re-enable this interactive launcher without a reviewed replacement and verification that no console appears and idle display/sleep behavior remains intact. Do not infer that this proves a shutdown blocker.

## Local maintenance incident resolution and package ownership
2026-10-29 owner confirmed normal sleep resumed after the flashing Lucid Local Maintenance Windows task was disabled; accept that mitigation as resolving the observed terminal/idle-sleep incident, superseding the earlier unverified-sleep note. Keep the task disabled until a reviewed replacement is verified. Packages 3/4 do not depend on restoring it; use scoped maintenance when needed for test artifacts/lifecycle checks. Local launcher replacement is a package 1 follow-up; ongoing operations remain package 5. Production scheduling was unchanged.
