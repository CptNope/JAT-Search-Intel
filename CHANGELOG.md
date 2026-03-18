# Changelog

All notable changes to Search Intel will be documented in this file.

This project adheres to [Semantic Versioning](https://semver.org/) and follows the [Keep a Changelog](https://keepachangelog.com/) format.

---

## [3.4.1] — 2026-03-17

### Added
- **FAQ Generation button**: "Generate FAQs" button with "Enhance with AI" toggle in the AEO tab — works with any configured AI provider via the AI Engine abstraction
- **AI Suggest Edits**: New button in the Analysis tab generates revision suggestions based on failing SEO/AEO/GEO/Readability checks
- **Score history**: Analysis tab now shows a table of the last 10 analysis runs with date and all four scores
- **Bulk FAQs**: FAQs field added to Bulk Tools AI generation (AEO section) — generates 5 Q&A pairs per post
- **Pre-populated scores**: Score rings in the Analysis tab load saved scores on page load instead of showing dashes

### Fixed
- **FAQ generation broken**: `enhance_with_ai()` was calling the OpenAI API directly with a separate `faq_ai_api_key` setting — refactored to use the AI Engine abstraction
- **FAQ AJAX handlers gated**: `si_generate_faqs` and `si_save_faqs` were only registered when `enable_faq_generator` was true — now always available for the main metabox AEO tab

---

## [3.4.0] — 2026-03-17

### Added
- **AI Optimizer**: New score-driven AI content improvement engine — after scoring, the AI analyzes failing SEO/AEO/GEO/Readability checks and generates actionable suggestions (meta descriptions, FAQ schema, AI summaries, focus keywords, entity extraction, question-style headings, readability rewrites) with one-click "Apply" for auto-applicable fixes
- **AI Optimizer meta box**: Sidebar widget in the post editor shows current scores, issue count, and AI-generated suggestions with Apply/Review actions
- **Readability tracking**: Score history, site-wide aggregates, before/after comparisons, and change logs now include readability alongside SEO/AEO/GEO

### Fixed
- **Analytics history never populated**: `search_intel_post_analyzed` action was never fired — now fires from both `analyze_on_save` and bulk rescore, so score history, change logs, and site-wide trends actually populate
- **Bulk rescore wrong result keys**: Bulk rescore read `$results['seo']['score']` instead of `$results['seo_score']['score']`, causing scores to silently save as 0
- **Change log never recorded**: `log_change()` existed but was never called — now automatically logs every score change with old→new values, action context, and user ID

---

## [3.3.6] — 2026-03-17

### Fixed
- **Bulk rescore/regenerate only processed one post type**: Added "All Public Post Types" option to the bulk tools dropdown — rescore, regenerate, and AI generate now process posts, pages, and all CPTs in a single run
- **Reports readability module**: Added Readability as a selectable module in the Reports form

---

## [3.3.5] — 2026-03-17

### Fixed
- **llms.txt 404**: Rewrite rules for `llms.txt` and `llms-full.txt` were never flushed on activation — added to activation hook alongside sitemap rules
- **llms.txt served by wrong plugin**: `template_redirect` handler now runs at priority 1 so Search Intel's llms.txt takes precedence over other plugins
- **Reports showing zero scores**: `build_report()` was reading from a non-existent `_search_intel_scores` meta key — fixed to read from individual `_search_intel_seo_score`, `_search_intel_aeo_score`, `_search_intel_geo_score`, `_search_intel_readability_score` keys
- **Psalm CI failure**: Removed stale `PossiblyUnusedReturnValue` baseline entry for `class-core-web-vitals.php`

---

## [3.3.4] — 2026-03-17

### Fixed
- **Settings Persistence**: `sanitize_settings()` now handles ALL keys from `get_default_settings()` — sitemap taxonomies, IndexNow providers, API keys, numeric fields, white-label fields, notification settings, and 30+ other fields that were silently dropped on save
- **Sitemap CPT/Taxonomy**: Custom post type and taxonomy selections now persist correctly across saves

### Added
- **Core Web Vitals Configuration**: Inline PSI API key setup with save button and manual scan trigger directly on the Web Vitals page
- **GA4 Configuration**: Inline GA4 Property ID setup with save button directly on the GA4 Insights page

---

## [3.3.3] — 2026-03-17

### Added
- **Module Toggles**: 27 feature toggles added to General Settings page, organized into 8 categories (Core, Advanced SEO, Advanced AEO, Advanced GEO, AI & Content Intelligence, Analytics & Reporting, Enterprise, Security)
- **Admin Pages Exposed**: All v2.0+ admin pages now accessible when their module toggle is enabled — adds 12 new menu items: Visibility, Citation Graph, Authority Map, Content Decay, Cannibalization, Analytics, GA4 Insights, Web Vitals, Reports, Bulk Editor, Internal Linking, Broken Links

### Fixed
- **Settings Persistence**: `sanitize_settings()` now includes all `enable_*` boolean keys so toggled modules persist on save

---

## [3.3.2] — 2026-03-17

### Added
- **AI Engine Settings Page**: New admin page (Search Intel → AI Engine) with provider selection (OpenAI, Anthropic, Ollama), encrypted API key management with save/test buttons, model override, custom endpoint, rate limiting, token budget, and usage statistics
- **Bulk AI Content Generation**: New section in Bulk Tools for AI-powered generation of meta descriptions, AI summaries, and focus keywords with empty-only or overwrite-all modes

### Fixed
- **Sitemap 404**: Register rewrite rules before `flush_rewrite_rules()` in activation hook so WordPress recognizes `/sitemap.xml` immediately

---

## [3.3.1] — 2026-03-17

### Fixed
- **PHPCS**: Added `phpcs.xml.dist` with project-specific ruleset (WordPress-Core base with short array syntax, space indentation)
- **PHPStan**: Resolved all level 6 errors — cast `$post->post_author` to `(int)`, fixed nullable params, removed dead code, added WordPress constant stubs
- **ESLint**: Added `tinymce` to globals, regenerated lockfile after `ajv` override removal
- **Psalm**: Removed stale baseline entries for fixed `RedundantCondition`
- **Playwright E2E**: Increased `beforeAll` login timeout for slow CI environments
- **CI Workflow**: Updated to `actions/checkout@v5`, `actions/setup-node@v5`, Node.js 22; fixed PHPCS `cs2pr` pipe with `-q` flag

### Security
- Added `wp_unslash()` to `$_REQUEST` data in Custom Reports
- Validated report frequency against allowed values whitelist
- Fixed mixed escaping in report admin page output

---

## [3.3.0] — 2026-03-16

### Added

#### Historical Analytics (`class-historical-analytics.php`)
- Per-post score history recording (SEO/AEO/GEO over time, 365-day retention)
- Chart-ready data generation for single-module or all-module views (30/60/90 days)
- Site-wide daily score aggregation via cron for trend dashboards
- Before/after comparison around a target date with delta calculation
- Score change log per post (what changed, when, by whom, old vs new scores)
- Recent changes summary across all posts with configurable lookback
- Analytics admin page with period selector and change log table

#### Google Analytics 4 Integration (`class-ga4-integration.php`)
- GA4 Data API connection via OAuth access tokens
- Organic traffic fetch with per-URL session and engaged session counts
- Post-level traffic lookup with permalink-to-path matching
- Score-traffic correlation report (scores alongside 30-day organic sessions)
- Distribution opportunities: high-score / low-traffic posts for promotion
- Optimization priorities: low-score / high-traffic posts for quick wins
- GA4 Insights admin page with opportunity and priority tables

#### Core Web Vitals Monitoring (`class-core-web-vitals.php`)
- PageSpeed Insights API integration with mobile/desktop strategy support
- Per-post CWV data storage (LCP, CLS, INP, performance score, 90-day history)
- Metric classification (good / needs-improvement / poor) using Google thresholds
- CWV trend data for charting per-post performance over time
- CWV-vs-SEO correlation report (CWV metrics alongside SEO/AEO/GEO scores)
- Weekly batch scan via cron (randomized post selection)
- Web Vitals admin dashboard with color-coded metric classification

#### Custom Reports & Export (`class-custom-reports.php`)
- Report builder: configurable modules, post types, date range, and limit
- CSV export with proper headers and data formatting
- JSON export with pretty-printed structure
- PDF-ready HTML generation with styled tables and summary
- Scheduled reports: daily/weekly/monthly email delivery with HTML reports
- Schedule management: save, delete, and process due schedules
- Reports admin page with export form and schedule overview

### Tests
- 828 unit tests, 1,880 assertions (63 new tests)
- Psalm level 4: 0 errors

---

## [3.2.0] — 2026-03-13

### Added

#### AI Engine (`class-ai-engine.php`)
- Unified LLM abstraction layer with provider support for OpenAI, Anthropic, and Ollama (local)
- Encrypted API key storage using AES-256-CBC with WordPress salts
- Per-minute rate limiting with configurable threshold (default 30 req/min)
- Monthly token budget controls with automatic reset and usage tracking
- AI meta description generation (120-155 chars, keyword-aware)
- AI title tag suggestions (3 alternatives, 50-60 chars each)
- AI internal link recommendations using Citation Graph topology
- AI content brief generator from Authority Map gap analysis

#### Content Decay Detection (`class-content-decay.php`)
- Historical score snapshots stored per post on every analysis (90-day retention)
- Decay detection: flag posts whose SEO/AEO/GEO scores dropped > threshold% over lookback period
- Decay Dashboard admin page: sorted list of declining content with drop percentages
- Auto-queue decaying posts for re-analysis via Scale background queue
- Content refresh suggestions based on which modules declined and by how much
- Score trend data API for sparkline visualizations (configurable period)
- Score change calculator with absolute and percentage deltas

#### Keyword Cannibalization Detector (`class-keyword-cannibalization.php`)
- Keyword index builder: detect multiple posts targeting the same focus keyword
- Cannibalization report: grouped by keyword with SEO/AEO/GEO score comparisons
- Smart action suggestions: merge, redirect, differentiate, or de-optimize based on score gaps
- Citation Graph integration: show internal link relationships between cannibalizing posts
- Admin dashboard with per-keyword cards, best-performer highlighting, and recommendation banners
- Transient caching with auto-invalidation on post save

#### Advanced Internal Linking (`class-advanced-linking.php`)
- Content-similarity link suggestion engine (keyword overlap, title match, taxonomy, graph proximity)
- REST API endpoints for editor integration (`/link-suggestions/{id}`, `/orphan-check/{id}`)
- Orphaned content detection (zero inbound internal links) with Citation Graph or fallback search
- Link equity flow visualization: nodes (posts) with inbound/outbound counts and edges
- Broken internal link scanner: daily cron, per-post checks, status classification
- Auto-fix suggestions for broken links: redirect, restore, or remove
- Anchor text suggestion algorithm (progressive substring matching from target title)

### Tests
- 765 unit tests, 1,747 assertions (up from 690/1,615)
- Psalm level 4: 0 errors

---

## [3.1.0] — 2026-03-13

### Added

#### MainWP Child-Side Reporter (`class-mainwp-reporter.php`)
- Detect MainWP Child plugin and hook into `mainwp_child_extra_execution`
- Expose site scores, feature status, scored post counts via MainWP Child callback API
- Remote actions: trigger rescore, regenerate, toggle features, push settings from dashboard
- Inject sync data (site summary, aggregate scores) into MainWP heartbeat

#### Notifications & Scheduled Reports (`class-notifications.php`)
- Weekly email digest with site health summary (score averages, worst posts, recommendations)
- Score drop alerts: detect posts below configurable per-module thresholds (SEO/AEO/GEO)
- Slack webhook integration for both digest summaries and score drop alerts
- Score snapshot storage for historical comparison (`search_intel_score_snapshots` option)
- Admin dashboard widget: site health at a glance with colored score bars and attention list
- Cron events: `search_intel_weekly_digest` (weekly) and `search_intel_score_drop_check` (daily)
- Default notification settings wired into Core: `enable_notifications`, `notification_email`, `alert_threshold_seo/aeo/geo`, `slack_webhook_url`

#### WP-CLI for v3.0 Features
- `wp search-intel rbac grant <user_id> <module>` — grant module access
- `wp search-intel rbac revoke <user_id> <module>` — revoke module access
- `wp search-intel rbac list` — show all user permissions
- `wp search-intel multisite push-defaults` — push network defaults to all sites
- `wp search-intel multisite report [--format=table|json|csv]` — network score report
- `wp search-intel queue status` — show background queue status
- `wp search-intel queue process [--batch=10]` — manually process queued jobs
- `wp search-intel white-label export > brand.json` — export reseller config
- `wp search-intel white-label import < brand.json` — import reseller config

#### Playwright E2E Tests
- `wp-env` Docker setup with WordPress + MySQL (`.wp-env.json`, port 8080)
- Bulk Editor smoke tests with graceful skip when Scale is disabled
- Migration Wizard smoke tests (page load, structure, supported plugins)
- RBAC smoke tests (admin access, author restriction with skip when user missing)
- White-Label smoke tests (custom name in admin menu, plugins list, settings header)

### Tests
- 690 unit tests, 1,615 assertions (up from 665/1,533)
- 58 Playwright E2E tests (51 pass, 7 skip for disabled features)
- Psalm level 4: 0 errors

---

## [3.0.0] — 2026-03-13

### Added

#### Multisite Support (`class-multisite.php`)
- Network-level default settings with `get_site_option` storage
- Per-site setting overrides with explicit override tracking
- Centralized reporting across all network sites (scores, features, overrides)
- Network admin menu page under Settings
- Auto-apply network defaults to newly created sites

#### Plugin Migration Engine (`class-migration.php`)
- Yoast SEO migration (titles, descriptions, keywords, robots, canonical, OG, redirects)
- Rank Math migration (titles, descriptions, keywords, robots, canonical, schema, redirects, 404 log)
- All in One SEO migration (titles, descriptions, robots, OG, Twitter, canonical)
- SEOPress migration (titles, descriptions, robots, canonical, OG, Twitter, redirects)
- The SEO Framework migration (titles, descriptions, robots, canonical)
- Dry-run mode with per-field preview and sample data
- Merge vs overwrite import mode
- Redirect migration with deduplication

#### Role-Based Access Control (`class-rbac.php`)
- Custom "SEO Editor" role with module-specific capabilities
- Per-module permissions (SEO, AEO, GEO, Sitemap, Settings)
- View-only mode for authors (can see scores, can't change settings)
- Settings lock (prevent non-admins from changing global config)
- Grant/revoke individual module capabilities per user
- Permissions summary report

#### Multi-Language Support (`class-multilang.php`)
- WPML integration (language detection, translations, hreflang)
- Polylang integration (language detection, translations, hreflang)
- Auto-generate hreflang tags on singular pages with x-default
- Per-language SEO meta fields (title, description, OG image)
- Per-language sitemap URLs with xhtml:link alternates
- Per-language `llms.txt` content generation

#### White-Label (`class-white-label.php`)
- Custom plugin name, URI, author, and description
- Custom branding (logo URL, brand color)
- Remove "Search Intel" references via `all_plugins` filter
- Admin CSS injection for branded pages
- Reseller config export/import

#### Scale & Performance (`class-scale.php`)
- Object caching support (Redis / Memcached aware via `wp_using_ext_object_cache`)
- Background processing queue with batch execution and auto-cleanup
- Bulk editor table (mass edit title/description/keyword)
- CDN-friendly asset URLs (file-mod-time versioning, no query strings)
- Headless WordPress REST API: `/search-intel/v1/seo/{id}`, `/aeo/{id}`, `/scores/{id}`, `/meta/{id}`

### Tests
- 665 unit tests, 1,533 assertions (up from 574/1,381)

---

## [2.5.0] — 2026-03-13

### Added

#### AI Visibility Dashboard (`class-visibility-dashboard.php`)
- Site-wide GEO health score (0–100) with 6 weighted factors
- Google Search Console API integration for search analytics
- Bing Webmaster API integration for query stats
- llms.txt access log tracking with AI bot identification
- AI referral traffic identification (ChatGPT, Perplexity, Claude, Gemini, Copilot, etc.)
- Visibility score trends over time with daily snapshots
- Prioritized recommendation engine based on health score gaps
- Content gap map across categories with coverage analysis
- Admin dashboard page with score card, trends, and logs
- Daily cron for automated visibility data sync

#### Citation Graph Builder (`class-citation-graph.php`)
- Internal link graph builder across all published content
- External citation tracking per post with domain distribution
- Topic cluster auto-detection based on shared categories and link density
- Pillar page identification via inbound link count, outbound links, and word count
- Missing internal link detection within topic clusters
- Orphaned content detection (posts with zero inbound links)
- Internal linking score per cluster (0–100)
- Auto-update link graph on post save
- Admin page with cluster table, pillar pages, and orphan list

#### Topical Authority Map (`class-authority-map.php`)
- Authority score per topic (0–100) based on volume, freshness, E-E-A-T, linking, schema
- Content gap analysis with per-topic recommendations
- Topic cluster dashboard with drill-down post details
- Multimodal optimization analysis (image alt text, video embeds, structured data)
- Admin page with authority scores, gap recommendations, and multimodal report

### Changed
- Core: added `$visibility_dashboard`, `$citation_graph`, `$authority_map` properties
- Default settings: `enable_visibility_dashboard`, `enable_citation_graph`, `enable_authority_map`, API key/URL settings

### Tests
- 574 unit tests, 1,381 assertions (up from 509/1,153)

---

## [2.1.0] — 2026-03-13

### Added

#### Additional Schema Types (`class-schema-types.php`)
- VideoObject with auto-detect YouTube/Vimeo iframes from post content
- Recipe schema (name, ingredients, steps, cook time, prep time, servings, nutrition)
- Event schema (name, date, location, performer, organizer, price)
- Review schema (star rating, pros/cons lists, itemReviewed with type)
- Course schema (provider, duration, price, description)
- SoftwareApplication schema (OS, category, price, aggregate rating)
- JobPosting schema (title, company, location, employment type, salary)
- Schema type injection into singular graph via `search_intel_singular_schema` filter

#### Q&A Detection Engine (`class-qa-detector.php`)
- Detect H2/H3 headings ending with `?` and extract answers
- Detect bold `Q:` / `A:` inline patterns
- Detect definition patterns ("X is defined as...", "X refers to...", etc.)
- Detect numbered step patterns ("Step 1:", ordered lists)
- Detect comparison patterns ("X vs Y", "difference between X and Y")
- Classify content as FAQPage, HowTo, QAPage, or DefinedTerm eligible
- Auto-inject detected FAQ into schema via filter

#### AI-Assisted FAQ Generation (`class-faq-generator.php`)
- Local pattern-matching FAQ generator (no API key needed)
- Extract Q&A from headings + following paragraphs
- Generate implicit questions from informational headings
- Optional AI-enhanced generation via OpenAI API (user provides API key)
- Editable FAQ suggestions with admin metabox
- AJAX save/generate endpoints with nonce verification
- Deduplication by question similarity

### Changed
- Core: added `$schema_types`, `$qa_detector`, `$faq_generator` properties
- Default settings: `enable_schema_types`, `enable_qa_detector`, `enable_faq_generator`, `faq_ai_api_key`

### Tests
- 509 unit tests, 1,153 assertions (up from 437/987)

---

## [2.0.1] — 2026-03-13

### Added

#### Broken Link Checker (`class-link-checker.php`)
- Background HTTP HEAD scan via WP-Cron (hourly batches)
- Extract all links from published post content
- Detect broken links (400, 403, 404, 405, 408, 410, 5xx)
- Admin page listing broken links with status, source post, error, and check time
- Clear results and manual re-scan support

#### Internal Linking Suggestions (`class-internal-linker.php`)
- Keyword extraction with stop-word filtering
- Shared taxonomy term matching for related content discovery
- Keyword overlap and title match scoring (0–100 relevance)
- Suggestion caching via transients (auto-invalidated on save)
- Editor metabox showing top 10 link suggestions with scores

#### Content Security Policy Headers (`class-csp-headers.php`)
- CSP headers on all Search Intel admin pages
- Strict CSP (`default-src 'none'`) on REST API endpoints under `/search-intel/`
- X-Content-Type-Options, X-Frame-Options, Referrer-Policy headers
- Configurable via `enable_csp_headers` setting

#### Subresource Integrity (`class-sri.php`)
- SHA-256 integrity hashes for plugin CSS and JS assets
- Automatic `integrity` and `crossorigin="anonymous"` attribute injection
- Hash caching in options (regenerated on plugin update)
- Filters `script_loader_tag` and `style_loader_tag`

### Changed
- Core: added `$link_checker`, `$internal_linker`, `$csp_headers`, `$sri` properties
- Default settings: `enable_link_checker`, `enable_internal_linker`, `enable_csp_headers`, `enable_sri` (all off by default)

### Tests
- 437 unit tests, 987 assertions (up from 372/887)

---

## [2.0.0] — 2026-03-13

### Added

#### Redirect Manager (`class-redirect-manager.php`)
- 301, 302, 307, 410 redirect rules with exact, prefix, and regex matching
- Admin table with search, filter, sort, and pagination
- Hit counter and last-hit date per redirect rule
- 404 log with hit aggregation and auto-suggest redirect targets
- CSV import/export for bulk redirect management
- Slug change auto-redirect detection on post save
- Redirect loop detection and prevention
- Custom database tables (`search_intel_redirects`, `search_intel_404_log`)

#### IndexNow Integration (`class-indexnow.php`)
- IndexNow API ping on post publish/update
- Support for Bing, Yandex, Naver endpoints
- API key management in settings with auto-served verification file
- Ping log with success/failure status per provider
- Page-level hooks for `publish_post`, `publish_page`, `post_updated`

#### WooCommerce Support (`class-woocommerce.php`)
- Auto-detect WooCommerce product pages
- Product schema (name, description, image, SKU, brand, GTIN, MPN)
- Offer schema (price, currency, availability, condition, seller, sale dates)
- AggregateRating from WooCommerce product reviews
- Product breadcrumbs (Home > Shop > Category > Product) with ancestor support
- Product sitemap via `search_intel_sitemap_post_types` filter
- Product-specific metabox fields (brand, GTIN, MPN, condition)

#### Local Business Schema (`class-local-business.php`)
- LocalBusiness schema type with 14 subtypes (Restaurant, Store, etc.)
- PostalAddress schema (street, city, state, zip, country)
- GeoCoordinates (latitude, longitude)
- Service area support (cities served)
- Multiple locations support via settings array
- OpeningHoursSpecification per day of week
- Price range, telephone, email, image, description

#### Robots.txt Editor (`class-robots-txt.php`)
- Visual robots.txt editor with monospace textarea
- Syntax validation (directive format, User-agent presence, Sitemap URLs)
- Warnings for wildcards and unknown directives
- Save, validate, and reset to default actions
- Filters WordPress `robots_txt` output when enabled

#### Audit Log (`class-audit-log.php`)
- Settings change tracking via `update_option_search_intel_settings` hook
- Diff-based logging — only changed keys recorded
- User ID, display name, timestamp, and hashed IP per entry
- Configurable max entries (200) with automatic trimming
- Clear log action for administrators

#### Tests
- `tests/unit/RedirectManagerTest.php` — 22 tests
- `tests/unit/IndexNowTest.php` — 14 tests
- `tests/unit/WooCommerceTest.php` — 10 tests
- `tests/unit/LocalBusinessTest.php` — 17 tests
- `tests/unit/RobotsTxtTest.php` — 14 tests
- `tests/unit/AuditLogTest.php` — 15 tests
- Unit test count: 372 tests, 887 assertions (up from 277/719)

### Changed
- `Core` constructor now boots 6 new modules: Redirect_Manager, IndexNow, WooCommerce, Local_Business, Robots_Txt, Audit_Log
- Activation hook creates redirect and 404 log database tables via `dbDelta`
- Default settings expanded with redirect, IndexNow, local business, robots editor, and audit log keys
- Test bootstrap extended with 20+ new WP function stubs and enhanced `$wpdb` mock

### New Files
- `includes/class-redirect-manager.php` — Redirect Manager
- `includes/class-indexnow.php` — IndexNow Integration
- `includes/class-woocommerce.php` — WooCommerce Support
- `includes/class-local-business.php` — Local Business Schema
- `includes/class-robots-txt.php` — Robots.txt Editor
- `includes/class-audit-log.php` — Audit Log

---

## [1.5.0] — 2026-03-13

### Added

#### Privacy & GDPR Compliance (`class-privacy.php`)
- `wp_privacy_personal_data_exporters` — exports all 5 Search Intel user meta fields (credentials, expertise areas, years of experience, social profiles, consent status)
- `wp_privacy_personal_data_erasers` — erases all Search Intel user meta on data deletion request
- `wp_add_privacy_policy_content()` — suggested privacy policy text covering data collection, storage, search engine pinging, and export/deletion support
- Privacy class registered in `Core` singleton — hooks fire automatically on `admin_init` and privacy filters

#### E-E-A-T Consent
- Explicit consent checkbox on user profile E-E-A-T section ("I consent to Search Intel storing and displaying my E-E-A-T author data…")
- `search_intel_eeat_consent` user meta key — `'1'` when opted in, empty when not
- `GEO_Manager::render_author_eeat()` now checks consent before outputting author structured data — no E-E-A-T schema without opt-in
- Consent can be withdrawn at any time by unchecking the box

#### Privacy Audit
- Verified: no frontend cookies set by plugin
- Verified: no external API calls by default (search engine pinging now opt-in)
- Verified: no tracking scripts or analytics
- Verified: all data stored locally in WordPress database (`wp_options`, `wp_postmeta`, `wp_usermeta`)

#### Tests
- `tests/unit/PrivacyTest.php` — 21 tests covering exporter, eraser, privacy policy content, constructor hooks
- Unit test count: 277 tests, 719 assertions (up from 256/666)

### Changed
- `ping_search_engines` default changed from `true` to `false` — search engine pinging is now opt-in with clear disclosure in settings
- `GEO_Manager::render_author_eeat()` returns early if author has not given E-E-A-T consent
- `Admin::render_user_eeat_fields()` now renders consent checkbox before credential fields
- `Admin::save_user_eeat_fields()` now saves `search_intel_eeat_consent` meta

### New Files
- `includes/class-privacy.php` — Privacy & GDPR compliance class

---

## [1.0.0] — 2026-03-12

### Added

#### Core
- Plugin bootstrap with PSR-4-style autoloader
- Singleton `Core` orchestrator with module initialization
- Activation/deactivation hooks with rewrite rule flushing
- Full default settings system with `wp_parse_args` merging
- `search_intel_settings` option for centralized configuration
- Text domain loading for full i18n support

#### SEO Module
- Custom SEO titles with `pre_get_document_title` filter
- Configurable title separator (|, -, --, etc.)
- Auto-generated meta descriptions from excerpt or content
- Author meta tag on single posts
- Open Graph tags: type, title, description, url, image, locale, site_name
- Open Graph article properties: published_time, modified_time, author, section, tags
- Twitter Card tags: card type, site, title, description, image, creator
- Canonical URL generation with custom override per post
- Robots meta directives: noindex, nofollow per post
- Global noindex rules for search, 404, date/author archives
- Rich result directives: max-snippet, max-image-preview, max-video-preview
- Featured image and custom OG image support with dimensions

#### Schema Module
- Single JSON-LD `@graph` output for clean structured data
- Organization or Person knowledge graph entity
- Logo ImageObject with contentUrl and caption
- Social profile `sameAs` links from social profiles option
- WebSite schema with `potentialAction` SearchAction for sitelinks searchbox
- BreadcrumbList schema with category/page ancestor hierarchy
- Article schema with headline, description, url, dates, author, publisher
- Author Person schema with description (E-E-A-T)
- Featured image ImageObject with dimensions
- Word count, articleSection, keywords properties
- Per-post schema type override (Article, NewsArticle, BlogPosting, TechArticle, etc.)
- `search_intel_singular_schema` filter for custom modifications

#### AEO Module
- FAQPage schema from manual post meta entries
- Auto-detection of FAQ patterns: H2/H3 headings ending with `?` + following paragraph
- HowTo schema with name, description, totalTime, supplies, tools, steps
- HowToStep with name, text, url anchors, optional images
- SpeakableSpecification with configurable CSS selectors per post or global
- `[search_intel_faq]` shortcode with microdata markup
- `[search_intel_howto]` shortcode with nested step support
- AEO scoring engine (100 points across 6 checks)

#### GEO Module
- AI-specific meta tags: content-type, keywords/topics, expertise-level
- Auto-topic extraction from categories, tags, and content term frequency
- Content type auto-detection (article, how-to, faq, comparison, review, guide)
- Content freshness meta tag: very-fresh, fresh, recent, aging, evergreen
- Fact-check date meta tag
- AI crawl directives: per-post blocking of GPTBot, ChatGPT-User, Google-Extended, CCBot, ClaudeBot, anthropic-ai
- Site-wide AI permissions option (allow/restrict)
- `noai` and `noimageai` robots directives
- `llms.txt` endpoint with site summary, key pages, topic categories, attribution
- `llms-full.txt` endpoint with recent post summaries and dates
- Entity markup: wraps defined entities with Schema.org microdata in content
- Author E-E-A-T JSON-LD: hasCredential, knowsAbout, hasOccupation, sameAs, image
- GEO scoring engine (100 points across 7+ checks)

#### Content Analyzer
- AJAX-powered live analysis from post editor
- SEO score (100 points, 10 checks): keyword placement, density, title/description length, headings, images, internal links
- AEO score (100 points, 6 checks): direct answers, question headings, lists, tables, FAQ data, concise paragraphs
- GEO score (100 points, 7+ checks): AI summary, citations, definitions, author bio, content depth, entities, freshness
- Flesch-Kincaid readability scoring with grade level
- Focus keyword analysis: occurrences, density, presence in title/description/H2/first paragraph/URL slug
- Configurable target readability score

#### Sitemap
- XML sitemap index with automatic pagination
- Per-post-type sitemaps with configurable max URLs
- Taxonomy sitemaps for categories and tags
- Image sitemap support (featured image + content images, max 10)
- Automatic exclusion of noindex posts
- Change frequency calculation based on modification age
- Priority assignment by post type
- Automatic search engine pinging (Google, Bing) on publish
- WordPress core sitemap disabled to avoid conflicts

#### Admin Interface
- 6 admin settings pages: General, SEO, AEO, GEO, Sitemap, Social
- Tabbed post editor metabox: SEO, AEO, GEO, Advanced, Analysis
- Live SERP preview with title and description
- Character counters with warning states
- FAQ repeater with add/remove/JSON serialization
- Entity repeater with type selector and URL field
- Admin column showing optimization status (0-4 filled fields)
- Plugin action link to settings page
- User profile E-E-A-T fields: credentials, expertise areas, years experience, social profiles

#### Security
- All input sanitized: `sanitize_text_field()`, `wp_kses_post()`, `esc_url_raw()`, `absint()`
- All output escaped: `esc_html()`, `esc_attr()`, `esc_url()`, `esc_textarea()`
- Nonce verification on all forms and AJAX
- Capability checks on all write operations
- `ABSPATH` check on all PHP files
- No frontend JavaScript or CSS — zero public attack surface

#### Housekeeping
- `uninstall.php` for complete database cleanup (post meta, user meta, options)
- WordPress.org-compliant `readme.txt`
- GPL-2.0-or-later license

---

## [1.1.0] — 2026-03-12

### Added

#### Post Panel — New Fields
- Social share title override (separate from SEO title) for Open Graph and Twitter Cards
- Social share description override (separate from meta description)
- Social image override with WordPress media library picker
- Secondary keywords field (comma-separated) with content usage tracking
- Direct-answer field for featured snippet targeting ("What is this post answering?")
- Citation / source references repeater with source name, URL, date, and quote fields
- Per-post author expertise notes (`knowsAbout` in JSON-LD)
- Content intent label (informational, navigational, transactional, commercial investigation)
- Audience label (beginner, intermediate, advanced, professional, general)
- Local relevance toggle with location field (`contentLocation` in JSON-LD, `geo.placename` meta)

#### Title Template Engine
- Variable resolver: `%title%`, `%sitename%`, `%sep%`, `%category%`, `%tag%`, `%date%`, `%author%`, `%page%`, `%excerpt%`
- Per-context templates: single posts, pages, categories, tags, author archives, search, 404, homepage
- Title template settings UI on the SEO settings page
- `pre_get_document_title` integration at priority 25 (above SEO Manager's priority 20)

#### Analysis-on-Save
- Automatic content analysis on `wp_after_insert_post` hook
- Score storage in post meta (`_search_intel_seo_score`, `_search_intel_aeo_score`, `_search_intel_geo_score`, `_search_intel_readability_score`)
- Admin notice via transients when critical issues detected (any score below 30)

#### New Analysis Checks (11 items)
- H1 presence detection (exactly one H1 or post title as H1)
- Heading hierarchy validation (no skipped levels, e.g., H2→H4)
- Internal link opportunity suggestions (scaled to word count, minimum 2)
- External citation/link presence scoring
- Answer block opportunity detection (concise paragraphs, definitions, lists)
- Entity clarity scoring (3+ entities recommended)
- AI summary presence and substantiveness check (10+ words)
- Schema completeness check (schema type + FAQs + entities)
- Secondary keyword usage tracking in content body
- Social metadata completeness check (title + description + image = 3/3)
- Image alt text presence check with per-image detail

#### AEO Additions
- Direct-answer field rendered as `<meta name="direct-answer">` tag
- Direct-answer added as `abstract` property in Article JSON-LD schema
- Q&A style detection: bold question patterns (`<strong>Q?</strong>`) and `Q:/A:` prefix patterns
- Answer snippet recommendation score (concise blocks, lists, definitions, tables — 4 checks, 25 pts each)
- Auto-generate lead summary from first 2-3 sentences when no excerpt exists (`get_the_excerpt` filter)

#### GEO Additions
- Citations rendered in Article JSON-LD as `citation` array with `@type: CreativeWork` objects
- Content intent label output as `<meta name="content-intent">` and `creativeWorkStatus` in JSON-LD
- Audience label output as `<meta name="target-audience">` and `schema.org/Audience` in JSON-LD
- Author expertise notes output as `<meta name="author-expertise">` and `knowsAbout` on author entity
- Local relevance rendered as `contentLocation` with `@type: Place` in schema and `geo.placename` meta tag

#### Expanded Settings
- Default OG image URL setting (fallback when no featured image or social image is set)
- Meta description fallback behavior: excerpt → content trim → empty (3 modes)
- Meta description max length setting (50-320 characters, truncates with ellipsis)
- Global robots default for new posts (index or noindex)
- AI summary default target word count setting (20-200, shown as guidance)
- Default content intent selection (pre-filled for new posts)
- Default audience selection (pre-filled for new posts)
- Search engine ping toggle (opt-in, sends sitemap URL to Google and Bing on publish)

#### Sitemap Improvements
- Transient-based sitemap caching with 12-hour TTL for faster responses
- Automatic cache invalidation on post save, delete, and term create/edit/delete
- Configurable taxonomy sitemap toggles (category, post_tag, and any custom taxonomy)
- Custom post type sitemap support (all public CPTs selectable in settings)
- Search engine ping respects the new `ping_search_engines` setting toggle

### Changed
- `SEO_Manager::get_description()` now respects `description_fallback` and `description_max_length` settings
- `SEO_Manager::get_og_image()` now checks `default_og_image` setting before knowledge logo fallback
- `Sitemap::ping_search_engines()` only hooks when `ping_search_engines` setting is enabled
- Taxonomy sitemaps now driven by `sitemap_taxonomies` setting instead of hardcoded `['category', 'post_tag']`
- Sitemap `generate_sitemap()` taxonomy detection uses settings array instead of hardcoded list

### New Files
- `includes/class-title-engine.php` — Title Template Engine class

---

## [1.4.0] — 2026-03-12

### Added

#### Gutenberg Sidebar Panel (`src/sidebar/`)
- `PluginDocumentSettingPanel` with tabbed interface (SEO, AEO, GEO, Analysis)
- `@wordpress/data` store (`search-intel/editor`) for post meta state management
- **SEO tab**: SEO title, meta description, focus keyword, secondary keywords, canonical URL, noindex/nofollow toggles, social overrides (OG + Twitter), live SERP preview with character counts
- **AEO tab**: Direct answer field with character count, schema type override dropdown, FAQ/HowTo block guidance
- **GEO tab**: AI summary, key topics, content type, content intent, target audience, expertise level, fact-check date, local relevance toggle with location field
- **Analysis tab**: Score ring SVG visualizations (SEO, AEO, GEO, Readability), "Run Analysis" button via REST API, detailed check results by category
- Post meta registered in REST API for all public post types (`register_post_meta` with `show_in_rest`)
- PHP metabox retained as Classic Editor fallback

#### Custom Gutenberg Blocks (`src/blocks/`)
- `search-intel/faq` — visual FAQ editor with add/remove items, numbered entries, automatic FAQPage schema markup, microdata on frontend
- `search-intel/howto` — step-by-step editor with per-step image upload via `MediaUpload`, HowTo schema, configurable total time (ISO 8601)
- `search-intel/toc` — auto-generated Table of Contents from post headings, configurable max depth and list type, dynamic server-side rendering
- `search-intel/breadcrumbs` — theme-aware breadcrumb navigation with BreadcrumbList schema, configurable separator and home label, server-side rendering
- `search-intel/answer-box` — highlighted answer box optimized for featured snippets and AI extraction, question + answer fields with semantic data attributes
- `search-intel/citation` — inline source citation block with CreativeWork schema, configurable source type/name/URL/author/date, `<figure>` + `<figcaption>` output

#### Build System
- `@wordpress/scripts` build toolchain with custom `webpack.config.js`
- 7 webpack entry points: sidebar + 6 blocks
- `npm run build` / `npm run start` scripts
- Asset files (`.asset.php`) for dependency and version management
- ESLint config updated for ESM + JSX

#### PHP Integration (`class-blocks.php`)
- `Blocks` class registered in `Core` singleton
- `register_blocks()` — registers all 6 custom blocks with editor scripts/styles
- `register_meta()` — registers 28 post meta keys in REST API for all public post types
- `enqueue_sidebar()` — loads compiled sidebar JS/CSS in block editor
- `render_toc()` — server-side TOC rendering from post heading extraction
- `render_breadcrumbs()` — server-side breadcrumb rendering with BreadcrumbList schema

### Changed
- `class-core.php`: Added `$blocks` property and `Blocks` instantiation
- `package.json`: Added `@wordpress/*` dependencies and build scripts, bumped to 1.4.0
- `.eslintrc.json`: Updated to `sourceType: module` with JSX support
- `.distignore`: Added `src/`, `webpack.config.js` exclusions
- `psalm-baseline.xml`: Added entries for `class-blocks.php` constants and `$blocks` property

---

## [1.3.0] — 2026-03-12

### Added

#### REST API (`class-rest-api.php`)
- `GET /search-intel/v1/scores/{id}` — retrieve SEO, AEO, GEO, and readability scores
- `GET /search-intel/v1/geo/{id}` — full GEO metadata (AI summary, topics, entities, intent, audience)
- `GET /search-intel/v1/meta/{id}` — read SEO meta fields (title, description, keyword, canonical, robots)
- `PUT /search-intel/v1/meta/{id}` — update SEO meta fields with sanitization
- `POST /search-intel/v1/analyze/{id}` — trigger content analysis and save scores
- `POST /search-intel/v1/bulk/rescore` — batch rescore with offset pagination
- `GET /search-intel/v1/settings` — read all plugin settings (admin only)
- `PUT /search-intel/v1/settings` — update plugin settings with type validation (admin only)
- `POST /search-intel/v1/sitemap/invalidate` — clear sitemap transient caches (admin only)
- Permission callbacks: `can_read_post`, `can_edit_post`, `can_manage_options`
- `enable_rest_api` toggle in GEO settings page (default: enabled)
- REST API routes guarded by toggle — routes not registered when disabled

#### Bulk Tools (`class-bulk-tools.php`)
- "Search Intel > Bulk Tools" admin page under main menu
- Bulk rescore — re-run SEO/AEO/GEO/readability analysis on all published posts (batched AJAX, 20/request)
- Bulk regenerate metadata — auto-fill empty descriptions, AI summaries, and content types (non-destructive)
- Sitemap cache clear & warm — invalidate all sitemap transients and queue regeneration
- Export settings — download all plugin settings + social profiles as timestamped JSON
- Import settings — upload JSON with merge (keep current, overlay) or overwrite (reset to defaults, overlay) mode
- AJAX progress bar UI with real-time batch progress (done/total, percentage)
- Client-side JSON validation before import submission

#### WP-CLI (`class-cli.php`)
- `wp search-intel analyze <post_id> [--save]` — analyze a single post with score table output
- `wp search-intel rescore --all [--post_type=] [--batch_size=]` — bulk rescore with progress bar
- `wp search-intel regenerate --all [--post_type=]` — bulk regenerate empty metadata fields
- `wp search-intel sitemap cache|clear` — manage sitemap transient caches
- `wp search-intel export` — output settings as JSON (pipe to file)
- `wp search-intel import <file> [--mode=merge|overwrite]` — import settings from JSON file
- `wp search-intel scores [--format=table|csv|json|yaml] [--orderby=seo|aeo|geo|readability] [--limit=]` — display score table

#### Testing
- 32 new unit tests: `RestApiTest` (28 tests) and `BulkToolsTest` (12 tests)
- Total suite: **225 tests, 441 assertions** — all passing
- `WP_REST_Request` and `WP_REST_Response` class stubs for isolated unit testing
- `$wpdb` stub for sitemap cache invalidation tests
- Enhanced `get_posts` stub: respects `fields`, `post_type`, `post_status`, `offset`, `limit`

### Changed
- `class-core.php`: Added `$rest_api` and `$bulk_tools` properties to singleton
- `class-admin.php`: Added Bulk Tools submenu page and asset enqueueing
- `admin/css/admin-style.css`: Added progress bar and bulk tools result styles
- `search-intel.php`: WP-CLI command registration (conditional on `WP_CLI`)

---

## [1.2.1] — 2026-03-12

### Fixed
- **CI: Psalm config** — Remove `node_modules` from `psalm.xml` `<ignoreFiles>` (directory doesn't exist in CI, causing parse error)
- **CI: PHP 8.0 dropped from test matrix** — `amphp/parallel` (Psalm transitive dependency) uses PHP 8.1+ syntax in autoload files; PHP 8.0 is EOL since Nov 2023
- **CI: Composer platform requirement** — Add `--ignore-platform-req=php` to all `composer install` commands (Psalm 6.5 lock file requires PHP ≥8.3.16)

### Added
- `phpstan.neon` configuration with WordPress stubs bootstrap
- `.eslintrc.json` configuration with browser/jQuery/WP globals

---

## [1.2.0] — 2026-03-12

### Added

#### Testing Infrastructure
- **193 PHPUnit unit tests** (348 assertions) covering all 9 plugin classes
- **125 PHPUnit integration tests** (202 assertions) covering meta output, schema rendering, settings, and cross-module pipelines
- **40 Playwright E2E tests** against live WordPress via `wp-env` (Docker)
  - Metabox: tab navigation, panel switching, SERP preview (5 tests)
  - Character counters: title and description with overflow detection (3 tests)
  - Focus keyword field and nonce verification (2 tests)
  - FAQ repeater: add, remove, multiple rows, question/answer fields (5 tests)
  - Entity repeater: add, remove, visibility (3 tests)
  - Citation repeater: add, remove, visibility (3 tests)
  - Analysis: Run Analysis button, AJAX score rings, multi-category display (5 tests)
  - Settings: all 6 pages load, separator save, OG toggle, sitemap max URLs (10 tests)
  - Post list: Search Intel column header, cell rendering, score data (3 tests)
  - Auth setup: automated WP login with stored session state (1 test)

#### Static Analysis & Linting
- Psalm level 4 with WordPress stubs and baseline (76 baselined WP hook pattern issues)
- Stylelint with `stylelint-config-standard` (0 errors after auto-fix)
- `psalm.xml` and `psalm-baseline.xml` configuration files
- `.stylelintrc.json` configuration file

#### CI/CD Pipeline
- Psalm job (PHP 8.3)
- Stylelint job
- Performance regression check job (`--filter Performance`)
- Playwright E2E job with `wp-env` startup, Classic Editor install, and auth configuration
- PHPUnit matrix updated to PHP 8.1, 8.2, 8.3

#### Performance Benchmarks
- Sitemap cold benchmark script (`tests/benchmark-sitemap.php`): **65.62ms** with 1002 posts (target < 500ms)
- Block theme compatibility test script (`tests/benchmark-blocktheme.php`)

#### Compatibility Testing
- Live verified: Classic Editor metabox with all 5 tabs
- Live verified: Front-end meta output (description, canonical, robots, OG, Twitter, AI meta, JSON-LD)
- Live verified: Settings pages save and persist across all 6 pages
- Live verified: Post list Search Intel column with score display
- Live verified: AJAX content analysis with SEO/AEO/GEO/Readability score rings
- Live verified: Block theme (Twenty Twenty-Four v1.4 FSE) — `wp_head` renders all SI meta correctly

#### Development Files
- `.wp-env.json` for local WordPress development environment
- `.distignore` for clean plugin distribution builds (excludes tests, configs, CI files)
- `CONTRIBUTING.md` with Git Flow branching strategy and Conventional Commits guide
- Playwright config with auth setup project and stored session state

### Fixed
- **`speakable_selectors` sanitize crash** — `explode()` called on array input when hidden fields passed array values (class-admin.php:187)
- Admin CSS: `color-function-notation` and `media-feature-range-notation` updated to modern syntax

### Changed
- `composer.json`: Added `vimeo/psalm` and `php-stubs/wordpress-stubs` as dev dependencies
- `package.json`: Added `@wordpress/env`, Playwright, Stylelint dev dependencies

---

## [Unreleased]

### Planned
- See [ROADMAP.md](ROADMAP.md) for v1.4 features (FSE & Block Editor Native, Gutenberg sidebar, custom blocks)

---

_For the full development roadmap, see [ROADMAP.md](ROADMAP.md)._
