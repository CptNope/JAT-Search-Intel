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

### Releases

`main` is protected: changes land through pull requests, and every required CI
check must pass first. To release:

1. Bump the version in `jat-search-intel.php` (header and `JAT_SEARCH_INTEL_VERSION`),
   `readme.txt` (`Stable tag` and a new `= x.y.z =` changelog entry) and `package.json`.
   `bin/check-version.sh` confirms they all agree; CI runs it on every PR.
2. Merge the release PR into `main`.
3. Open **Actions → Release → Run workflow** on `main`. It creates the
   `vX.Y.Z` tag from the plugin header, builds `jat-search-intel.zip` from that
   commit and publishes the release with the zip, a SHA-256 checksum and the
   readme changelog entry as notes. (Pushing a `vX.Y.Z` tag does the same.)

Releases are immutable once published: the tag and the zip can't be changed
afterwards, so a fix always ships as a new version.

### Code Quality Gates

Every pull request runs `.github/workflows/ci.yml`, and `main` will not accept a
merge until these pass:

| Check | Command to run locally |
|---|---|
| PHPUnit on PHP 8.1–8.4 | `vendor/bin/phpunit --no-coverage` |
| PHP 7.4 and 8.4 syntax | `php -l` on every shipped file |
| PHP_CodeSniffer (WordPress standards) | `vendor/bin/phpcs` |
| PHPStan level 6 | `vendor/bin/phpstan analyse` |
| Psalm level 4 | `vendor/bin/psalm` |
| ESLint and Stylelint | `npx eslint admin/js/` · `npx stylelint "admin/css/**/*.css"` |
| Package (versions agree, zip has no dev files) | `bin/check-version.sh && bin/build-zip.sh` |
| WordPress.org Plugin Check on the built zip | `wp plugin check jat-search-intel` |

Dependabot opens weekly PRs for Composer, npm and GitHub Actions updates. Report
security problems privately; see [SECURITY.md](SECURITY.md).

## Local Development Setup

```bash
# Clone and install
git clone <repo-url>
cd JAT-Search-Intel
composer install
npm install          # For Playwright E2E tests

# Run tests
vendor/bin/phpunit --no-coverage          # All tests
vendor/bin/phpunit --testsuite unit       # Unit only
vendor/bin/phpunit --testsuite integration # Integration only
npm run test:e2e                          # E2E (requires WP instance)
```
