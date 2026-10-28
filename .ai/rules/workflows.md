---
paths:
  - .github/workflows/tests.yml
---

# Workflows

## CI Rector is dry-run only
GitHub Actions Tests runs Pint --test, Rector process --dry-run --output-format=github, Larastan, then Pest excluding stress and evals. Never run applying rector process in CI.

## CI installs Playwright before Pest
The Tests workflow installs Node, runs npm ci and npm run build, then npx playwright install --with-deps before php artisan test. Browser screenshots and traces upload as an artifact only when the job fails. Do not apply Rector in that workflow.

## Forge deploys only the tested main commit
Work directly on main as requested. Forge push-to-deploy is disabled. The deploy job needs tests and only runs on a main push, using GitHub secret FORGE_DEPLOY_HOOK. .github/forge-deploy.sh requires ci_commit, rejects moved main, checks successful GitHub Actions tests for that SHA, then fast-forwards exactly to it. Keep the live Forge script synchronized with this file. Run bash .github/forge-deploy-test.sh. Never print the hook token.
