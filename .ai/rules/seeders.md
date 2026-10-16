---
paths:
  - database/seeders/KnowledgeSeeder.php
---

# Seeders

## Live Harbor articles are not updated by reseeding
Forge deploy migrates and does not run db:seed. A change to a published Harbor article needs a targeted migration that keeps the rest of the body, adds the new text once, and refreshes that article's chunks. Do not run KnowledgeSeeder on the live database.
