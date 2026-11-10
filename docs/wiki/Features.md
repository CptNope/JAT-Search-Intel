# Features

JAT Search Intel covers three kinds of search visibility in one plugin:

- **SEO** (classic search engine optimization)
- **AEO** (Answer Engine Optimization, for featured snippets, voice and People Also Ask)
- **GEO** (Generative Engine Optimization, for AI answer engines such as ChatGPT, Perplexity and Google AI Overviews)

There is no paid tier. Features that other SEO plugins keep for premium plans, such as category SEO, a primary category, crawl cleanup, index status, change history and many AI providers, are all in the free plugin.

## SEO

- **Titles and descriptions**: custom meta title and description per post, with a live SERP preview and title templates (`%title%`, `%sitename%`, `%sep%`, `%category%`, `%tag%`, `%date%`, `%author%`, `%page%`, `%excerpt%`, `%search_query%`).
- **Social**: Open Graph tags and Twitter Card tags, with per-post overrides for title, description and image.
- **Indexing control**: canonical URLs, and per-post robots directives (`noindex`, `nofollow`).
- **Categories, tags and other taxonomies**: every public taxonomy term gets its own SEO title (title variables allowed), meta description, focus keyword, canonical URL, `noindex`/`nofollow` and social title, description and image, on the term's edit screen. Under **Settings → SEO → Taxonomies** you can noindex whole taxonomies, and empty terms are noindexed by default. Noindexed terms, and terms with a canonical pointing elsewhere, are left out of the sitemap. Title templates exist for taxonomy archives and other archives.
- **Primary category**: when a post has several categories (or a product several product categories), choose the main one in the editor's **Primary category** panel. It is used in the permalink (with `%category%` in the permalink structure), breadcrumbs, `articleSection` schema and the `%category%` title variable.
- **Site verification**: codes for Google, Bing, Yandex, Baidu, Pinterest, Facebook, Naver and Seznam under **Settings → SEO → Site Verification**. Paste either the code or the whole `<meta>` tag; only the code is kept.
- **Crawl cleanup**: under **Settings → SEO → Crawl Cleanup**, remove the shortlink, RSD, Windows Live Writer and generator tags, emoji scripts, oEmbed and REST API discovery links, the `X-Pingback` header, the comments feeds and the extra category/tag/author feeds, and answer spam searches (links, domains, HTML or over 100 characters) with a 404. All off by default.
- **Index Status** (**Reports → Index Status**): Google's verdict, coverage state, last crawl and chosen canonical for every page, from the Search Console URL Inspection API. Checks run daily (up to 200 pages, switched on under **Settings → General**) or on demand, 25 at a time. Uses the Rank Tracker's Search Console connection.
- **SEO change history**: every change to a post's SEO fields is recorded with the time and the user, and any change can be put back in one click from the editor's **SEO change history** panel (or the classic meta box). The last 30 changes are kept per post. The robots.txt editor keeps its last 10 versions the same way.
- **Last-modified date**: for small fixes, turn on **Don't update the last-modified date** in the editor so search results, schema and the sitemap keep the real "last updated" date.
- **Reading time**: added to Article schema as `timeRequired` and to X cards, and available as `[jat_search_intel_reading_time]`. X cards also show the author.
- **XML sitemaps**: `/sitemap.xml` index with paginated per-type sitemaps and images, plus optional `/news-sitemap.xml` and `/video-sitemap.xml` (YouTube, Vimeo and self-hosted video). Sitemap URLs are added to robots.txt.
- **HTML sitemap**: the `[jat_search_intel_html_sitemap post_types="page,post" limit="500"]` shortcode.
- **Rank tracker**: daily positions and 7/28-day movement for your keywords, from your own Search Console data.
- **Media**: automatic image alt text from file names, attachment-page redirects and RSS feed backlinks.
- **Content analysis**: focus keyword density and placement, and readability (Flesch-Kincaid).
- **Schema**: one connected JSON-LD graph (Organization or Person, WebSite, WebPage, Article, author, breadcrumbs and image), linked by `@id`. You can paste custom JSON-LD per post; it is validated and merged into the graph.

## AEO

- **FAQPage schema**: manual entry, or detected automatically from question-style headings.
- **HowTo schema**: step-by-step structured data.
- **SpeakableSpecification**: for voice assistants.
- **Featured-snippet scoring**: checks content structure such as lists, tables and concise answer paragraphs.
- **FAQ and HowTo** shortcodes and blocks, with built-in schema.

## GEO

- **AI Summary**: a short factual summary written for LLMs, used in meta tags, llms.txt and the Markdown copy.
- **llms.txt and llms-full.txt**: generated from your content, following the llms.txt proposal.
- **Markdown copies**: every public post at `/post-slug.md`, linked from the page and from llms.txt.
- **Schema map**: `/wp-json/jat-search-intel/v1/schema-map` lists feeds of your site's structured data, one page of up to 200 posts per feed, and is advertised in robots.txt. An AI agent can read the schema of the whole site without crawling every page. Noindexed and "hide from AI" content is left out. Switch it off under **Settings → GEO**.
- **Google Preferred Sources**: a button that lets readers add your site as a preferred source in Google's Top Stories, as a block, the `[jat_search_intel_preferred_source]` shortcode, or added automatically after posts (**Settings → GEO → Preferred Source**).
- **Entity markup**: mark people, organizations, places and products with Schema.org microdata. The first whole-word mention in the post is wrapped.
- **E-E-A-T author signals**: credentials, expertise areas and years of experience.
- **Freshness**: content-freshness meta tags and fact-check dates.
- **Content understanding**: content type detection (article, how-to, FAQ, comparison, review, guide), key topics and semantic keyword signals.
- **AI crawler control**: 21 AI bots, Content Signals and IndexNow. See [AI Search](AI-Search).
- **AI Citations** (**Reports → AI Citations**): list up to 25 questions your customers ask. The plugin asks ChatGPT, Claude, Gemini and Perplexity each one with web search on, using your own API keys (OpenAI, Anthropic and Gemini keys from **Settings → AI**, and a Perplexity key on the report page). For every question and engine you see whether it cited your site (and which page), read your site without citing it, or named you, plus which sites it cited instead. Checks run weekly when **Settings → GEO → AI Citation Checks** is on, or when you click **Check now**. They run in the background, six at a time, and stop early for an engine whose key is rejected. Each check is one web-search request billed to your account.

## Scores

Every post gets separate **SEO**, **AEO** and **GEO** scores (0–100) with recommendations. They are calculated when you save, from the editor's **Analysis** tab, with **Tools → Bulk Tools → Rescore**, or with `wp jat-search-intel rescore --all`.

### GEO health score

The **AI Visibility** dashboard scores the whole site out of 100, weighted by what research on AI citations shows matters:

| Factor | Points | What it checks |
|---|---|---|
| AI search crawlers allowed | 20 | The site is public, AI search and user-request crawlers (OAI-SearchBot, Claude-SearchBot, PerplexityBot…) aren't blocked, and Content Signals don't say `ai-input=no`. Blocking training crawlers costs nothing. |
| Indexed in Google and Bing | 20 | Sitemap (5), IndexNow (5), and the share of checked pages Google has indexed from [Index Status](Features#seo) (10; half credit until at least 5 pages are checked) |
| Freshness | 20 | Share of the 100 most recent posts updated in the last 6 months (half credit within a year) |
| Answer-ready content | 15 | Share of recent posts with a direct answer, FAQs or an AEO score of 70+ |
| Entity and authors | 15 | Organization or person name (4), logo (3), social profiles (4), author biographies (4) |
| Structured data | 10 | Schema output (7) and the extra content types (3) |

llms.txt and the AI meta tags are listed as optional extras with no points: no major AI engine has said it uses them, and server-log studies found 97% of llms.txt files are never requested. They stay available because they cost almost nothing.

The **Refresh Queue** on the Readiness page puts pages that an AI engine stopped citing at the top, with the questions they lost, followed by the pages that have gone longest without an update.

## Optional modules

Switch these on under **Settings → General → Module Toggles**. Modules you leave off are never loaded.

| Module | What it does |
|---|---|
| Redirect manager | Exact, prefix and regex redirects, a 404 log, and CSV import |
| IndexNow | Pings Bing and other engines when content is published or updated |
| Robots.txt editor | Edit the virtual robots.txt |
| Broken link checker | Finds external links that no longer resolve |
| Internal linking | Link suggestions and orphan-page checks |
| Local Business schema | LocalBusiness markup with address, hours and geo |
| WooCommerce schema | Product markup with price, availability and reviews |
| Multilingual | hreflang for WPML and Polylang |
| Role-based access | Choose which roles can edit SEO, AEO or GEO fields |
| AI Engine | Optional AI-written descriptions, summaries and FAQs with your own key: OpenAI, Anthropic, Google Gemini, Mistral, DeepSeek, OpenRouter, any OpenAI-compatible endpoint, or a local Ollama server |
| Reports | Content decay, keyword cannibalization, Core Web Vitals, GA4 insights and scheduled email reports |

## Bulk tools

**Tools → Bulk Tools** has batch operations with live progress:

- rescore all content
- fill in empty meta fields (existing values are never overwritten)
- clear and warm the sitemap cache
- export and import settings as JSON (merge or overwrite)

## Performance

The plugin prints lightweight meta tags and one JSON-LD block, and loads no JavaScript or CSS on the public site. On a normal page view it runs **no extra database queries** compared with the plugin switched off. Modules load only when needed: the REST API on REST requests, the analyzer when a post is saved, and admin screens in the admin.
