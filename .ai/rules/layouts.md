---
paths:
  - "resources/views/components/layouts/**"
---

# Layouts

## Lucid layout has no floating chat
Homepage and `/demo/harbor`, `/demo/documents`, and `/demo/invoices` use `components.layouts.lucid`. Harbor chat is inline in the workspace. Do not mount a second `livewire:chat.widget` on that layout.

## Keep the public chat widget inline
Do not add lazy or defer to livewire:chat.widget. Document GET plus the Livewire __lazyLoad mount is about 2× wall time and more session/demo-session queries than today’s single GET. Stressless GET / with defer omits that mount (demo_sessions stop growing) and is first-paint only—not a complete-flow or four-worker throughput win. The widget is fixed on the page, so viewport lazy is also the wrong tool.

## Keep the preview toast on its own persist key
The preview copy toast uses a top-end flux:toast.group inside @persist('workspace-toast'). Do not reuse @persist('toast'). Every shared @persist('toast') group, on the public, agent, and auth layouts, is also top-end so wire:navigate keeps that position. The keys stay different so the first group does not cover the Preview launcher.

## Homepage artwork URLs must use Laravel assets
Homepage root supplies --landing-hero-art and --landing-footer-art via asset() absolute URLs. CSS root-relative /images paths resolve to Vite origin during development and fail even though built-asset tests render correctly. Retain the original purple wave/smoke artwork and asset files without grayscale filters; upper footer texture blends into pure-white gallery without altering black.

## Development refresh follows standard Vite configuration
Use the current Laravel Vite refresh:true development workflow; CSS HMR and watched template/server edits update normally. Built assets are used by browser tests. Treat stale hot markers or reconnect reloads as a diagnosis, not a reason to disable standard refresh or reinstate old manual-refresh workarounds.

## Homepage header keeps only the Lucid home link
Owner removes the upper-right Explore demos anchor because the demo cards are directly below the hero. Keep the Lucid home/wordmark link and existing header geometry; no empty navigation landmark or replacement CTA. Preserve demo cards, section anchor, responsive guide copy and footer.
