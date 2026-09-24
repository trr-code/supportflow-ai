---
paths:
  - rector.php
  - composer.json
---

# General

## Conservative Rector only; dry-run in CI
Keep Rector conservative: PHP sets from composer.json, Pest CODING_STYLE, skip toBeEmpty rewrites, Pest.php $this arrow functions, and new-without-parentheses. composer rector:check and GitHub Actions run process --dry-run only. Apply with composer rector locally. Do not apply Rector in CI. Do not set withCache('/tmp/rector')—this repo is developed on Windows.

## composer test includes Rector dry-run
composer test runs config:clear, Pint --test, Rector --dry-run, PHPStan, then Pest excluding stress and evals. rector:check is the read-only gate; rector applies changes locally. Do not fold evals or Stressless into test.
