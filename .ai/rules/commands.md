---
paths:
  - 'app/Console/Commands/**'
---

# Commands

## Cleanup is automatic but preserves active content and accounting
The owner approved verified-safe removal of expired runtime data and abandoned uploads. Keep previews as the default for content/file cleanup commands; scheduled runs use explicit --execute, overlap protection, ownership checks and a grace period. Preserve active workspaces/invoices, seeded/demo catalog data, unrelated drafts and AI usage totals. Retry failed cleanup without starving the rest of a batch. This is not blanket permission to delete source, design assets, fixtures, backups, other projects, or to commit/deploy. Local automatic maintenance uses the named Lucid Local Maintenance Windows task while Herd is running; production still needs the approved deployment and scheduler verification.
