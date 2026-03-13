# Search Intel

**The first WordPress plugin built for all three pillars of modern search visibility: SEO, AEO, and GEO.**

[![CI](https://github.com/CptNope/Search-Intel/actions/workflows/ci.yml/badge.svg)](https://github.com/CptNope/Search-Intel/actions/workflows/ci.yml)
[![License: GPL v2](https://img.shields.io/badge/License-GPLv2-blue.svg)](LICENSE.md)
[![WordPress: 6.0+](https://img.shields.io/badge/WordPress-6.0%2B-21759b.svg)](https://wordpress.org/)
[![PHP: 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)](https://php.net/)
[![Release: v1.4.0](https://img.shields.io/badge/Release-v1.4.0-brightgreen)](https://github.com/CptNope/Search-Intel/releases/tag/v1.4.0)

**[Roadmap](ROADMAP.md)** · **[Changelog](CHANGELOG.md)** · **[Contributing](CONTRIBUTING.md)** · **[License](LICENSE.md)**

---

## Table of Contents

- [Overview](#overview)
- [Architecture](#architecture)
- [Module Breakdown](#module-breakdown)
- [Data Flow](#data-flow)
- [Admin Interface](#admin-interface)
- [Schema Output Pipeline](#schema-output-pipeline)
- [Scoring System](#scoring-system)
- [Installation](#installation)
- [Configuration](#configuration)
- [REST API](#rest-api)
- [WP-CLI Commands](#wp-cli-commands)
- [Bulk Tools](#bulk-tools)
- [Gutenberg Sidebar](#gutenberg-sidebar)
- [Custom Blocks](#custom-blocks)
- [Shortcodes](#shortcodes)
- [Hooks and Filters](#hooks-and-filters)
- [File Structure](#file-structure)
- [Compatibility](#compatibility)
- [Testing & CI](#testing--ci)
- [Security](#security)
- [Contributing](#contributing)
- [License](#license)

---

## Overview

Search Intel optimizes WordPress content across three dimensions:

| Pillar | Target | What It Optimizes For |
|--------|--------|----------------------|
| **SEO** | Google, Bing, traditional crawlers | Meta tags, Open Graph, Twitter Cards, sitemaps, schema |
| **AEO** | Featured snippets, voice assistants, PAA | FAQ/HowTo schema, Speakable, concise answer formatting |
| **GEO** | ChatGPT, Perplexity, Google AI Overviews | AI summaries, llms.txt, entity markup, E-E-A-T, freshness |

```mermaid
graph LR
    subgraph "Search Intel Plugin"
        SEO["SEO Module"]
        AEO["AEO Module"]
        GEO["GEO Module"]
    end

    subgraph "Traditional Search"
        G["Google"]
        B["Bing"]
        Y["Yahoo"]
    end

    subgraph "Answer Engines"
        FS["Featured Snippets"]
        VA["Voice Assistants"]
        PAA["People Also Ask"]
    end

    subgraph "Generative AI"
        GPT["ChatGPT"]
        PER["Perplexity"]
        AIO["AI Overviews"]
        COP["Copilot"]
    end

    SEO --> G
    SEO --> B
    SEO --> Y
    AEO --> FS
    AEO --> VA
    AEO --> PAA
    GEO --> GPT
    GEO --> PER
    GEO --> AIO
    GEO --> COP
```

---

## Architecture

### High-Level Module Architecture

```mermaid
graph TD
    BOOT["search-intel.php<br/><i>Bootstrap & Autoloader</i>"]
    CORE["Core<br/><i>Singleton Orchestrator</i>"]
    
    BOOT --> CORE

    CORE --> TITLE["Title Engine"]
    CORE --> SEO["SEO Manager"]
    CORE --> SCHEMA["Schema Manager"]
    CORE --> AEO["AEO Manager"]
    CORE --> GEO["GEO Manager"]
    CORE --> ANALYZER["Content Analyzer"]
    CORE --> SITEMAP["Sitemap"]
    CORE --> ADMIN["Admin<br/><i>only in wp-admin</i>"]

    TITLE --> TVAR["Variable Resolver"]
    TITLE --> TCTX["Per-Context Templates"]

    SEO --> META["Meta Tags"]
    SEO --> OG["Open Graph"]
    SEO --> TW["Twitter Cards"]
    SEO --> CAN["Canonical URLs"]
    SEO --> ROB["Robots Meta"]

    SCHEMA --> JSONLD["JSON-LD @graph"]
    SCHEMA --> BREAD["BreadcrumbList"]
    SCHEMA --> SITE["WebSite + SearchAction"]
    SCHEMA --> KG["Knowledge Graph"]

    AEO --> FAQ["FAQPage Schema"]
    AEO --> HOWTO["HowTo Schema"]
    AEO --> SPEAK["Speakable"]
    AEO --> AUTODETECT["Auto-detect FAQs"]
    AEO --> DIRECTANS["Direct Answer Meta"]
    AEO --> QAPAT["Q&A Detection"]
    AEO --> SNIPPET["Snippet Scoring"]

    GEO --> AIMETA["AI Meta Tags"]
    GEO --> LLMS["llms.txt Endpoint"]
    GEO --> ENTITY["Entity Markup"]
    GEO --> EEAT["E-E-A-T Schema"]
    GEO --> FRESH["Freshness Signals"]
    GEO --> AIBOT["AI Crawl Directives"]
    GEO --> INTENT["Intent & Audience Meta"]
    GEO --> LOCAL["Local Relevance"]

    ADMIN --> SETTINGS["Settings Pages x6"]
    ADMIN --> METABOX["Post Metabox"]
    ADMIN --> COLUMNS["Admin Columns"]
    ADMIN --> USERFIELDS["User E-E-A-T Fields"]

    style CORE fill:#2563eb,color:#fff
    style SEO fill:#16a34a,color:#fff
    style AEO fill:#d97706,color:#fff
    style GEO fill:#9333ea,color:#fff
    style TITLE fill:#0d9488,color:#fff
    style ANALYZER fill:#0891b2,color:#fff
    style ADMIN fill:#dc2626,color:#fff
```

### Class Dependency Map

```mermaid
classDiagram
    class Core {
        -instance : Core
        +title_engine : Title_Engine
        +seo : SEO_Manager
        +schema : Schema_Manager
        +aeo : AEO_Manager
        +geo : GEO_Manager
        +analyzer : Content_Analyzer
        +sitemap : Sitemap
        +admin : Admin
        +get_instance() Core
        +get_settings() array
        +get_default_settings() array
    }

    class Title_Engine {
        -settings : array
        +resolve_title() string
        +resolve_variables(template, context) string
        +get_default_templates() array
    }

    class SEO_Manager {
        -settings : array
        +get_title(post_id) string
        +get_description(post_id) string
        +render_meta_tags() void
        +render_canonical() void
        +render_robots_meta() void
        +render_open_graph() void
        +render_twitter_card() void
    }

    class Schema_Manager {
        -settings : array
        +render_schema() void
        +get_singular_schema(post_id) array
        -get_knowledge_graph() array
        -get_website_schema() array
        -get_breadcrumbs_schema() array
    }

    class AEO_Manager {
        -settings : array
        +get_faq_schema(post_id) array
        +get_howto_schema(post_id) array
        +render_speakable() void
        +render_direct_answer_meta() void
        +detect_qa_patterns(content) array
        +get_snippet_score(content) array
        +auto_lead_summary(excerpt, post) string
        +get_aeo_score(post_id) array
        -auto_detect_faqs(post_id) array
    }

    class GEO_Manager {
        -settings : array
        +render_ai_meta() void
        +serve_llms_txt() void
        +enhance_content_entities(content) string
        +render_author_eeat() void
        +get_geo_score(post_id) array
        -auto_extract_topics(post_id) array
    }

    class Content_Analyzer {
        -settings : array
        +analyze(post_id, content, title, keyword, desc) array
        +get_readability_score(text) array
        +ajax_analyze() void
        +analyze_on_save(post_id, post) void
        -get_content_checks(post_id, content, text, word_count) array
        -count_syllables(text) int
    }

    class Sitemap {
        -settings : array
        +register_endpoints() void
        +serve_sitemap() void
        +ping_search_engines(post_id) void
        +invalidate_cache() void
        -generate_sitemap_index() string
        -generate_sitemap(type, page) string
    }

    class Admin {
        -settings : array
        -core : Core
        +register_menu() void
        +register_metaboxes() void
        +save_metabox_data(post_id) void
        +render_user_eeat_fields(user) void
    }

    Core --> Title_Engine
    Core --> SEO_Manager
    Core --> Schema_Manager
    Core --> AEO_Manager
    Core --> GEO_Manager
    Core --> Content_Analyzer
    Core --> Sitemap
    Core --> Admin
    Admin --> Core
```

---

## Data Flow

### Frontend Output Pipeline

```mermaid
flowchart TD
    WP["WordPress wp_head Action"] --> SEO_OUT
    WP --> SCHEMA_OUT
    WP --> AEO_OUT
    WP --> GEO_OUT

    subgraph SEO_OUT["SEO Manager Output (priority 1-5)"]
        M1["meta description"]
        M2["meta author"]
        M3["link canonical"]
        M4["meta robots"]
        M5["meta og:*"]
        M6["meta twitter:*"]
    end

    subgraph SCHEMA_OUT["Schema Manager Output (priority 10)"]
        S1["script type=application/ld+json"]
        S1 --> S2["@graph: Organization"]
        S1 --> S3["@graph: WebSite"]
        S1 --> S4["@graph: BreadcrumbList"]
        S1 --> S5["@graph: Article/WebPage"]
    end

    subgraph AEO_OUT["AEO Manager Output (priority 11-13)"]
        A1["FAQPage JSON-LD"]
        A2["HowTo JSON-LD"]
        A3["Speakable JSON-LD"]
        A4["Direct-answer meta"]
    end

    subgraph GEO_OUT["GEO Manager Output (priority 3-13)"]
        G1["AI crawl directives"]
        G2["AI meta tags"]
        G3["Freshness meta"]
        G4["Author E-E-A-T JSON-LD"]
        G5["Intent & audience meta"]
        G6["Local relevance meta"]
    end

    style SEO_OUT fill:#d1fae5,stroke:#16a34a
    style SCHEMA_OUT fill:#dbeafe,stroke:#2563eb
    style AEO_OUT fill:#fef3c7,stroke:#d97706
    style GEO_OUT fill:#ede9fe,stroke:#9333ea
```

### Post Editor Metabox Data Flow

```mermaid
flowchart LR
    EDITOR["Post Editor"] --> METABOX["Search Intel Metabox"]

    METABOX --> TAB_SEO["SEO Tab"]
    METABOX --> TAB_AEO["AEO Tab"]
    METABOX --> TAB_GEO["GEO Tab"]
    METABOX --> TAB_ADV["Advanced Tab"]
    METABOX --> TAB_ANALYSIS["Analysis Tab"]

    TAB_SEO --> |"save_post"| PM_SEO["Post Meta<br/>_search_intel_title<br/>_search_intel_description<br/>_search_intel_focus_keyword<br/>_search_intel_canonical<br/>_search_intel_noindex<br/>_search_intel_nofollow<br/>_search_intel_social_title<br/>_search_intel_social_description<br/>_search_intel_social_image<br/>_search_intel_secondary_keywords"]

    TAB_AEO --> |"save_post"| PM_AEO["Post Meta<br/>_search_intel_faqs<br/>_search_intel_schema_type<br/>_search_intel_direct_answer<br/>_search_intel_citations"]

    TAB_GEO --> |"save_post"| PM_GEO["Post Meta<br/>_search_intel_ai_summary<br/>_search_intel_topics<br/>_search_intel_entities<br/>_search_intel_expertise_level<br/>_search_intel_content_type<br/>_search_intel_fact_checked<br/>_search_intel_content_intent<br/>_search_intel_audience<br/>_search_intel_author_expertise_notes<br/>_search_intel_local_relevance<br/>_search_intel_local_location"]

    TAB_ANALYSIS --> |"AJAX"| ANALYZER["Content Analyzer"]
    ANALYZER --> |"JSON response"| SCORES["SEO: 0-100<br/>AEO: 0-100<br/>GEO: 0-100<br/>Readability: 0-100"]
```

### Sitemap Request Flow

```mermaid
sequenceDiagram
    participant Bot as Search Engine Bot
    participant WP as WordPress
    participant SM as Sitemap Module

    Bot->>WP: GET /sitemap.xml
    WP->>SM: query_var = search_intel_sitemap
    SM->>SM: Check transient cache
    alt Cache hit
        SM-->>Bot: Cached XML Sitemap Index
    else Cache miss
        SM->>SM: generate_sitemap_index()
        SM->>SM: Store in transient (12h TTL)
        SM-->>Bot: XML Sitemap Index
    end

    Bot->>WP: GET /sitemap-post1.xml
    WP->>SM: type=post, page=1
    SM->>SM: Check transient cache
    SM->>WP: get_posts(published, not noindex)
    WP-->>SM: Post data + images
    SM->>SM: Build URL entries + image:image
    SM-->>Bot: XML Sitemap with images

    Bot->>WP: GET /sitemap-category.xml
    WP->>SM: type=category
    SM->>WP: get_terms(category, hide_empty)
    WP-->>SM: Term data
    SM-->>Bot: XML Taxonomy Sitemap
```

### llms.txt Request Flow

```mermaid
sequenceDiagram
    participant AI as AI Crawler
    participant WP as WordPress
    participant GEO as GEO Manager

    AI->>WP: GET /llms.txt
    WP->>GEO: query_var = search_intel_llms_txt
    GEO->>GEO: Build site summary
    GEO->>WP: get_pages(), get_categories()
    WP-->>GEO: Pages + categories
    GEO-->>AI: text/plain llms.txt

    AI->>WP: GET /llms-full.txt
    WP->>GEO: query_var = search_intel_llms_full_txt
    GEO->>WP: get_posts(50 recent)
    WP-->>GEO: Posts with content
    GEO-->>AI: Full llms.txt with article summaries
```

---

## Admin Interface

### Settings Page Structure

```mermaid
graph TD
    MENU["Search Intel Menu<br/><i>dashicons-chart-area</i>"]

    MENU --> GEN["General Settings<br/><i>Site identity, post types, module toggles</i>"]
    MENU --> SEO_S["SEO Settings<br/><i>OG, Twitter, canonical, robots</i>"]
    MENU --> AEO_S["AEO Settings<br/><i>FAQ, HowTo, Speakable selectors</i>"]
    MENU --> GEO_S["GEO Settings<br/><i>AI meta, llms.txt, entity, E-E-A-T</i>"]
    MENU --> SIT_S["Sitemap Settings<br/><i>Post types, max URLs</i>"]
    MENU --> SOC_S["Social Settings<br/><i>8 platform profile URLs</i>"]

    GEN --> OPT_MAIN["wp_options:<br/>search_intel_settings"]
    SOC_S --> OPT_SOC["wp_options:<br/>search_intel_social_profiles"]

    style MENU fill:#2563eb,color:#fff
```

---

## Schema Output Pipeline

### JSON-LD Graph Assembly

```mermaid
flowchart TD
    START["wp_head fires"] --> CHECK{"Schema<br/>enabled?"}
    CHECK -->|No| SKIP["No output"]
    CHECK -->|Yes| COLLECT["Collect graph nodes"]

    COLLECT --> KG["Knowledge Graph<br/>Organization or Person"]
    COLLECT --> WS{"Is front<br/>page?"}
    WS -->|Yes| WEBSITE["WebSite + SearchAction"]
    WS -->|No| SKIP_WS[" "]

    COLLECT --> BC{"Not front<br/>page?"}
    BC -->|Yes| BREAD["BreadcrumbList<br/>Home > Category > Post"]
    BC -->|No| SKIP_BC[" "]

    COLLECT --> SG{"Is<br/>singular?"}
    SG -->|Yes| ARTICLE["Article / WebPage<br/>+ author, publisher, image"]
    SG -->|No| SKIP_SG[" "]

    KG --> MERGE["Merge into @graph array"]
    WEBSITE --> MERGE
    BREAD --> MERGE
    ARTICLE --> MERGE

    MERGE --> OUTPUT["Output single script tag<br/>@context + @graph"]

    style OUTPUT fill:#2563eb,color:#fff
```

---

## Scoring System

### SEO Score Breakdown (100 points)

```mermaid
pie title SEO Score Distribution
    "Keyword in title" : 15
    "Keyword in description" : 10
    "Description length" : 10
    "Title length" : 10
    "Content length" : 10
    "Keyword in first para" : 10
    "Keyword density" : 10
    "Subheadings" : 10
    "Image alt text" : 8
    "Internal links" : 7
```

### AEO Score Breakdown (100 points)

```mermaid
pie title AEO Score Distribution
    "Direct first-paragraph answer" : 20
    "Question-style headings" : 20
    "Structured lists" : 15
    "Data tables" : 15
    "FAQ schema data" : 15
    "Concise paragraphs" : 15
```

### GEO Score Breakdown (100 points)

```mermaid
pie title GEO Score Distribution
    "AI summary provided" : 15
    "Source citations" : 15
    "Clear definitions" : 15
    "Content depth 800w+" : 15
    "Entities defined" : 15
    "Content freshness" : 15
    "Author bio + credentials" : 10
```

### Score Rating Thresholds

| Score Range | Rating | Color | Meaning |
|-------------|--------|-------|---------|
| 70 - 100 | Good | Green | Well optimized |
| 40 - 69 | Needs Work | Amber | Key improvements needed |
| 0 - 39 | Poor | Red | Significant optimization required |

---

## Installation

### Requirements

| Requirement | Minimum | Recommended |
|-------------|---------|-------------|
| WordPress | 6.0 | 6.5+ |
| PHP | 7.4 | 8.1+ |
| MySQL | 5.7 | 8.0+ |

### Steps

1. Download or clone this repository
2. Copy the `search-intel` folder to `wp-content/plugins/`
3. Activate via **Plugins > Installed Plugins**
4. Navigate to **Search Intel > General** to configure
5. Flush permalinks: **Settings > Permalinks > Save** (required for sitemap and llms.txt)

### Important

> **Deactivate other SEO plugins** (Yoast, Rank Math, All in One SEO, etc.) before activating Search Intel to avoid duplicate meta tags and schema conflicts.

---

## Configuration

### Quick Start

1. **General** — Set your site name, entity type (Organization/Person), and logo
2. **SEO** — Enable Open Graph, Twitter Cards, and robots settings
3. **AEO** — Enable FAQ/HowTo schema and Speakable
4. **GEO** — Enable AI meta, llms.txt, and entity markup
5. **Social** — Add your social profile URLs
6. **Sitemap** — Choose which post types to include

### Per-Post Settings

Every post and page gets a **Search Intel** metabox with five tabs:

| Tab | Fields |
|-----|--------|
| **SEO** | Title, description, focus keyword, canonical URL, noindex/nofollow, SERP preview, social title/description/image, secondary keywords |
| **AEO** | FAQ items (repeater), schema type override, direct-answer field, citation/source references repeater |
| **GEO** | AI summary, topics, expertise level, content type, fact-check date, entities, content intent, audience, author expertise notes, local relevance toggle + location |
| **Advanced** | AI crawler blocking |
| **Analysis** | Live SEO/AEO/GEO/Readability scores with actionable checks + 11 content quality checks |

---

## REST API

Search Intel exposes a REST API under the `search-intel/v1` namespace. Enable it via **Search Intel > GEO > Enable REST API**.

All endpoints use standard WordPress REST authentication (cookie, application passwords, or OAuth).

### Endpoints

| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| `GET` | `/search-intel/v1/scores/{id}` | `read` (published) | SEO, AEO, GEO, readability scores |
| `GET` | `/search-intel/v1/geo/{id}` | `read` (published) | Full GEO metadata (AI summary, topics, entities, intent, audience) |
| `GET` | `/search-intel/v1/meta/{id}` | `read` (published) | SEO meta fields (title, description, keyword, canonical, robots) |
| `PUT` | `/search-intel/v1/meta/{id}` | `edit_post` | Update SEO meta fields |
| `POST` | `/search-intel/v1/analyze/{id}` | `edit_post` | Run content analysis and save scores |
| `POST` | `/search-intel/v1/bulk/rescore` | `manage_options` | Batch rescore with offset pagination |
| `GET` | `/search-intel/v1/settings` | `manage_options` | Read all plugin settings |
| `PUT` | `/search-intel/v1/settings` | `manage_options` | Update plugin settings |
| `POST` | `/search-intel/v1/sitemap/invalidate` | `manage_options` | Clear sitemap transient caches |

### Example

```bash
# Get scores for post 42
curl -u admin:APP_PASSWORD https://example.com/wp-json/search-intel/v1/scores/42

# Trigger analysis
curl -X POST -u admin:APP_PASSWORD https://example.com/wp-json/search-intel/v1/analyze/42

# Export settings
curl -u admin:APP_PASSWORD https://example.com/wp-json/search-intel/v1/settings
```

---

## WP-CLI Commands

Search Intel registers a `search-intel` WP-CLI command group with the following subcommands:

```bash
# Analyze a single post (displays score table)
wp search-intel analyze 42
wp search-intel analyze 42 --save        # Save scores to post meta

# Bulk rescore all published posts
wp search-intel rescore --all
wp search-intel rescore --all --post_type=page --batch_size=100

# Bulk regenerate empty metadata (descriptions, AI summaries, content types)
wp search-intel regenerate --all
wp search-intel regenerate --all --post_type=page

# Sitemap cache management
wp search-intel sitemap cache             # Clear + queue regeneration
wp search-intel sitemap clear             # Clear caches only

# Settings export/import
wp search-intel export > settings.json
wp search-intel import settings.json
wp search-intel import settings.json --mode=overwrite

# Display score table
wp search-intel scores
wp search-intel scores --format=csv --limit=100
wp search-intel scores --orderby=seo
```

---

## Bulk Tools

Navigate to **Search Intel > Bulk Tools** in the WordPress admin to access:

- **Bulk Rescore** — re-run SEO/AEO/GEO/readability analysis on all published posts with a live progress bar
- **Bulk Regenerate Metadata** — auto-fill empty descriptions, AI summaries, and content types (existing values are never overwritten)
- **Sitemap Cache** — clear all sitemap transient caches and queue regeneration
- **Export Settings** — download all plugin settings + social profiles as a timestamped JSON file
- **Import Settings** — upload a JSON file with **merge** (overlay on current) or **overwrite** (reset to defaults first) mode

All bulk operations are batched via AJAX (20 posts per request) with a progress bar, so they won't time out on large sites.

---

## Gutenberg Sidebar

Search Intel adds a native **PluginDocumentSettingPanel** to the Block Editor with four tabs:

### SEO Tab
- **SEO Title** — custom title tag with character count (60 max)
- **Meta Description** — custom description with character count (160 max)
- **Focus Keyword** — primary keyword for content analysis
- **Secondary Keywords** — comma-separated additional keywords
- **Canonical URL** — override the canonical URL
- **noindex / nofollow** — toggle robots directives
- **Social Overrides** — collapsible panel for OG and Twitter title/description
- **SERP Preview** — live preview of how the page appears in Google results

### AEO Tab
- **Direct Answer** — concise answer for featured snippets and voice assistants (300 char limit)
- **Schema Type Override** — select Article, NewsArticle, BlogPosting, HowTo, FAQPage, or Review (default: auto-detect)
- **FAQ & HowTo Guidance** — prompts to use the dedicated blocks for structured data

### GEO Tab
- **AI Summary** — factual summary for LLM consumption (500 char limit)
- **Key Topics** — comma-separated topics for AI indexing
- **Content Type** — article, how-to, faq, comparison, review, guide, news, opinion
- **Content Intent** — informational, navigational, transactional, commercial
- **Target Audience** — general, beginner, intermediate, expert, professional
- **Expertise Level** — beginner through expert
- **Fact-Check Date** — date picker for freshness signals
- **Local Relevance** — toggle with conditional location field

### Analysis Tab
- **Score Rings** — SVG ring visualizations for SEO, AEO, GEO, and Readability scores (color-coded: green ≥80, yellow ≥50, red <50)
- **Run Analysis** — one-click analysis via the REST API with spinner feedback
- **Check Results** — categorized pass/fail/info items from the content analyzer

> **Classic Editor users**: The existing PHP metabox continues to work unchanged. The Gutenberg sidebar is only loaded in the Block Editor.

---

## Custom Blocks

Search Intel provides 6 custom Gutenberg blocks under the `search-intel` namespace:

### `search-intel/faq`
Visual FAQ editor with numbered question/answer pairs. Add, remove, and reorder items directly in the editor. Outputs microdata with `itemScope`/`itemProp` attributes and a `data-schema="FAQPage"` data attribute for JSON-LD generation.

### `search-intel/howto`
Step-by-step tutorial editor with a title, description, optional total time (ISO 8601), and per-step name, instructions, and image upload via `MediaUpload`. Outputs an ordered list with HowTo schema data attributes.

### `search-intel/toc`
Auto-generated Table of Contents from headings in the post. Updates in real-time as you add or edit headings. Settings: max heading depth (1–6) and ordered/unordered list. **Dynamic block** — rendered server-side via `render_toc()` on the frontend.

### `search-intel/breadcrumbs`
Theme-aware breadcrumb navigation with configurable separator (`›`, `/`, `»`, `→`, `|`) and home label. Outputs a full `BreadcrumbList` schema with `itemListElement` microdata. **Dynamic block** — rendered server-side via `render_breadcrumbs()` with category hierarchy.

### `search-intel/answer-box`
Highlighted answer container optimized for featured snippet extraction. Question + answer fields with `data-si-answer-box` and `data-si-direct-answer` attributes for AI and search engine parsing.

### `search-intel/citation`
Inline source citation block wrapping quoted text with source metadata. Inspector panel for source name, URL, type (website, journal, book, report, news, government), author, and date. Outputs a `<figure>` with `CreativeWork` microdata.

> **FSE Templates**: The TOC, Breadcrumbs, and FAQ blocks are all usable in Full Site Editing templates (`header.html`, `single.html`, etc.).

---

## Shortcodes

### FAQ Block

```
[search_intel_faq question="What is SEO?" answer="SEO stands for Search Engine Optimization."]
```

### HowTo Block

```
[search_intel_howto name="How to Install a Plugin" time="PT5M"]
  Step content here...
[/search_intel_howto]
```

---

## Hooks and Filters

### Filters

| Filter | Description | Parameters |
|--------|-------------|------------|
| `search_intel_singular_schema` | Modify article/page schema before output | `$schema`, `$post_id` |

### Actions

All output hooks fire on `wp_head` with the following priorities:

| Priority | Module | Output |
|----------|--------|--------|
| 1 | SEO Manager | Meta description, author |
| 2 | SEO Manager | Canonical URL |
| 3 | SEO Manager / GEO | Robots meta, AI crawl directives |
| 4 | SEO Manager | Open Graph tags (with social overrides) |
| 5 | SEO Manager | Twitter Card tags (with social overrides) |
| 6 | GEO Manager | AI meta tags + intent, audience, expertise, local relevance |
| 7 | GEO Manager | Freshness meta |
| 10 | Schema Manager | JSON-LD @graph (with citations, audience, contentLocation) |
| 11 | AEO Manager | FAQ / HowTo schema |
| 12 | AEO Manager | Speakable schema |
| 13 | AEO Manager | Direct-answer meta tag |
| 13 | GEO Manager | Author E-E-A-T schema |
| 20 | SEO Manager | Document title filter |
| 25 | Title Engine | Template-based title resolution |

---

## File Structure

```
search-intel/
├── search-intel.php              # Main plugin bootstrap + autoloader + WP-CLI registration
├── uninstall.php                 # Database cleanup on uninstall
├── readme.txt                    # WordPress.org readme
├── README.md                     # This file
├── LICENSE.md                    # GPL-2.0-or-later
├── CHANGELOG.md                  # Version history
├── ROADMAP.md                    # Development roadmap
├── webpack.config.js             # @wordpress/scripts build config (7 entry points)
│
├── includes/
│   ├── class-core.php            # Singleton orchestrator + default settings
│   ├── class-title-engine.php    # Title template engine with variable resolver
│   ├── class-seo-manager.php     # Meta, OG, Twitter, canonical, robots
│   ├── class-schema-manager.php  # JSON-LD structured data (+ citations, audience, location)
│   ├── class-aeo-manager.php     # FAQ, HowTo, Speakable, direct-answer, Q&A detection
│   ├── class-geo-manager.php     # AI meta, llms.txt, entities, E-E-A-T, intent, local
│   ├── class-content-analyzer.php# Scoring engine + analysis-on-save + 11 content checks
│   ├── class-sitemap.php         # XML sitemap with images + transient caching
│   ├── class-admin.php           # Settings, metaboxes, user fields, Bulk Tools page
│   ├── class-rest-api.php        # REST API controller (search-intel/v1 namespace)
│   ├── class-bulk-tools.php      # Bulk rescore, regenerate, export/import (AJAX)
│   ├── class-cli.php             # WP-CLI commands (analyze, rescore, scores, etc.)
│   └── class-blocks.php          # Block editor: sidebar, custom blocks, meta registration
│
├── src/                           # Block editor source (compiled to build/)
│   ├── store/
│   │   └── index.js              # @wordpress/data store (search-intel/editor)
│   ├── sidebar/
│   │   ├── index.js              # PluginDocumentSettingPanel registration
│   │   ├── sidebar.css           # Sidebar styles (tabs, SERP preview, score rings)
│   │   └── panels/
│   │       ├── seo-panel.js      # SEO fields + SERP preview + social overrides
│   │       ├── aeo-panel.js      # Direct answer + schema type override
│   │       ├── geo-panel.js      # AI summary, topics, intent, audience, local
│   │       └── score-panel.js    # Score rings + Run Analysis + check results
│   └── blocks/
│       ├── faq/                  # FAQ block (edit, save, editor CSS)
│       ├── howto/                # HowTo block (edit, save, editor CSS)
│       ├── toc/                  # Table of Contents block (dynamic)
│       ├── breadcrumbs/          # Breadcrumbs block (dynamic)
│       ├── answer-box/           # Answer Box block (edit, save, editor CSS)
│       └── citation/             # Citation block (edit, save, editor CSS)
│
├── build/                         # Compiled assets (sidebar.js, blocks/*/index.js)
│
├── admin/
│   ├── css/
│   │   └── admin-style.css       # Admin UI styles + bulk tools progress bars
│   ├── js/
│   │   ├── admin-script.js       # Tabs, SERP preview, repeaters, AJAX analysis
│   │   └── bulk-tools.js         # Batch AJAX processing with progress UI
│   └── views/
│       ├── metabox.php           # Post editor metabox (SEO, AEO, GEO, Advanced, Analysis)
│       ├── settings-general.php  # General settings page
│       ├── settings-seo.php      # SEO settings (+ OG defaults, descriptions, robots, titles)
│       ├── settings-aeo.php      # AEO settings page
│       ├── settings-geo.php      # GEO settings (+ content defaults, REST API toggle)
│       ├── settings-sitemap.php  # Sitemap settings (+ taxonomy toggles)
│       ├── settings-social.php   # Social profiles page
│       └── bulk-tools.php        # Bulk Tools admin page
│
└── languages/                    # Translation files (i18n ready)
```

---

## Compatibility

| Environment | Status | Notes |
|-------------|--------|-------|
| WordPress 6.0 - 6.7 | Supported | Tested across major versions |
| Full Site Editing (FSE) themes | Supported | Works via `wp_head` hook |
| Classic themes | Supported | Full compatibility |
| Block Editor (Gutenberg) | Supported | Reads content via `wp.data` |
| Classic Editor | Supported | TinyMCE + textarea fallback |
| PHP 7.4 - 8.3 | Supported | Typed properties, null coalescing |
| MySQL 5.7+ / MariaDB 10.3+ | Supported | Standard meta queries |
| Multisite | Compatible | Per-site settings |

---

## Testing & CI

Search Intel has a comprehensive automated test suite and CI pipeline.

### Test Coverage

| Suite | Count | Framework |
|-------|-------|-----------|
| Unit tests | 225 tests, 441 assertions | PHPUnit 9.6 |
| Integration tests | 125 tests, 202 assertions | PHPUnit 9.6 |
| E2E tests | 40 tests | Playwright (Chromium) |

### Static Analysis & Linting

| Tool | Level / Standard | Status |
|------|-----------------|--------|
| Psalm | Level 4 | Passing (with WP stubs baseline) |
| PHPStan | Level 6 | Passing |
| PHPCS | WordPress-Core (WPCS 3.0) | Informational |
| ESLint | Custom config | Informational |
| Stylelint | stylelint-config-standard | Passing (0 errors) |

### Performance Benchmarks

| Metric | Result | Target |
|--------|--------|--------|
| `wp_head` full pipeline | < 10ms | < 10ms |
| Sitemap cold (1000+ posts) | 65ms | < 500ms |
| Content analysis | < 200ms | < 200ms |

### CI Pipeline (GitHub Actions)

- PHPUnit on PHP 8.1, 8.2, 8.3
- Psalm level 4, PHPStan level 6, PHPCS, ESLint, Stylelint
- Performance regression checks
- Playwright E2E via `wp-env` (Docker)

### Running Tests Locally

```bash
# Unit + integration tests
composer install
vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --testsuite integration

# E2E tests (requires Docker)
npm install
npx wp-env start
npx playwright test

# Static analysis
vendor/bin/psalm --no-cache
npx stylelint "admin/css/**/*.css"
```

---

## Security

- All user input sanitized with `sanitize_text_field()`, `wp_kses_post()`, `esc_url_raw()`
- All output escaped with `esc_html()`, `esc_attr()`, `esc_url()`
- Nonce verification on all form submissions and AJAX calls
- Capability checks (`manage_options`, `edit_post`, `edit_user`) on all write operations
- No direct file access — all PHP files check `ABSPATH`
- No external API calls except optional search engine pinging (non-blocking)
- No JavaScript on the public frontend — zero attack surface
- Rate limiting on AJAX content analysis (10 requests/min per user)

---

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for full details on branching strategy, commit conventions, and local development setup.

1. Fork the repository
2. Create a feature branch from `develop`: `git checkout -b feature/my-feature develop`
3. Follow WordPress coding standards (WPCS) and [Conventional Commits](https://www.conventionalcommits.org/)
4. Write tests for new functionality
5. Submit a pull request against `develop`

---

## License

Search Intel is licensed under the [GNU General Public License v2.0 or later](LICENSE.md).
