---
paths:
  - 'app/Models/**'
---

# Models

## Relationship loading and creation defaults
Declare needed eager loads on the consuming query. Keep model-owned creation defaults in the existing booted callbacks when modifying those models.

## Explicit mass-assignment allow-lists
Keep explicit mass-assignable field allow-lists using the existing #[Fillable] model attribute. Do not broadly unguard models as a convenience. These lists complement, and never replace, input validation and authorization.
