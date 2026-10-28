---
paths:
  - 'tests/Browser/**/*.php'
---

# Browser

## Browser tests use Pest Playwright
Browser coverage lives in tests/Browser and uses pestphp/pest-plugin-browser with Playwright 1.62.1 (the plugin rejects older releases). Browser tests extend Tests\BrowserTestCase so Vite assets load; other tests keep withoutVite(). Fake the AI agents for these runs. A real microphone stays a manual check in docs/manual-regression.md. Playwright clicks that remove a Livewire control can time out after the action succeeds.

## Click removed Livewire buttons once
Playwright locator clicks retry after Livewire removes the button, so Back to tickets and Load scenario time out even when the handler already ran. On a touch-emulated device a raw mouse click can also reload the page. Browser tests click those buttons once with Playwright mouseClick at the button center, on a non-touch viewport narrower than 1280 so the queue stays at 11 rows. Do not call Livewire methods directly and do not swallow the click timeout.

## Touch taps hit wire:click once
Touch coverage uses a hasTouch viewport with isMobile false and a width under 1280 so the queue stays at 11 rows. Tap the control once with Playwright's element tap so the wire:click handler runs. Do not add a mouse click on that same control, call the Livewire method directly, or swallow a timeout. A mouse click on a touch-emulated device can reload the page.

## Browser tests use built assets even when Vite is running
BrowserTestCase sets Vite::useHotFile to storage/framework/testing-vite.hot so tests use public/build instead of the developer public/hot server. Development HMR reloads can destroy polling test execution contexts. Build assets before browser tests; keep the assertion that test HTML excludes @vite/client.
