=== Search Intel ===
Contributors: searchintel
Tags: seo, aeo, geo, schema, structured-data, ai-optimization, llms, featured-snippets, voice-search
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A comprehensive SEO, AEO (Answer Engine Optimization), and GEO (Generative Engine Optimization) plugin for WordPress.

== Description ==

Search Intel is the first WordPress plugin designed from the ground up for the three pillars of modern search visibility:

**SEO — Search Engine Optimization**
Traditional search optimization with a modern implementation.

* Custom meta titles and descriptions with SERP preview
* Open Graph tags for Facebook, LinkedIn, and social sharing
* Twitter Card tags with per-post configuration
* Canonical URL management
* Robots meta directives (noindex, nofollow) per post
* XML Sitemaps with image support and automatic pagination
* Focus keyword analysis with density and placement checks
* Readability scoring (Flesch-Kincaid)
* BreadcrumbList, WebSite, and Organization/Person JSON-LD schema
* Article schema with full author, date, and category support

**AEO — Answer Engine Optimization**
Optimize your content for featured snippets, voice assistants, and People Also Ask boxes.

* FAQPage schema — manual entry or auto-detection from question-style headings
* HowTo schema with step-by-step structured data
* SpeakableSpecification for voice assistant results (Google Assistant, Alexa, Siri)
* Featured snippet optimization scoring
* Content structure analysis (lists, tables, concise paragraphs)
* Shortcodes for FAQ and HowTo blocks with built-in schema

**GEO — Generative Engine Optimization**
The future of search — optimize for AI-powered answer engines like ChatGPT, Perplexity, and Google AI Overviews.

* AI Summary field — provide a concise, factual summary for LLM consumption
* llms.txt endpoint — machine-readable site summary following the llms.txt specification
* Entity markup — define people, organizations, places, and products with Schema.org microdata
* E-E-A-T author signals — credentials, expertise areas, years of experience
* Content freshness meta tags — signal recency to AI crawlers
* Content type detection — article, how-to, FAQ, comparison, review, guide
* AI crawl directives — control which AI bots can index your content
* Fact-check date tracking
* Key topics extraction and semantic keyword signals

**Unified Content Analyzer**
Real-time scoring dashboard in the post editor with separate SEO, AEO, and GEO scores plus actionable recommendations.

**REST API (v1.3+)**
Full REST API under the `search-intel/v1` namespace with toggleable endpoints:

* `GET /scores/{id}` — SEO, AEO, GEO, readability scores
* `GET /geo/{id}` — full GEO metadata
* `GET/PUT /meta/{id}` — read/write SEO meta fields
* `POST /analyze/{id}` — trigger content analysis
* `POST /bulk/rescore` — batch rescore with pagination
* `GET/PUT /settings` — read/write plugin settings (admin only)
* `POST /sitemap/invalidate` — clear sitemap caches (admin only)

**Bulk Tools (v1.3+)**
Admin page with batch operations and live progress bars:

* Bulk rescore all published content
* Bulk regenerate empty metadata fields (non-destructive)
* Sitemap cache clear and warm
* Export/import settings as JSON (merge or overwrite mode)

**WP-CLI Commands (v1.3+)**

* `wp search-intel analyze <post_id>` — analyze a single post
* `wp search-intel rescore --all` — bulk rescore with progress bar
* `wp search-intel regenerate --all` — bulk regenerate metadata
* `wp search-intel sitemap cache|clear` — manage sitemap caches
* `wp search-intel export / import` — settings export/import
* `wp search-intel scores` — display score table (table, CSV, JSON, YAML)

**Gutenberg Sidebar Panel (v1.4+)**
Native Block Editor integration with a tabbed sidebar panel:

* SEO tab: title, description, keyword, canonical, robots, social overrides, live SERP preview
* AEO tab: direct answer, schema type override
* GEO tab: AI summary, key topics, content type, intent, audience, expertise, fact-check date
* Analysis tab: score ring visualizations + one-click analysis via REST API

**Custom Gutenberg Blocks (v1.4+)**

* FAQ block — visual FAQ editor with automatic FAQPage schema
* How To block — step editor with per-step images and HowTo schema
* Table of Contents — auto-generated from post headings
* Breadcrumbs — theme-aware navigation with BreadcrumbList schema
* Answer Box — featured snippet-optimized answer formatting
* Citation — inline source citation with CreativeWork schema

== Installation ==

1. Upload the `search-intel` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Search Intel > General** to configure your site settings
4. Configure SEO, AEO, and GEO settings in their respective tabs
5. Add social profiles under **Search Intel > Social**
6. Edit any post or page to access the Search Intel metabox

== Frequently Asked Questions ==

= Does this replace Yoast SEO or Rank Math? =

Yes. Search Intel provides equivalent SEO functionality plus AEO and GEO features that other plugins don't offer. Deactivate other SEO plugins before activating Search Intel to avoid conflicts.

= What is AEO? =

Answer Engine Optimization focuses on getting your content into featured snippets, voice assistant results, and People Also Ask boxes. It emphasizes structured data (FAQ, HowTo), concise answers, and content formatting.

= What is GEO? =

Generative Engine Optimization is the practice of optimizing content for AI-powered search engines and answer systems like ChatGPT, Perplexity, Google AI Overviews, and Bing Copilot. It focuses on clear definitions, source citations, entity clarity, and author authority.

= What is llms.txt? =

llms.txt is a proposed standard (similar to robots.txt) that provides a machine-readable summary of your website for Large Language Models. Search Intel auto-generates this file from your site content.

= Will this slow down my site? =

No. Search Intel outputs lightweight meta tags and JSON-LD schema directly in the page head. There are no frontend JavaScript files or CSS loaded on the public site.

== Changelog ==

= 2.0.0 =
* Redirect Manager: 301/302/307/410 redirects with exact, prefix, regex matching
* Redirect Manager: Admin table, hit counter, 404 log, CSV import/export, slug change detection
* IndexNow: Ping Bing, Yandex, Naver on publish/update with API key management and ping log
* WooCommerce: Product schema (name, SKU, brand, GTIN, MPN), Offer, AggregateRating
* WooCommerce: Product breadcrumbs, product sitemap, product metabox
* Local Business: LocalBusiness schema with 14 subtypes, address, geo, hours, service areas
* Robots.txt: Visual editor with syntax validation and WordPress robots_txt filter
* Audit Log: Settings change tracking with diff, user attribution, and IP hashing
* 372 unit tests, 887 assertions

= 1.5.0 =
* GDPR: Personal data exporter and eraser for author E-E-A-T meta
* Privacy policy suggested text via wp_add_privacy_policy_content()
* Explicit E-E-A-T consent checkbox on user profile
* Author structured data gated behind consent — no output without opt-in
* Search engine pinging changed to opt-in (default off)
* Privacy audit: no cookies, no external calls by default, no tracking, all data local
* 277 unit tests, 719 assertions

= 1.4.0 =
* Gutenberg sidebar panel with SEO, AEO, GEO, and Analysis tabs
* @wordpress/data store for post meta state management
* 6 custom Gutenberg blocks: FAQ, HowTo, TOC, Breadcrumbs, Answer Box, Citation
* Server-side rendering for TOC and Breadcrumbs blocks
* BreadcrumbList and FAQPage schema from blocks
* 28 post meta keys registered in REST API for all public post types
* @wordpress/scripts build toolchain with 7 webpack entry points
* PHP metabox retained as Classic Editor fallback

= 1.3.0 =
* REST API: 9 endpoints under `search-intel/v1` namespace with permission callbacks
* REST API: Toggleable via enable_rest_api setting in GEO settings page
* Bulk Tools: Admin page with batch rescore, regenerate, sitemap cache management
* Bulk Tools: Settings export/import as JSON with merge and overwrite modes
* Bulk Tools: AJAX progress bars for all batch operations
* WP-CLI: 7 commands — analyze, rescore, regenerate, sitemap, export, import, scores
* 225 unit tests, 441 assertions — all passing

= 1.2.1 =
* Fix: Psalm CI configuration and PHP 8.0 dropped from test matrix
* Fix: Composer platform requirement for CI

= 1.2.0 =
* 193 PHPUnit unit tests, 125 integration tests, 40 Playwright E2E tests
* Psalm level 4, PHPStan level 6, Stylelint, ESLint static analysis
* CI pipeline: PHPUnit matrix, static analysis, performance benchmarks, E2E
* Fix: speakable_selectors sanitize crash

= 1.0.0 =
* Initial release
* Full SEO suite: meta tags, Open Graph, Twitter Cards, canonical URLs, robots
* Schema: Article, Organization, Person, WebSite, BreadcrumbList, Sitelinks SearchBox
* AEO: FAQ schema, HowTo schema, Speakable, auto-detection, shortcodes
* GEO: AI meta, llms.txt, entity markup, E-E-A-T signals, freshness
* Content Analyzer with SEO/AEO/GEO scoring
* XML Sitemap with image support
* Social profiles management
* User profile E-E-A-T fields

== Upgrade Notice ==

= 2.0.0 =
Major: Redirect Manager (301/302/307/410, 404 log, CSV), IndexNow (Bing/Yandex/Naver), WooCommerce product schema, Local Business schema, robots.txt editor, audit log.

= 1.5.0 =
New: GDPR compliance (data exporter/eraser, privacy policy text), E-E-A-T consent checkbox, author schema gated behind consent, search engine pinging opt-in.

= 1.4.0 =
New: Gutenberg sidebar panel (SEO/AEO/GEO/Analysis tabs), 6 custom blocks (FAQ, HowTo, TOC, Breadcrumbs, Answer Box, Citation), @wordpress/data store, server-side rendering.

= 1.3.0 =
New: REST API (9 endpoints), Bulk Tools admin page, WP-CLI commands (7 subcommands), settings export/import.

= 1.0.0 =
Initial release of Search Intel.
