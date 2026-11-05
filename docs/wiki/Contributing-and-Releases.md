# Contributing and Releases

The full guide is in [CONTRIBUTING.md](https://github.com/CptNope/JAT-Search-Intel/blob/main/CONTRIBUTING.md). In short:

## Branches

- `main`: stable releases. Changes arrive only through pull requests that pass every check.
- `develop`: integration branch.
- `feature/*`, `release/*` and `hotfix/*` branches.

## Local setup

```bash
git clone https://github.com/CptNope/JAT-Search-Intel.git
cd JAT-Search-Intel
composer install
npm ci
vendor/bin/phpunit --no-coverage
```

If you change anything in `src/`, run `npm run build` and commit `build/`. WordPress.org ships the compiled files.

## Quality gates

`main` won't accept a merge until all of these pass:

| Check | Locally |
|---|---|
| PHPUnit on PHP 8.1, 8.2, 8.3 and 8.4 | `vendor/bin/phpunit --no-coverage` |
| PHP 7.4 and 8.4 syntax of every shipped file | `php -l` |
| WordPress coding standards | `vendor/bin/phpcs` |
| PHPStan level 6 | `vendor/bin/phpstan analyse` |
| Psalm level 4 | `vendor/bin/psalm` |
| ESLint and Stylelint | `npx eslint admin/js/` and `npx stylelint "admin/css/**/*.css"` |
| Package: version numbers agree and the zip has no dev files | `bin/check-version.sh && bin/build-zip.sh` |
| Official WordPress.org Plugin Check on the built zip | `wp plugin check jat-search-intel` |
| Performance regression tests | `vendor/bin/phpunit --filter Performance` |
| CodeQL: no new high-severity alerts | runs automatically |

## Security

- Dependabot opens weekly update PRs for Composer, npm and GitHub Actions, plus security fixes as soon as they're available.
- Secret scanning with push protection blocks commits that contain credentials.
- Report vulnerabilities privately through the [Security tab](https://github.com/CptNope/JAT-Search-Intel/security/advisories/new). See [SECURITY.md](https://github.com/CptNope/JAT-Search-Intel/blob/main/SECURITY.md).

## Releasing

1. Bump the version in `jat-search-intel.php` (header and constant), `readme.txt` (`Stable tag` and a changelog entry) and `package.json`. `bin/check-version.sh` confirms they agree.
2. Merge the release PR into `main`.
3. Run **Actions → Release → Run workflow** on `main`. It tags the version, builds `jat-search-intel.zip` from that commit and publishes the release with the zip, a SHA-256 checksum and the changelog entry.

Releases are immutable: once published, neither the tag nor the zip can change, so a fix always ships as a new version.

## This wiki

The pages are kept in [`docs/wiki/`](https://github.com/CptNope/JAT-Search-Intel/tree/main/docs/wiki) in the repository and published here automatically when `main` changes. To improve the docs, open a pull request that edits those files.
