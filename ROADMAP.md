# Search Intel — Development Roadmap

> A version-by-version checklist for building Search Intel from v1.0 through v3.0.

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
- [ ] Add PHPStan + Psalm to CI for static analysis

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

### Integration Tests (WP Test Suite — ~80 tests)

- [ ] `wp_head` meta tag output validation
- [ ] OG / Twitter / canonical rendering
- [ ] Schema JSON-LD validity and `@graph` structure
- [ ] Citation schema in JSON-LD
- [ ] Sitemap XML validity, pagination, noindex exclusion
- [ ] Sitemap transient caching behavior
- [ ] `llms.txt` content accuracy (summary and full)
- [ ] Metabox save/load cycle (all fields including new v1.1 fields)
- [ ] Settings save/load with sanitization and defaults
- [ ] AJAX analysis endpoint (nonce check, response format)
- [ ] Auto-analysis on `save_post` hook

### E2E Tests (Playwright — ~30 tests)

- [ ] Metabox: all tabs navigate correctly
- [ ] SERP preview updates live
- [ ] Character counters for title and description
- [ ] FAQ repeater: add, edit, remove items
- [ ] Entity repeater: add, edit, remove items
- [ ] Citation repeater: add, edit, remove items
- [ ] Social tab: image picker, title/description preview
- [ ] Analysis: "Run Analysis" button, score display, check list
- [ ] Settings: all settings pages save correctly
- [ ] Post list: custom score columns render

### CI/CD Pipeline

- [ ] GitHub Actions workflow
- [ ] PHP_CodeSniffer with WordPress-Core standard (0 errors)
- [ ] PHPStan level 6 (0 errors)
- [ ] Psalm level 4
- [ ] ESLint for admin JS
- [ ] Stylelint for admin CSS
- [ ] Test matrix: PHP 7.4 + WP 6.0, PHP 8.1 + WP 6.5, PHP 8.3 + WP 6.7
- [ ] 100% unit and integration test pass rate gate
- [ ] 95%+ E2E test pass rate gate
- [ ] Performance regression check (< 10% degradation)

### Performance

- [ ] `wp_head` added time < 5ms
- [ ] Schema JSON generation < 2ms
- [ ] Sitemap (cached, 1000 posts) < 5ms
- [ ] Sitemap (cold, 1000 posts) < 500ms
- [ ] Admin page load impact < 50ms (lazy-load metabox assets)
- [ ] Database queries per page < 6 added (batch `get_post_meta`)
- [ ] Analysis-on-save overhead < 200ms

### Compatibility QA

- [ ] Classic themes (Twenty Twelve through Twenty Twenty-One)
- [ ] Block themes (Twenty Twenty-Two+)
- [ ] Hybrid themes (Astra, GeneratePress, Kadence)
- [ ] Page builders (Elementor, Beaver Builder, Divi)
- [ ] Theme frameworks (Genesis, developer themes)
- [ ] Classic Editor plugin (TinyMCE content extraction)
- [ ] Block Editor / Gutenberg (`wp.data` content extraction)
- [ ] WooCommerce (no fatal errors, product pages work)
- [ ] WPML / Polylang (no conflicts, translatable strings)
- [ ] Custom themes without `wp_head` (admin notice warning)

---

## v1.3 — Bulk Tools & REST API

### Bulk Tools Admin Page

- [ ] "Search Intel > Bulk Tools" admin page
- [ ] Bulk regenerate metadata (re-run title templates, description fallbacks, auto-excerpts)
- [ ] Bulk rescore content (run SEO + AEO + GEO analysis on all posts)
- [ ] Bulk generate sitemap cache (pre-warm all transients)
- [ ] Export settings as JSON download
- [ ] Import settings from JSON upload with validation
- [ ] `WP_Background_Process` for long-running operations
- [ ] AJAX progress bar for bulk operations
- [ ] Merge vs overwrite option on settings import

### REST API

- [ ] `GET /search-intel/v1/geo/{id}` — full GEO metadata for a post
- [ ] `GET /search-intel/v1/scores/{id}` — SEO + AEO + GEO scores
- [ ] `GET/PUT /search-intel/v1/meta/{id}` — read/write SEO meta fields
- [ ] `POST /search-intel/v1/analyze/{id}` — trigger analysis, return results
- [ ] `POST /search-intel/v1/bulk/rescore` — queue bulk rescore job
- [ ] `GET/PUT /search-intel/v1/settings` — read/write plugin settings (admin only)
- [ ] `POST /search-intel/v1/sitemap/invalidate` — clear sitemap cache (admin only)
- [ ] REST endpoint toggle in GEO settings
- [ ] Authentication via WordPress app passwords
- [ ] Proper permission callbacks on all endpoints

### WP-CLI Commands

- [ ] `wp search-intel analyze <post_id>` — analyze a single post
- [ ] `wp search-intel rescore --all` — rescore all published posts
- [ ] `wp search-intel regenerate --all` — regenerate metadata for all posts
- [ ] `wp search-intel sitemap cache` — pre-warm sitemap caches
- [ ] `wp search-intel export > settings.json` — export settings
- [ ] `wp search-intel import < settings.json` — import settings
- [ ] `wp search-intel scores --format=table` — show all post scores
- [ ] `wp search-intel migrate --from=yoast` — migrate from another SEO plugin

---

## v1.4 — FSE & Block Editor Native

### Gutenberg Sidebar Panel

- [ ] `PluginDocumentSettingPanel` React component
- [ ] `@wordpress/data` store (`search-intel/editor`)
- [ ] Real-time analysis via `subscribe` to editor changes
- [ ] SEO fields in sidebar (title, description, keyword, canonical, robots)
- [ ] Social fields in sidebar (title, description, image)
- [ ] AEO fields in sidebar (direct-answer, FAQ toggle)
- [ ] GEO fields in sidebar (AI summary, intent, audience)
- [ ] Score display in sidebar
- [ ] Keep PHP metabox as fallback for Classic Editor

### Custom Gutenberg Blocks

- [ ] `search-intel/faq` — visual FAQ editor with live schema preview
- [ ] `search-intel/howto` — step editor with image support per step
- [ ] `search-intel/toc` — auto-generated table of contents from headings
- [ ] `search-intel/breadcrumbs` — theme-aware breadcrumb navigation
- [ ] `search-intel/answer-box` — direct-answer formatting block
- [ ] `search-intel/citation` — inline source citation block
- [ ] Block previews in editor
- [ ] Schema output deduplication (block + wp_head don't double-output)

### FSE Template Integration

- [ ] Breadcrumbs block usable in FSE `header.html` template part
- [ ] TOC block usable in FSE `single.html` template
- [ ] FAQ block usable in FSE templates
- [ ] Read `theme.json` color palette for admin score ring UI

### Backward Compatibility

- [ ] Classic Editor: PHP metabox unchanged
- [ ] Block Editor without sidebar: metabox fallback
- [ ] Shortcodes in block content: rendered + deprecation notice
- [ ] FSE theme with no blocks inserted: `wp_head` output only

---

## v1.5 — Privacy & Compliance

### GDPR

- [ ] Register `wp_privacy_personal_data_exporters` for author user meta
- [ ] Register `wp_privacy_personal_data_erasers` for author user meta
- [ ] Privacy policy suggested text via `wp_add_privacy_policy_content()`
- [ ] Explicit consent checkbox on user profile E-E-A-T section
- [ ] Data minimization audit (review all stored fields for necessity)
- [ ] Document data processing inventory

### General Privacy

- [ ] Verify no frontend cookies set by plugin
- [ ] Verify no external API calls by default
- [ ] Make search engine pinging opt-in with clear disclosure
- [ ] Verify no tracking scripts
- [ ] Verify all data stays in local database
- [ ] CCPA compliance review
- [ ] PIPEDA compliance review

---

## v2.0 — Advanced SEO

### Redirect Manager

- [ ] 301, 302, 307, 410 redirect rules
- [ ] Exact match, prefix match, regex match
- [ ] Admin table with search, filter, sort
- [ ] Hit counter and last-hit date per redirect
- [ ] 404 log with auto-suggest redirect targets
- [ ] CSV import/export for redirects
- [ ] Slug change auto-redirect detection
- [ ] Redirect loop detection and prevention

### IndexNow Integration

- [ ] IndexNow API ping on post publish/update
- [ ] Support for Bing, Yandex, Naver
- [ ] API key management in settings
- [ ] Ping log with success/failure status

### WooCommerce Support

- [ ] Auto-detect WooCommerce product pages
- [ ] Product schema (name, description, image, SKU, brand, GTIN, MPN)
- [ ] Offer schema (price, currency, availability, condition, seller)
- [ ] AggregateRating from WooCommerce product reviews
- [ ] Product breadcrumbs (Home > Shop > Category > Product)
- [ ] Product sitemap (`sitemap-product-{page}.xml`)
- [ ] Product-specific metabox fields

### Local Business Schema

- [ ] LocalBusiness schema type with address, phone, hours
- [ ] Geo coordinates (latitude, longitude)
- [ ] Service area support
- [ ] Multiple locations support
- [ ] Opening hours specification

### Additional SEO Tools

- [ ] Robots.txt visual editor with syntax validation
- [ ] Broken link checker (background HTTP HEAD scan)
- [ ] Internal linking suggestions (AI-powered related content)
- [ ] Content Security Policy headers on plugin endpoints
- [ ] Subresource Integrity for admin assets
- [ ] Audit logging for settings changes

---

## v2.1 — Advanced AEO

### Additional Schema Types

- [ ] VideoObject (auto-detect YouTube/Vimeo iframes)
- [ ] Recipe (ingredients, steps, cook time, nutrition)
- [ ] Event (date, location, price, performer, organizer)
- [ ] Review / Rating (star rating, pros/cons, itemReviewed)
- [ ] Course (provider, duration, price, description)
- [ ] SoftwareApplication (OS, price, rating, category)
- [ ] JobPosting (salary, location, employment type, company)

### AI-Assisted FAQ Generation

- [ ] Local pattern-matching FAQ generator (no API key needed)
- [ ] Extract Q&A from headings + following paragraphs
- [ ] Optional AI-enhanced generation (user provides API key)
- [ ] Suggested FAQs are editable before saving
- [ ] Auto-update AEO score after FAQ changes

### Q&A Detection Engine

- [ ] Detect H2/H3 headings ending with `?`
- [ ] Detect bold `Q:` / `A:` inline patterns
- [ ] Detect definition patterns ("X is defined as...")
- [ ] Detect numbered step patterns ("Step 1:", "1.")
- [ ] Detect comparison patterns ("X vs Y", "difference between")
- [ ] Classify content as FAQPage, HowTo, Q&A, or Definition eligible
- [ ] Surface classification in AEO analysis tab

---

## v2.5 — Advanced GEO

### AI Visibility Dashboard

- [ ] Site-wide GEO health score
- [ ] Google Search Console API integration
- [ ] Bing Webmaster API integration
- [ ] `llms.txt` access log tracking
- [ ] AI referral traffic identification and tracking
- [ ] Visibility trends over time chart
- [ ] Prioritized recommendation list
- [ ] Content gap map

### Citation Graph Builder

- [ ] Internal link graph visualization
- [ ] External citation tracking per post
- [ ] Topic cluster auto-detection (shared entities, categories, semantic similarity)
- [ ] Pillar page identification and suggestions
- [ ] Missing internal link detection
- [ ] Orphaned content detection
- [ ] Internal linking score per cluster

### Topical Authority Map

- [ ] Authority score per topic (based on content volume, freshness, E-E-A-T)
- [ ] Content gap analysis vs competitor coverage
- [ ] Topic cluster dashboard with drill-down
- [ ] Multimodal optimization analysis (image alt text, video transcripts, structured data)

---

## v3.0 — Enterprise & Scale

### Multisite

- [ ] Network-level default settings
- [ ] Per-site setting overrides
- [ ] Centralized reporting across network
- [ ] Network admin menu page

### Plugin Migration

- [ ] Yoast SEO (titles, descriptions, keywords, robots, canonical, OG, schema, redirects, breadcrumbs)
- [ ] Rank Math (titles, descriptions, keywords, robots, canonical, schema, redirects, 404 monitor)
- [ ] All in One SEO (titles, descriptions, robots, OG, Twitter, canonical, local SEO)
- [ ] SEOPress (titles, descriptions, robots, OG, Twitter, canonical, redirects, schemas)
- [ ] The SEO Framework (titles, descriptions, robots, canonical)
- [ ] Dry-run mode (preview what will be migrated before committing)

### Role-Based Access Control

- [ ] Custom "SEO Editor" role
- [ ] Per-module permissions (SEO, AEO, GEO, Sitemap, Settings)
- [ ] View-only mode for authors (can see scores, can't change settings)
- [ ] Settings lock (prevent non-admins from changing global config)

### Multi-Language Support

- [ ] WPML integration (per-language SEO title, description, OG image)
- [ ] Polylang integration (per-language SEO title, description, OG image)
- [ ] Auto-generate hreflang tags
- [ ] Per-language sitemap (separate or combined)
- [ ] Per-language `llms.txt`

### White-Label

- [ ] Custom plugin name
- [ ] Custom branding (logo, colors)
- [ ] Remove "Search Intel" references
- [ ] Reseller support

### Scale

- [ ] Object caching support (Redis / Memcached aware)
- [ ] Background processing queue for all heavy operations
- [ ] Bulk editor table (mass edit title/description/keyword)
- [ ] CDN-friendly (no query string dependencies)
- [ ] Headless WordPress support (REST API serves all SEO/AEO/GEO data)

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

_Last updated: March 2026_
_This roadmap is a living document. Check off items as they are completed._
