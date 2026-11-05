# Features

JAT Search Intel covers three kinds of search visibility in one plugin:

- **SEO** (classic search engine optimization)
- **AEO** (Answer Engine Optimization, for featured snippets, voice and People Also Ask)
- **GEO** (Generative Engine Optimization, for AI answer engines such as ChatGPT, Perplexity and Google AI Overviews)

## SEO

- **Titles and descriptions**: custom meta title and description per post, with a live SERP preview and title templates (`%title%`, `%sitename%`, `%sep%`, `%category%`, `%tag%`, `%date%`, `%author%`, `%page%`, `%excerpt%`, `%search_query%`).
- **Social**: Open Graph tags and Twitter Card tags, with per-post overrides for title, description and image.
- **Indexing control**: canonical URLs, and per-post robots directives (`noindex`, `nofollow`).
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
- **Entity markup**: mark people, organizations, places and products with Schema.org microdata. The first whole-word mention in the post is wrapped.
- **E-E-A-T author signals**: credentials, expertise areas and years of experience.
- **Freshness**: content-freshness meta tags and fact-check dates.
- **Content understanding**: content type detection (article, how-to, FAQ, comparison, review, guide), key topics and semantic keyword signals.
- **AI crawler control**: 21 AI bots, Content Signals and IndexNow. See [AI Search](AI-Search).

## Scores

Every post gets separate **SEO**, **AEO** and **GEO** scores (0–100) with recommendations. They are calculated when you save, from the editor's **Analysis** tab, with **Tools → Bulk Tools → Rescore**, or with `wp jat-search-intel rescore --all`.

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
| AI Engine | Optional AI-written descriptions, summaries and FAQs (OpenAI, Anthropic or a local Ollama server) |
| Reports | Content decay, keyword cannibalization, Core Web Vitals, GA4 insights and scheduled email reports |

## Bulk tools

**Tools → Bulk Tools** has batch operations with live progress:

- rescore all content
- fill in empty meta fields (existing values are never overwritten)
- clear and warm the sitemap cache
- export and import settings as JSON (merge or overwrite)

## Performance

The plugin prints lightweight meta tags and one JSON-LD block, and loads no JavaScript or CSS on the public site. On a normal page view it runs **no extra database queries** compared with the plugin switched off. Modules load only when needed: the REST API on REST requests, the analyzer when a post is saved, and admin screens in the admin.
