---
paths:
  - .github/workflows/tests.yml
---

# Workflows

## CI Rector is dry-run only
GitHub Actions Tests runs Pint --test, Rector process --dry-run --output-format=github, Larastan, then Pest excluding stress and evals. Never run applying rector process in CI.

## CI installs Playwright before Pest
The Tests workflow installs Node, runs npm ci and npm run build, then npx playwright install --with-deps before php artisan test. Browser screenshots and traces upload as an artifact only when the job fails. Do not apply Rector in that workflow.
