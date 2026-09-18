---
paths:
  - resources/views/components/layouts/public.blade.php
---

# Layouts

## Keep the public chat widget inline
Do not add lazy or defer to livewire:chat.widget. Document GET plus the Livewire __lazyLoad mount is about 2× wall time and more session/demo-session queries than today’s single GET. Stressless GET / with defer omits that mount (demo_sessions stop growing) and is first-paint only—not a complete-flow or four-worker throughput win. The widget is fixed on the page, so viewport lazy is also the wrong tool.
