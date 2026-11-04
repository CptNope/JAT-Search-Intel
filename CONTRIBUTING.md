# Contributing to JAT Search Intel

## Branching Strategy

This project follows a **Git Flow** branching model.

```
main ─────────────●───────────────●──────────── (stable releases, tagged)
                  │               ↑
develop ──────●───┼───●───●───●───┤──────────── (integration branch)
              │       │       │   │
feature/foo ──┘       │       │   │
feature/bar ──────────┘       │   │
feature/baz ──────────────────┘   │
release/1.3 ──────────────────────┘
```

### Branches

| Branch | Purpose | Merges into |
|---|---|---|
| `main` | Stable releases only. Every commit is tagged (e.g. `v1.2.0`). | — |
| `develop` | Integration branch. All feature work merges here first. | `main` (via release branch) |
| `feature/*` | Individual features or tasks. Short-lived. | `develop` |
| `release/*` | Version prep (bump version, final QA, changelog). | `main` + `develop` |
| `hotfix/*` | Urgent production fixes. Branched from `main`. | `main` + `develop` |

### Workflow

#### Starting a new feature
```bash
git checkout develop
git pull origin develop
git checkout -b feature/my-feature
# ... work, commit ...
git push -u origin feature/my-feature
# Open PR → develop
```

#### Preparing a release
```bash
git checkout develop
git checkout -b release/1.3.0
# Bump version in jat-search-intel.php, readme.txt, package.json
# Update CHANGELOG.md
# Final QA fixes
git push -u origin release/1.3.0
# Open PR → main (and back-merge into develop after merge)
```

#### Hotfix
```bash
git checkout main
git checkout -b hotfix/critical-fix
# ... fix, commit ...
git push -u origin hotfix/critical-fix
# Open PR → main (and back-merge into develop after merge)
```

### Commit Message Convention

Use **Conventional Commits**:

```
<type>(<scope>): <short description>

[optional body]
```

| Type | When |
|---|---|
| `feat` | New feature |
| `fix` | Bug fix |
| `test` | Adding or updating tests |
| `chore` | Build, CI, deps, housekeeping |
| `docs` | Documentation only |
| `refactor` | Code change that neither fixes nor adds |
| `perf` | Performance improvement |
| `style` | Formatting, whitespace (no logic change) |

**Examples:**
- `feat(geo): add AI crawl directive meta tags`
- `fix(schema): handle empty post content in singular schema`
- `test(integration): add compatibility tests for CPTs and unicode`
- `chore(ci): add GitHub Actions workflow`

### Tags

Every merge to `main` gets a semver tag:

```bash
git tag -a v1.3.0 -m "v1.3.0 — Bulk Tools & REST API"
git push origin v1.3.0
```

### Code Quality Gates

Before merging any PR, ensure:

1. **All PHPUnit tests pass** — `vendor/bin/phpunit --no-coverage`
2. **PHPCS clean** — `vendor/bin/phpcs --standard=WordPress-Core includes/`
3. **PHPStan level 6** — `vendor/bin/phpstan analyse includes/ --level=6`
4. **No regressions** — test count should not decrease

These are enforced automatically by the CI pipeline (`.github/workflows/ci.yml`).

## Local Development Setup

```bash
# Clone and install
git clone <repo-url>
cd Search-Intel
composer install
npm install          # For Playwright E2E tests

# Run tests
vendor/bin/phpunit --no-coverage          # All tests
vendor/bin/phpunit --testsuite unit       # Unit only
vendor/bin/phpunit --testsuite integration # Integration only
npm run test:e2e                          # E2E (requires WP instance)
```
