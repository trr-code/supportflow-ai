---
paths:
  - .github/workflows/tests.yml
---

# Workflows

## CI Rector is dry-run only
GitHub Actions Tests runs Pint --test, Rector process --dry-run --output-format=github, Larastan, then Pest excluding stress and evals. Never run applying rector process in CI.
