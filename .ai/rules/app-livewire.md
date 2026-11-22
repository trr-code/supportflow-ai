---
paths:
  - 'app/Livewire/**'
---

# App Livewire

## Validation follows the mutation boundary
Default to validation in the Livewire mutation action before invoking workflow services. Livewire Form Objects are supported when complexity or reuse warrants them. Preserve controller-owned validation for POST SSE endpoints; UX-only actions do not duplicate that boundary.
