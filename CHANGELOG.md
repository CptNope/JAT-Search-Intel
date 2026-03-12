# Changelog

All notable changes to Search Intel will be documented in this file.

This project adheres to [Semantic Versioning](https://semver.org/) and follows the [Keep a Changelog](https://keepachangelog.com/) format.

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

## [Unreleased]

### Planned
- See [ROADMAP.md](ROADMAP.md) for v1.2+ features (security audit, unit tests, REST API, dashboard)

---

_For the full development roadmap, see [ROADMAP.md](ROADMAP.md)._
