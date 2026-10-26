---
paths:
  - resources/views/partials/head.blade.php
---

# Partials

## Harbor layouts stay light-only
Harbor pages are light-only. Do not add @fluxAppearance to the shared head; it applies system Dark Mode via a .dark class and makes Flux filled/primary buttons unreadable on sand/white. Keep :root { color-scheme: only light; } so Chrome cannot auto-darken native controls. Do not put class="dark" on Harbor public or agent sidebar html.

## Visitor-facing document titles use Lucid
HTML document titles on visitor layouts use Lucid, not APP_NAME. Keep APP_NAME as SupportFlow AI for Forge, mail, and cache prefixes. Home is Lucid; other pages are {page} - Lucid.
