---
paths:
  - resources/views/partials/head.blade.php
---

# Partials

## Harbor layouts stay light-only
Harbor pages are light-only. Do not add @fluxAppearance to the shared head; it applies system Dark Mode via a .dark class and makes Flux filled/primary buttons unreadable on sand/white. Keep :root { color-scheme: only light; } so Chrome cannot auto-darken native controls. Do not put class="dark" on Harbor public or agent sidebar html.
