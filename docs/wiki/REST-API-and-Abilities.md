# REST API and Abilities

## REST API

All routes are under the `jat-search-intel/v1` namespace, for example `https://example.com/wp-json/jat-search-intel/v1/meta/42`. The main API can be switched off under **Settings → General**. Authenticate as you would for any WordPress REST request: cookie plus nonce in the admin, or an application password.

"Public" routes only answer for **published, non-password-protected posts of a public post type**. For anything else they need edit access to that post. Fields that are only for editors (focus keyword, secondary keywords, expertise notes and scores) are removed for visitors.

| Method | Route | Who can call it | What it does |
|---|---|---|---|
| `GET` | `/scores/{id}` | users who can edit the post | SEO, AEO, GEO and readability scores |
| `GET` | `/geo/{id}` | public | GEO metadata (the GEO score only for editors) |
| `GET` | `/meta/{id}` | public | SEO meta fields |
| `PUT` | `/meta/{id}` | users who can edit the post | update SEO meta fields |
| `POST` | `/analyze/{id}` | users who can edit the post | run the content analysis and store the scores |
| `POST` | `/bulk/rescore` | administrators | rescore a batch; `post_type` (default `post`), `batch_size` (default 50), `offset` |
| `GET` / `PUT` | `/settings` | administrators | read or update plugin settings |
| `POST` | `/sitemap/invalidate` | administrators | clear the sitemap caches |
| `POST` | `/coach/{id}` | users who can edit the post | Passage Coach findings for unsaved `content` and `title` |
| `GET` | `/seo/{id}` | public | compact SEO data for headless front ends |
| `GET` | `/aeo/{id}` | public | FAQ, HowTo and direct-answer data for headless front ends |
| `GET` | `/link-suggestions/{id}` | users who can edit posts | internal link suggestions (Internal linking module) |
| `GET` | `/orphan-check/{id}` | users who can edit posts | whether the post has incoming internal links |
| `GET` | `/history/{id}` | users who can edit the post | the post's SEO change history |
| `POST` | `/history/{id}/restore` | users who can edit the post | put back the values an `entry` changed |
| `GET` | `/schema-map` | public | XML list of schema feeds (see [Features](Features#geo)) |
| `GET` | `/schema/{type}/{page}` | public | one feed: the site's Organization/Person and WebSite nodes plus the schema of up to 200 public, indexable posts |

### Example

```bash
curl -u "editor:APPLICATION-PASSWORD" \
  -X PUT https://example.com/wp-json/jat-search-intel/v1/meta/42 \
  -H "Content-Type: application/json" \
  -d '{"title":"Best trail shoes for 2026","description":"We tested 14 pairs on rock, mud and road."}'
```

### Post meta in the core REST API

The plugin's post meta fields are also registered with `show_in_rest`, so the block editor can read and save them through `/wp/v2/posts/{id}`. Writing them requires edit access to that specific post.

## Abilities

On WordPress 6.9 and later, the plugin registers twelve abilities in the `jat-search-intel` category with the WordPress Abilities API. Any client that speaks the Abilities API can call them, including AI agents connected through the [MCP Adapter](https://github.com/WordPress/mcp-adapter).

| Ability | Who can run it | Type | What it does |
|---|---|---|---|
| `jat-search-intel/search-content` | anyone | read-only | search published, indexable content |
| `jat-search-intel/get-page-seo` | anyone | read-only | title, description, canonical, schema type and AI summary of a published, indexable page |
| `jat-search-intel/analyze-post` | users who can edit the post | runs analysis | SEO, AEO and GEO scores, recommendations and Passage Coach findings |
| `jat-search-intel/update-seo-meta` | users who can edit the post | writes | update the SEO title, description and related fields |
| `jat-search-intel/site-readiness` | administrators | read-only | the AI Search Readiness audit and score |
| `jat-search-intel/refresh-queue` | editors (`edit_others_posts`) | read-only | pages most in need of a refresh |
| `jat-search-intel/get-term-seo` | anyone | read-only | SEO fields of a public category, tag or other taxonomy term (the focus keyword only for users who can edit it) |
| `jat-search-intel/update-term-seo` | users who can edit the term | writes | update a term's SEO fields; fields you leave out are not changed |
| `jat-search-intel/posts-needing-attention` | editors (`edit_others_posts`) | read-only | published posts with the lowest SEO, AEO or GEO score, or no meta description |
| `jat-search-intel/get-index-status` | users who can edit the post | reads, or refreshes with `refresh` | Google index status of a post from Search Console |
| `jat-search-intel/list-redirects` | administrators | read-only | redirect rules, optionally filtered by a search term |
| `jat-search-intel/create-redirect` | administrators | writes | add a 301, 302, 307 or 410 redirect (exact, prefix or regex) |

Content marked `noindex` or "hide from AI" is never returned by the public abilities. Read-only abilities are called with `GET`, and the others with `POST`:

```bash
curl -u "admin:APPLICATION-PASSWORD" \
  "https://example.com/wp-json/wp-abilities/v1/abilities/jat-search-intel/site-readiness/run"
```

When an ability updates meta, the [`jat_search_intel_seo_meta_updated`](Developer-Hooks#jat_search_intel_seo_meta_updated) action fires with `'ability'` as the source.
