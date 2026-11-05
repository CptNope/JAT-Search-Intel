## What this changes

<!-- One or two sentences. Link the issue if there is one (Fixes #123). -->

## How it was tested

<!-- Tests added or run, and anything checked by hand on a WordPress site. -->

## Checklist

- [ ] `vendor/bin/phpunit`, `vendor/bin/phpcs`, `vendor/bin/phpstan analyse` and `vendor/bin/psalm` pass
- [ ] User input is sanitized, output is escaped, and actions check capabilities and nonces
- [ ] New strings use the `jat-search-intel` text domain
- [ ] `readme.txt` changelog updated if users will notice the change
- [ ] If `src/` changed: `npm run build` was run and `build/` is committed
