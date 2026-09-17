---
paths:
  - resources/views/livewire/pages/welcome.blade.php
  - resources/views/livewire/pages/demo-safety.blade.php
  - resources/views/livewire/pages/demo-environment.blade.php
---

# Livewire Pages

## Landing page uses four live demo cards
The public landing page has four live cards (chat, workflow page, knowledge, safety page) in sm:grid-cols-2 lg:grid-cols-4. Never show a Coming soon or uploader placeholder. A fifth live path must change the grid. Business knowledge is Browse policies only—Agent stays in the chrome. wire:key belongs on the safety-page test foreach, not on static landing cards.

## Safety page omits the chat cap and deep-links Agent scenarios
Keep the ten-question chat cap in ChatService. Do not mention that limit on the visitor Advanced Safety page or the landing safety card. Open Agent scenarios must land on the already-visible catalog (query scenarios=1 and #agent-scenarios) so visitors do not hunt through Agent navigation.

## Demo home stays sales-focused
Demo home leads with the live-portfolio kicker, a working-copilot intro, and a smaller stack line. Keep one primary button (Try a prepared question) and three outlined cards. “What this demonstrates” uses client language. Do not put “What this shows” or “What this is not” on home. Limitations and dictation live on the Demo environment page.
