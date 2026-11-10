# WP-CLI

All commands are under `wp jat-search-intel`. Run `wp help jat-search-intel <command>` for every option.

## Scores and analysis

| Command | What it does |
|---|---|
| `wp jat-search-intel analyze 42` | Analyze one post and print its scores |
| `wp jat-search-intel analyze 42 --save` | Analyze it and store the scores |
| `wp jat-search-intel rescore --all` | Rescore all content, with a progress bar |
| `wp jat-search-intel rescore --all --post_type=page` | Rescore one post type |
| `wp jat-search-intel scores` | Score table for your content |
| `wp jat-search-intel scores --format=csv --limit=100` | Output as `table`, `csv`, `json` or `yaml` |
| `wp jat-search-intel scores --orderby=seo` | Sort by a score |

## Metadata

| Command | What it does |
|---|---|
| `wp jat-search-intel regenerate --all` | Fill in empty meta fields (existing values are kept) |
| `wp jat-search-intel regenerate --all --post_type=page` | For one post type |

## Sitemaps

| Command | What it does |
|---|---|
| `wp jat-search-intel sitemap cache` | Build and cache all sitemaps |
| `wp jat-search-intel sitemap clear` | Clear the sitemap caches |

## AI citations

| Command | What it does |
|---|---|
| `wp jat-search-intel citations` | Show the latest result for each tracked question and engine: cited, read, no or error |
| `wp jat-search-intel citations --check` | Ask every engine every question now (uses your API keys), then show the results |
| `wp jat-search-intel citations --format=json` | Any of table, csv, json or yaml |

## Google index status

Needs the Search Console connection (see [Features](Features#seo)).

| Command | What it does |
|---|---|
| `wp jat-search-intel index-status` | Check the next 25 pages: never-checked pages first, then the oldest results |
| `wp jat-search-intel index-status --limit=100` | Check more at once (Google allows about 2,000 inspections a day per property) |
| `wp jat-search-intel index-status --post=42` | Check one post and print the result |

## Settings

| Command | What it does |
|---|---|
| `wp jat-search-intel export > settings.json` | Export settings as JSON |
| `wp jat-search-intel import settings.json` | Import and merge settings |
| `wp jat-search-intel import settings.json --mode=overwrite` | Import and replace settings |

## Migration from other SEO plugins

`--from` takes `yoast`, `rankmath`, `aioseo`, `seopress` or `seoframework`.

| Command | What it does |
|---|---|
| `wp jat-search-intel migrate --from=yoast --dry-run` | Show what would be imported |
| `wp jat-search-intel migrate --from=yoast` | Import, keeping any JAT Search Intel values already set |
| `wp jat-search-intel migrate --from=rankmath --overwrite` | Import and overwrite existing values |

## Roles, multisite and queue

| Command | What it does |
|---|---|
| `wp jat-search-intel rbac list` | Show which users can edit which fields |
| `wp jat-search-intel rbac grant 5 seo` | Let user 5 edit SEO fields (`seo`, `aeo` or `geo`) |
| `wp jat-search-intel rbac revoke 5 aeo` | Remove that access |
| `wp jat-search-intel multisite push-defaults` | Copy this site's settings to every site in the network |
| `wp jat-search-intel multisite report --format=json` | Network-wide score report |
| `wp jat-search-intel queue status` | Background job queue status |
| `wp jat-search-intel queue process --batch=25` | Process queued jobs now |
