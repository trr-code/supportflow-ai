---
paths:
  - resources/views/components/layouts/public.blade.php
  - resources/views/components/layouts/workspace.blade.php
---

# Layouts

## Keep the public chat widget inline
Do not add lazy or defer to livewire:chat.widget. Document GET plus the Livewire __lazyLoad mount is about 2× wall time and more session/demo-session queries than today’s single GET. Stressless GET / with defer omits that mount (demo_sessions stop growing) and is first-paint only—not a complete-flow or four-worker throughput win. The widget is fixed on the page, so viewport lazy is also the wrong tool.

## Keep the preview toast on its own persist key
The preview copy toast uses a top-end flux:toast.group inside @persist('workspace-toast'). Do not reuse @persist('toast'). Every shared @persist('toast') group, on the public, agent, and auth layouts, is also top-end so wire:navigate keeps that position. The keys stay different so the first group does not cover the Preview launcher.
