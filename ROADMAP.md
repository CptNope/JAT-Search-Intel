# Search Intel — Development Roadmap

> A version-by-version checklist for building Search Intel from v1.0 through v4.0.

---

## v1.0 — Core Launch

### Core Plugin

- [x] Main plugin file with header, constants, autoloader
- [x] Core class (singleton, module loader, settings manager)
- [x] Activation / deactivation hooks
- [x] Uninstall script (clean all plugin data from database)
- [x] WordPress.org-compliant `readme.txt`

### SEO Manager

- [x] SEO title override with `pre_get_document_title` filter
- [x] Meta description tag output
- [x] Canonical URL with override support
- [x] Robots meta directives (noindex, nofollow)
- [x] Open Graph tags (title, description, image, type, URL, site name)
- [x] Twitter Card tags (card type, title, description, image)
- [x] Title separator setting

### Schema Manager

- [x] JSON-LD `@graph` container in `wp_head`
- [x] Organization schema from settings
- [x] WebSite schema with search action
- [x] BreadcrumbList schema
- [x] Article schema for single posts
- [x] FAQPage schema (when FAQs exist)
- [x] HowTo schema (when enabled per-post)
- [x] SpeakableSpecification schema

### AEO Manager

- [x] FAQ auto-detection from heading patterns
- [x] FAQ repeater field (manual Q&A entry)
- [x] HowTo schema builder
- [x] Speakable markup for article intro
- [x] AEO scoring algorithm
- [x] `[search_intel_faq]` shortcode
- [x] `[search_intel_howto]` shortcode

### GEO Manager

- [x] AI meta tags (`ai-summary`, `ai-topics`, `ai-content-type`)
- [x] `llms.txt` endpoint (summary version)
- [x] `llms-full.txt` endpoint (detailed version)
- [x] Entity markup repeater
- [x] E-E-A-T signals (author credentials in schema)
- [x] Content freshness tracking (`dateModified`, fact-check date)
- [x] AI crawl directives meta tags
- [x] GEO scoring algorithm

### Content Analyzer

- [x] Readability scoring (Flesch-Kincaid)
- [x] Keyword density analysis
- [x] Word count check
- [x] SEO score calculation
- [x] AEO score calculation
- [x] GEO score calculation
- [x] AJAX endpoint for live analysis
- [x] Character counter for title and description

### Sitemap

- [x] XML sitemap index at `/sitemap.xml`
- [x] Post type sitemaps with pagination
- [x] Taxonomy sitemaps (categories)
- [x] Automatic noindex exclusion
- [x] `lastmod` support per URL
- [x] Search engine ping on publish
- [x] Disable WordPress default sitemap

### Admin Interface

- [x] Top-level admin menu with sub-pages
- [x] General settings page (site identity, modules, post types)
- [x] SEO settings page (OG, Twitter, canonical, robots)
- [x] AEO settings page (FAQ, HowTo, Speakable config)
- [x] GEO settings page (AI meta, llms.txt, entities, E-E-A-T)
- [x] Sitemap settings page (post types, max URLs)
- [x] Social profiles settings page
- [x] Post editor metabox with tabbed UI (SEO, AEO, GEO, Advanced, Analysis)
- [x] SERP preview in metabox
- [x] FAQ repeater UI
- [x] Entity repeater UI
- [x] Custom columns in post list (SEO/AEO/GEO scores)
- [x] User profile E-E-A-T fields (credentials, expertise, social)
- [x] Admin CSS and JS assets

### Security (Baseline)

- [x] Input sanitization on all saved fields
- [x] Output escaping on all rendered values
- [x] Nonce verification on all forms and AJAX
- [x] Capability checks on all admin actions
- [x] `ABSPATH` guards on all PHP files
- [x] Zero frontend JavaScript or CSS

### Documentation

- [x] `LICENSE.md` (GPL-2.0-or-later)
- [x] `README.md` with architecture overview
- [x] `CHANGELOG.md` with v1.0 release notes
- [x] `ROADMAP.md` (this file)

---

## v1.1 — Feature Completion

### Post Panel — New Fields

- [x] Social share title (separate from SEO title)
- [x] Social share description (separate from meta description)
- [x] Social image override with media library picker
- [x] Secondary keywords field (comma-separated)
- [x] Direct-answer field ("What is this post answering?")
- [x] Citation / source references repeater (`source`, `url`, `date`, `quote`)
- [x] Per-post author expertise notes
- [x] Content intent label (informational, commercial, navigational, educational, transactional)
- [x] Audience label (beginner, intermediate, advanced, professional, general)
- [x] Local relevance toggle + location field

### Title Template Engine

- [x] Variable resolver (`%title%`, `%sitename%`, `%sep%`, `%category%`, `%tag%`, `%date%`, `%author%`, `%page%`, `%excerpt%`)
- [x] Per-context templates: single posts, pages, categories, tags, author archives, search, 404, homepage
- [x] Title template settings UI
- [x] `pre_get_document_title` integration with template engine

### Analysis-on-Save

- [x] Hook into `save_post` / `wp_after_insert_post` for auto-analysis
- [x] Store scores in post meta on every save
- [x] Admin notice when critical issues detected on save

### New Analysis Checks

- [x] H1 presence detection (exactly one H1 or post title as H1)
- [x] Heading hierarchy validation (no skipped levels)
- [x] Internal link opportunity suggestions
- [x] External citation presence scoring
- [x] Answer block opportunity detection
- [x] Entity clarity scoring (key entities in repeater)
- [x] AI summary presence check
- [x] Schema completeness check
- [x] Secondary keyword usage in content
- [x] Social metadata completeness check
- [x] Image alt text presence check

### AEO Additions

- [x] Direct-answer field rendered as meta tag and in Article schema
- [x] Q&A style detection (question-then-answer patterns beyond headings)
- [x] Answer snippet recommendation score (40-word blocks, lists, definitions)
- [x] Auto-generate lead summary from first 2-3 sentences when no excerpt exists

### GEO Additions

- [x] Citations rendered in JSON-LD as `citation` with `@type: CreativeWork`
- [x] Content intent label in meta tag and JSON-LD
- [x] Audience label in meta tag and `schema.org/audience`
- [x] Local relevance `contentLocation` with `@type: Place` in schema

### Expanded Settings

- [x] Default OG image (fallback when no featured image)
- [x] Meta description fallback behavior (excerpt → content trim → empty)
- [x] Meta description max length setting
- [x] Global robots default for new posts
- [x] Taxonomy sitemap toggles (category, post_tag, custom)
- [x] Custom post type sitemap toggles
- [x] AI summary default target word count
- [x] Default content intent selection
- [x] Default audience selection
- [x] Search engine ping toggle (opt-in with disclosure)

### Sitemap Improvements

- [x] Transient-based sitemap caching (12-hour TTL)
- [x] Cache invalidation on post publish/update
- [x] Explicit tags sitemap toggle
- [x] Custom post type sitemap support
- [x] Image sitemap support (extract images from post content)

---

## v1.2 — Hardening & Testing

### Security Audit

- [x] Full input sanitization audit (all `$_POST`, `$_GET`, `$_REQUEST`)
- [x] Full output escaping audit (every `echo`/`printf` uses `esc_html`/`esc_attr`/`esc_url`)
- [x] Nonce coverage check (every form and AJAX handler)
- [x] SQL injection review (all `$wpdb` calls use prepared statements)
- [x] CSRF protection verification (nonces + capabilities on all state changes)
- [x] File upload validation (social image: MIME type, file size, dimensions) — N/A, uses WP media library
- [x] Rate limiting on content analysis AJAX
- [x] Security headers on sitemap, llms.txt, and REST responses (`Cache-Control`, `X-Robots-Tag`)
- [x] Add PHPStan + Psalm to CI for static analysis (Psalm level 4 with baseline)

### Unit Tests (PHPUnit — 193 tests, 348 assertions ✅)

- [x] Title Engine: variable resolution, fallback chain, context detection, custom templates (16 tests)
- [x] Content Analyzer: readability scoring, word count, H1 detection, internal links, keyword density (28 tests)
- [x] SEO Manager: title generation, description fallback, separator, hooks, meta output, canonical (22 tests)
- [x] Schema Manager: knowledge graph, breadcrumbs, article assembly, citations, audience, location (16 tests)
- [x] AEO Manager: FAQ schema, HowTo, Q&A detection, snippet score, direct-answer meta (15 tests)
- [x] GEO Manager: AI meta output, entity markup, freshness, AI crawl directives (16 tests)
- [x] Sitemap: hooks, XML generation, cache TTL, ping hooks (10 tests)
- [x] Admin: hooks, settings sanitization, social profiles, metabox save/load, user E-E-A-T fields (26 tests)
- [x] Core: default settings, singleton, module initialization, settings merge (25 tests)
- [x] Security: nonce verification, input sanitization, entity whitelist, rate limiting, SQL safety, escaping (23 tests)

### Integration Tests (125 tests, 202 assertions ✅)

- [x] Meta output: description, canonical, robots, OG, Twitter Card rendering & escaping (37 tests)
- [x] Schema output: JSON-LD validity, @graph structure, Organization, WebSite, BreadcrumbList, Article (28 tests)
- [x] Schema v1.1: citations, audience, content location, abstract, custom type (28 tests)
- [x] AEO output: direct-answer meta, FAQ schema JSON-LD, lead summary, snippet scoring, Q&A detection (20 tests)
- [x] GEO output: AI meta tags, freshness signals, entity markup, crawl directives, GEO scoring (31 tests)
- [x] Cross-module: metabox save → SEO/AEO/Schema/GEO read cycles, settings round-trip, analyzer integration (25 tests)
- [x] Sitemap output: index XML, post/page sitemaps, changefreq logic, homepage priority, image namespace (16 tests)

### E2E Tests (Playwright — 40/40 passing ✅ against live wp-env)

- [x] Metabox: tab navigation, panel switching, SERP preview (5 tests)
- [x] Character counters: title and description with overflow detection (3 tests)
- [x] Focus keyword field and nonce verification (2 tests)
- [x] FAQ repeater: add, remove, multiple rows, question/answer fields (5 tests)
- [x] Entity repeater: add, remove, visibility (3 tests)
- [x] Citation repeater: add, remove, visibility (3 tests)
- [x] Analysis: Run Analysis button, AJAX score rings (SEO/AEO/GEO), multi-category display (5 tests)
- [x] Settings: all 6 pages load, separator save, OG toggle, sitemap max URLs (10 tests)
- [x] Post list: Search Intel column header, cell rendering, score data (3 tests)
- [x] Auth setup: automated WP login with stored session state (1 test)
- [x] Run against live WP instance via Docker/wp-env ✅

### CI/CD Pipeline (GitHub Actions ✅)

- [x] GitHub Actions workflow (`.github/workflows/ci.yml`)
- [x] PHPUnit test matrix: PHP 8.1, 8.2, 8.3 (8.0 dropped — EOL Nov 2023)
- [x] PHP_CodeSniffer with WordPress-Core standard (WPCS 3.0)
- [x] PHPStan level 6
- [x] ESLint for admin JS
- [x] 100% unit and integration test pass rate gate
- [x] Playwright E2E job (40/40 passing via wp-env)
- [x] Psalm level 4 (with WordPress stubs + baseline for WP hook patterns)
- [x] Stylelint for admin CSS (stylelint-config-standard, 0 errors)
- [x] Performance regression check via PerformanceTest filter in CI

### Performance (Optimized & Benchmarked ✅)

- [x] `wp_head` full pipeline (SEO+Schema+AEO+GEO) < 10ms — verified via PerformanceTest
- [x] SEO meta render (description, canonical, robots, OG, Twitter) < 5ms
- [x] Schema JSON generation < 2ms
- [x] Schema full render (JSON-LD output) < 5ms
- [x] AEO meta render < 2ms
- [x] GEO AI meta + freshness + crawl directives < 3ms
- [x] GEO scoring < 5ms
- [x] Sitemap (cached) < 5ms
- [x] Content analysis < 200ms
- [x] Meta prefetch: `update_meta_cache()` on singular pages — batch all SI meta keys in 1 query
- [x] Admin assets lazy-loaded: JS/CSS only on `post.php` / `post-new.php` for enabled post types
- [x] Sitemap (cold, 1000 posts) 65.62ms — benchmarked via wp-env with 1002 published posts

### Compatibility QA (Defensive Checks & Tests ✅)

- [x] Conflicting SEO plugin detection (Yoast, Rank Math, AIOSEO, The SEO Framework) with admin notice
- [x] Empty / minimal content handling: no fatals in SEO, Schema, AEO, GEO, Analyzer
- [x] WooCommerce-style custom post types: `product` defaults to WebPage schema
- [x] Custom CPTs: analyzer and schema work on arbitrary post types
- [x] Page builder content: shortcode-heavy / Elementor / Divi content handled gracefully
- [x] Unicode / multibyte content: CJK titles, descriptions, and schema headlines preserved
- [x] Very long content: 5000+ word posts analyzed without issues
- [x] Custom schema type overrides via post meta
- [x] Default settings contain all required keys
- [x] All modules disabled produces zero wp_head output
- [x] Sitemap disabled registers no hooks
- [x] Admin assets lazy-loaded only on `post.php` / `post-new.php`
- [x] Live: Classic Editor metabox renders with all tabs (SEO, AEO, GEO, Advanced, Analysis)
- [x] Live: Front-end meta output verified (description, canonical, robots, OG, Twitter, ai-summary, direct-answer, keywords, content-type, content-freshness)
- [x] Live: Schema JSON-LD renders on singular posts (Article, headline, description, abstract)
- [x] Live: Settings pages save and persist (General, SEO, AEO, GEO, Sitemap, Social)
- [x] Live: Post list Search Intel column with score display (3/4 format)
- [x] Live: AJAX content analysis returns SEO/AEO/GEO/Readability score rings
- [x] Bug fix: `speakable_selectors` sanitize crash when array passed to `explode()` (class-admin.php:187)
- [x] Live: Block theme (Twenty Twenty-Four v1.4 FSE) — wp_head fires, SI comment marker + description + canonical + OG + JSON-LD all render correctly
- [ ] Live testing: WPML / Polylang string compatibility — deferred (integration code ships in v3.0 `class-multilang.php`, live testing requires premium plugins)

---

## v1.3 — Bulk Tools & REST API

### Bulk Tools Admin Page

- [x] "Search Intel > Bulk Tools" admin page
- [x] Bulk regenerate metadata (re-run title templates, description fallbacks, auto-excerpts)
- [x] Bulk rescore content (run SEO + AEO + GEO analysis on all posts)
- [x] Bulk generate sitemap cache (pre-warm all transients)
- [x] Export settings as JSON download
- [x] Import settings from JSON upload with validation
- [x] AJAX progress bar for bulk operations (batched AJAX with progress UI)
- [x] Merge vs overwrite option on settings import

### REST API

- [x] `GET /search-intel/v1/geo/{id}` — full GEO metadata for a post
- [x] `GET /search-intel/v1/scores/{id}` — SEO + AEO + GEO scores
- [x] `GET/PUT /search-intel/v1/meta/{id}` — read/write SEO meta fields
- [x] `POST /search-intel/v1/analyze/{id}` — trigger analysis, return results
- [x] `POST /search-intel/v1/bulk/rescore` — queue bulk rescore job
- [x] `GET/PUT /search-intel/v1/settings` — read/write plugin settings (admin only)
- [x] `POST /search-intel/v1/sitemap/invalidate` — clear sitemap cache (admin only)
- [x] REST endpoint toggle in GEO settings
- [x] Authentication via WordPress app passwords (built-in WP REST auth)
- [x] Proper permission callbacks on all endpoints

### WP-CLI Commands

- [x] `wp search-intel analyze <post_id>` — analyze a single post
- [x] `wp search-intel rescore --all` — rescore all published posts
- [x] `wp search-intel regenerate --all` — regenerate metadata for all posts
- [x] `wp search-intel sitemap cache` — pre-warm sitemap caches
- [x] `wp search-intel export > settings.json` — export settings
- [x] `wp search-intel import < settings.json` — import settings
- [x] `wp search-intel scores --format=table` — show all post scores
- [x] `wp search-intel migrate --from=yoast` — migrate from another SEO plugin (CLI command added, migration class in v3.0)

---

## v1.4 — FSE & Block Editor Native

### Gutenberg Sidebar Panel

- [x] `PluginDocumentSettingPanel` React component
- [x] `@wordpress/data` store (`search-intel/editor`)
- [x] Real-time analysis via `subscribe` to editor changes
- [x] SEO fields in sidebar (title, description, keyword, canonical, robots)
- [x] Social fields in sidebar (title, description, image)
- [x] AEO fields in sidebar (direct-answer, FAQ toggle)
- [x] GEO fields in sidebar (AI summary, intent, audience)
- [x] Score display in sidebar
- [x] Keep PHP metabox as fallback for Classic Editor

### Custom Gutenberg Blocks

- [x] `search-intel/faq` — visual FAQ editor with live schema preview
- [x] `search-intel/howto` — step editor with image support per step
- [x] `search-intel/toc` — auto-generated table of contents from headings
- [x] `search-intel/breadcrumbs` — theme-aware breadcrumb navigation
- [x] `search-intel/answer-box` — direct-answer formatting block
- [x] `search-intel/citation` — inline source citation block
- [x] Block previews in editor
- [x] Schema output deduplication (block + wp_head don't double-output)

### FSE Template Integration

- [x] Breadcrumbs block usable in FSE `header.html` template part
- [x] TOC block usable in FSE `single.html` template
- [x] FAQ block usable in FSE templates
- [x] Read `theme.json` color palette for admin score ring UI

### Backward Compatibility

- [x] Classic Editor: PHP metabox unchanged
- [x] Block Editor without sidebar: metabox fallback
- [x] Shortcodes in block content: rendered + deprecation notice
- [x] FSE theme with no blocks inserted: `wp_head` output only

---

## v1.5 — Privacy & Compliance

### GDPR

- [x] Register `wp_privacy_personal_data_exporters` for author user meta
- [x] Register `wp_privacy_personal_data_erasers` for author user meta
- [x] Privacy policy suggested text via `wp_add_privacy_policy_content()`
- [x] Explicit consent checkbox on user profile E-E-A-T section
- [x] Data minimization audit (review all stored fields for necessity)
- [x] Document data processing inventory (privacy policy covers all stored fields)

### General Privacy

- [x] Verify no frontend cookies set by plugin
- [x] Verify no external API calls by default
- [x] Make search engine pinging opt-in with clear disclosure
- [x] Verify no tracking scripts
- [x] Verify all data stays in local database
- [x] CCPA compliance review (no sale/sharing of data, all local storage)
- [x] PIPEDA compliance review (consent-based, exportable, erasable)

---

## v2.0 — Advanced SEO

### Redirect Manager

- [x] 301, 302, 307, 410 redirect rules
- [x] Exact match, prefix match, regex match
- [x] Admin table with search, filter, sort
- [x] Hit counter and last-hit date per redirect
- [x] 404 log with auto-suggest redirect targets
- [x] CSV import/export for redirects
- [x] Slug change auto-redirect detection
- [x] Redirect loop detection and prevention

### IndexNow Integration

- [x] IndexNow API ping on post publish/update
- [x] Support for Bing, Yandex, Naver
- [x] API key management in settings
- [x] Ping log with success/failure status

### WooCommerce Support

- [x] Auto-detect WooCommerce product pages
- [x] Product schema (name, description, image, SKU, brand, GTIN, MPN)
- [x] Offer schema (price, currency, availability, condition, seller)
- [x] AggregateRating from WooCommerce product reviews
- [x] Product breadcrumbs (Home > Shop > Category > Product)
- [x] Product sitemap (`sitemap-product-{page}.xml`)
- [x] Product-specific metabox fields

### Local Business Schema

- [x] LocalBusiness schema type with address, phone, hours
- [x] Geo coordinates (latitude, longitude)
- [x] Service area support
- [x] Multiple locations support
- [x] Opening hours specification

### Additional SEO Tools

- [x] Robots.txt visual editor with syntax validation
- [x] Broken link checker (background HTTP HEAD scan)
- [x] Internal linking suggestions (AI-powered related content)
- [x] Content Security Policy headers on plugin endpoints
- [x] Subresource Integrity for admin assets
- [x] Audit logging for settings changes

---

## v2.1 — Advanced AEO

### Additional Schema Types

- [x] VideoObject (auto-detect YouTube/Vimeo iframes)
- [x] Recipe (ingredients, steps, cook time, nutrition)
- [x] Event (date, location, price, performer, organizer)
- [x] Review / Rating (star rating, pros/cons, itemReviewed)
- [x] Course (provider, duration, price, description)
- [x] SoftwareApplication (OS, price, rating, category)
- [x] JobPosting (salary, location, employment type, company)

### AI-Assisted FAQ Generation

- [x] Local pattern-matching FAQ generator (no API key needed)
- [x] Extract Q&A from headings + following paragraphs
- [x] Optional AI-enhanced generation (user provides API key)
- [x] Suggested FAQs are editable before saving
- [x] Auto-update AEO score after FAQ changes

### Q&A Detection Engine

- [x] Detect H2/H3 headings ending with `?`
- [x] Detect bold `Q:` / `A:` inline patterns
- [x] Detect definition patterns ("X is defined as...")
- [x] Detect numbered step patterns ("Step 1:", "1.")
- [x] Detect comparison patterns ("X vs Y", "difference between")
- [x] Classify content as FAQPage, HowTo, Q&A, or Definition eligible
- [x] Surface classification in AEO analysis tab

---

## v2.5 — Advanced GEO

### AI Visibility Dashboard

- [x] Site-wide GEO health score
- [x] Google Search Console API integration
- [x] Bing Webmaster API integration
- [x] `llms.txt` access log tracking
- [x] AI referral traffic identification and tracking
- [x] Visibility trends over time chart
- [x] Prioritized recommendation list
- [x] Content gap map

### Citation Graph Builder

- [x] Internal link graph visualization
- [x] External citation tracking per post
- [x] Topic cluster auto-detection (shared entities, categories, semantic similarity)
- [x] Pillar page identification and suggestions
- [x] Missing internal link detection
- [x] Orphaned content detection
- [x] Internal linking score per cluster

### Topical Authority Map

- [x] Authority score per topic (based on content volume, freshness, E-E-A-T)
- [x] Content gap analysis vs competitor coverage
- [x] Topic cluster dashboard with drill-down
- [x] Multimodal optimization analysis (image alt text, video transcripts, structured data)

---

## v3.0 — Enterprise & Scale

### Multisite

- [x] Network-level default settings
- [x] Per-site setting overrides
- [x] Centralized reporting across network
- [x] Network admin menu page

### Plugin Migration

- [x] Yoast SEO (titles, descriptions, keywords, robots, canonical, OG, schema, redirects, breadcrumbs)
- [x] Rank Math (titles, descriptions, keywords, robots, canonical, schema, redirects, 404 monitor)
- [x] All in One SEO (titles, descriptions, robots, OG, Twitter, canonical, local SEO)
- [x] SEOPress (titles, descriptions, robots, OG, Twitter, canonical, redirects, schemas)
- [x] The SEO Framework (titles, descriptions, robots, canonical)
- [x] Dry-run mode (preview what will be migrated before committing)

### Role-Based Access Control

- [x] Custom "SEO Editor" role
- [x] Per-module permissions (SEO, AEO, GEO, Sitemap, Settings)
- [x] View-only mode for authors (can see scores, can't change settings)
- [x] Settings lock (prevent non-admins from changing global config)

### Multi-Language Support

- [x] WPML integration (per-language SEO title, description, OG image)
- [x] Polylang integration (per-language SEO title, description, OG image)
- [x] Auto-generate hreflang tags
- [x] Per-language sitemap (separate or combined)
- [x] Per-language `llms.txt`

### White-Label

- [x] Custom plugin name
- [x] Custom branding (logo, colors)
- [x] Remove "Search Intel" references
- [x] Reseller support

### Scale

- [x] Object caching support (Redis / Memcached aware)
- [x] Background processing queue for all heavy operations
- [x] Bulk editor table (mass edit title/description/keyword)
- [x] CDN-friendly (no query string dependencies)
- [x] Headless WordPress support (REST API serves all SEO/AEO/GEO data)

---

## v3.1 — Integrations & Monitoring

### MainWP Integration

- [x] Child-side reporter class (`class-mainwp-reporter.php`) — hooks into `mainwp_child_extra_execution`
- [x] Expose site scores, feature status, post counts via MainWP Child callback API
- [x] Remote actions: trigger rescore, regenerate, toggle features from dashboard
- [ ] Separate MainWP Dashboard Extension plugin (new repo: `search-intel-mainwp`)
- [ ] Dashboard extension: network-wide scores grid with per-site drill-down
- [ ] Dashboard extension: bulk push settings to all child sites
- [ ] Dashboard extension: score threshold alerts (low SEO/AEO/GEO warnings)
- [ ] Dashboard extension: migration status overview across all sites

### WP-CLI for v3.0 Features

- [x] `wp search-intel migrate --from=yoast [--dry-run] [--overwrite]` — run migration from CLI
- [x] `wp search-intel rbac grant <user_id> <module>` — grant module access
- [x] `wp search-intel rbac revoke <user_id> <module>` — revoke module access
- [x] `wp search-intel rbac list` — show all user permissions
- [x] `wp search-intel multisite push-defaults` — push network defaults to all sites
- [x] `wp search-intel multisite report [--format=table|json|csv]` — network score report
- [x] `wp search-intel queue status` — show background queue status
- [x] `wp search-intel queue process [--batch=10]` — manually process queued jobs
- [x] `wp search-intel white-label export > brand.json` — export reseller config
- [x] `wp search-intel white-label import < brand.json` — import reseller config

### Playwright E2E Tests

- [x] `wp-env` Docker setup with WordPress + MySQL (`.wp-env.json`)
- [x] Playwright config targeting `localhost:8080`
- [x] Smoke test: settings page saves and persists (`settings.spec.ts`)
- [x] Smoke test: Classic Editor metabox renders all tabs (`metabox.spec.ts`)
- [x] Smoke test: Bulk Editor table loads and saves (`bulk-editor.spec.ts`)
- [x] Smoke test: Migration wizard detects sources and runs dry-run (`migration.spec.ts`)
- [x] RBAC test: SEO Editor role sees correct UI, author sees view-only (`rbac.spec.ts`)
- [x] White-Label test: custom name appears in admin menu and plugin list (`white-label.spec.ts`)

### Notifications & Scheduled Reports

- [x] Email digest: weekly site health summary (scores, issues, recommendations)
- [x] Score drop alerts: notify when a post's score drops below threshold
- [x] Slack webhook integration for alerts
- [x] Configurable alert thresholds per module (SEO, AEO, GEO)
- [x] Admin dashboard widget: site health at a glance

---

## v3.2 — AI & Content Intelligence

### AI-Powered Content Optimization

- [x] AI meta description generation (OpenAI / Anthropic / Ollama API)
- [x] AI title tag suggestions based on content and keyword
- [x] AI internal link recommendations (based on Citation Graph topology)
- [x] AI content brief generator for gap topics (from Authority Map)
- [x] API key management in settings (encrypted storage)
- [x] Rate limiting and token budget controls
- [x] Ollama / local LLM support for privacy-first deployments

### Content Decay Detection

- [x] Historical score tracking per post (store score snapshots on each analysis)
- [x] Decay detection: flag posts whose scores dropped > 10% over 30 days
- [x] Decay dashboard: sorted list of declining content with trend sparklines
- [x] Auto-queue decaying posts for re-analysis
- [x] "Content Refresh" suggestions based on what changed in the scoring

### Keyword Cannibalization Detector

- [x] Detect multiple posts targeting the same focus keyword
- [x] Cannibalization report: grouped by keyword with score comparison
- [x] Suggested actions: merge, redirect, differentiate, or de-optimize
- [x] Integration with Citation Graph to show link relationships between cannibalizing posts

### Advanced Internal Linking

- [x] Auto-suggest internal links while editing (based on Citation Graph + content similarity)
- [x] Orphaned content alerts in post editor sidebar
- [x] Link equity flow visualization (which posts pass authority to which)
- [x] Broken internal link detection with auto-fix suggestions (redirect or remove)

---

## v3.3 — Analytics & Reporting

### Historical Analytics

- [ ] Per-post score history chart (SEO / AEO / GEO over time)
- [ ] Site-wide score trend dashboard (30 / 60 / 90 day views)
- [ ] Before/after comparison for content updates
- [ ] Score change log (what changed, when, by whom)

### Google Analytics 4 Integration

- [ ] GA4 API connection (Measurement Protocol or Data API)
- [ ] Correlate SEO scores with organic traffic per post
- [ ] Identify high-score / low-traffic posts (distribution opportunities)
- [ ] Identify low-score / high-traffic posts (optimization priorities)

### Core Web Vitals Monitoring

- [ ] PageSpeed Insights API integration
- [ ] Per-page CWV scores (LCP, FID/INP, CLS)
- [ ] CWV trend tracking
- [ ] Correlation with SEO scores

### Custom Reports & Export

- [ ] PDF report generation (site health, per-post breakdown)
- [ ] Scheduled PDF reports via email
- [ ] CSV/JSON export for all report types
- [ ] Custom report builder (pick modules, date range, post types)

---

## v4.0 — Platform & Ecosystem

### Cloud Sync (Optional)

- [ ] Opt-in cloud sync for cross-site analytics
- [ ] Centralized web dashboard (SaaS companion, not required)
- [ ] API key authentication for cloud features
- [ ] Data sovereignty controls (choose region, opt-out anytime)

### Team Collaboration

- [ ] Per-post editorial comments (SEO review notes)
- [ ] Review workflows: request review → approve → publish
- [ ] Activity log: who changed what SEO settings and when
- [ ] @mention notifications for team members

### Marketplace & Extensibility

- [ ] Filter/action API for third-party extensions
- [ ] Custom scoring rule engine (user-defined score weights)
- [ ] Custom schema type registration API
- [ ] Addon ecosystem: premium modules as separate plugins

### WordPress.org Submission

- [ ] Plugin review compliance audit
- [ ] Remove all external API calls from default install
- [ ] Readme.txt fully compliant with WP plugin directory guidelines
- [ ] Asset preparation (banner, icon, screenshots)
- [ ] Submission and review process

---

## Reference

### Compatibility Targets

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| WordPress | 6.0 | 6.5+ |
| PHP | 7.4 | 8.1+ |
| MySQL | 5.7 | 8.0+ |
| MariaDB | 10.3 | 10.6+ |
| Web Server | Apache 2.4 / Nginx 1.18 | Latest stable |
| Memory | 64 MB | 128 MB+ |

### Plugin Compatibility

| Plugin | Status | Notes |
|--------|--------|-------|
| Classic Editor | Full support | PHP metabox, TinyMCE extraction |
| WooCommerce | Basic v1.0, full v2.0 | Product schema in v2.0 |
| WPML | Tested v1.2, integrated v3.0 | hreflang, per-language meta |
| Polylang | Tested v1.2, integrated v3.0 | Same as WPML |
| Elementor | Compatible | `wp_head` fires normally |
| Beaver Builder | Compatible | `wp_head` fires normally |
| Divi | Compatible | `wp_head` fires normally |
| ACF | Compatible | No conflicts |
| WP Rocket | Compatible | Cache-friendly |
| MainWP | Planned v3.1 | Child reporter + dashboard extension |

### Security Principles

- **Defense in depth** — sanitize input AND escape output
- **Least privilege** — check minimum required capability per action
- **Secure defaults** — safe out-of-the-box, advanced features opt-in
- **No trust of client data** — all POST/GET/REQUEST treated as hostile
- **Minimal attack surface** — zero frontend assets, no API keys required by default
- **Fail closed** — security check failure = action denied
- **Dependency hygiene** — no third-party PHP libraries, minimal JS deps

### Quality Gates

| Gate | Threshold | Blocks Release? |
|------|-----------|-----------------|
| WPCS lint | 0 errors | Yes |
| PHPStan level 6 | 0 errors | Yes |
| Unit test pass | 100% | Yes |
| Integration test pass | 100% | Yes |
| E2E test pass | 95%+ | Yes |
| Code coverage (unit) | > 80% | Advisory |
| Code coverage (integration) | > 60% | Advisory |
| Performance regression | < 10% | Yes |

---

_Last updated: March 13, 2026_
_This roadmap is a living document. Check off items as they are completed._
