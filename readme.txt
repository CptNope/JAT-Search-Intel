=== JAT Search Intel ===
Contributors: cptnope
Tags: seo, schema, llms-txt, ai-search, redirects
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.8.33
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SEO, answer-engine and AI-search optimization: meta tags, schema, sitemaps, llms.txt, redirects and live SEO/AEO/GEO content scores.

== Description ==

JAT Search Intel covers the three pillars of modern search visibility in one plugin: classic SEO, Answer Engine Optimization for featured snippets and voice results, and Generative Engine Optimization for AI answer engines.

Everything runs on your own site. No account, no tracking and no outside requests unless you switch on an optional integration (see External services below).

**Set up in three steps**
A short wizard after activation covers who you are (organisation or person, logo, official profiles), search engines (indexing, sitemap, IndexNow) and AI assistants (allow AI search but block training, allow everything, or block AI crawlers). It finishes on your Readiness score. Everything lives under six menu entries: Readiness, Settings, Redirects, AI Search, Reports and Tools.

**AI Search Readiness**
One screen that answers "can Google, Bing and AI assistants find, read, trust and cite this site?" It checks indexing settings, robots.txt (including whether you are accidentally blocking Googlebot, Bingbot or the AI search crawlers), conflicting SEO plugins, HTTPS, permalinks, sitemaps, IndexNow, llms.txt, Markdown copies, structured data, brand profiles, author bios, meta descriptions and content freshness, then gives a 0–100 score. Anything the plugin controls gets a one-click Fix button.

The Refresh Queue beside it lists the pages that have gone longest without an update, weighted by real Search Console clicks when connected, so you know what to refresh first.

**Passage Coach in the editor**
Search results and AI answers quote single passages, not whole pages. Passage Coach checks each block of the post you are writing and points to the exact passage to improve: an intro that warms up instead of answering, a question heading with no direct answer under it, a statistic with no source, a paragraph too long to quote, a comparison with no table, a reference to an out-of-date year, an image with no alt text, or a heading that skips a level. "Show passage" jumps to the block; for a missing answer or comparison table it inserts a starter block for you. It runs instantly on your own server, with no AI service needed.

**Page Performance**
One report puts Search Console clicks and impressions, visits from AI assistants, AI crawler activity, GEO score and freshness side by side for every page, with the next step for each: refresh it, rewrite its title, or make it easier for AI to cite.

**AI agents and MCP**
On WordPress 6.9 and later the plugin registers abilities with the WordPress Abilities API, so AI agents connected through the REST API or the MCP Adapter can search your content and read a page's SEO data (both limited to published, indexable pages), and logged-in editors can have an agent analyse posts (including Passage Coach findings), update titles and descriptions, run the readiness audit and fetch the refresh queue. Every ability that reads scores or changes data checks WordPress capabilities.

**No paid tier**
Every feature is in the free plugin. Things other SEO plugins keep for their premium plans are included: SEO fields for categories, tags and product categories, a primary category, crawl cleanup, Search Console index status for every page, a change history with one-click restore for SEO fields and robots.txt, a site-wide schema map for AI agents, AI citation tracking across ChatGPT, Claude, Gemini and Perplexity, social previews in the editor, eight AI providers with your own key, and the same abilities for AI agents.

**SEO — Search Engine Optimization**
Traditional search optimization with a modern implementation.

* Custom meta titles and descriptions with SERP preview
* Open Graph tags for Facebook, LinkedIn, and social sharing
* Twitter Card tags with per-post configuration
* Canonical URL management
* Robots meta directives (noindex, nofollow) per post
* SEO for categories, tags and other taxonomies: title, meta description, canonical, noindex/nofollow and social title, description and image per term; noindex whole taxonomies or empty terms
* Primary category for posts and WooCommerce products, used in permalinks, breadcrumbs, schema and the `%category%` title variable
* Site verification for Google, Bing, Yandex, Baidu, Pinterest, Facebook, Naver and Seznam (paste the code or the whole meta tag)
* Crawl cleanup: remove shortlinks, RSD, Windows Live Writer, generator tag, emoji scripts, oEmbed and REST links, the X-Pingback header and unneeded feeds, and turn away spam searches
* Index Status: Google's verdict for each page from the Search Console URL Inspection API, checked daily or on demand
* SEO change history: every change to a post's SEO fields is recorded with who made it, and any change can be put back in one click; robots.txt keeps its last 10 versions too
* Freeze the last-modified date for small fixes, so search results and schema keep the real "last updated" date
* Estimated reading time in schema, in X (Twitter) cards and as a `[jat_search_intel_reading_time]` shortcode
* Facebook and X share previews in the block editor
* XML Sitemaps with image support and automatic pagination
* Google News sitemap and video sitemap (YouTube, Vimeo, self-hosted)
* HTML sitemap shortcode for visitors
* Keyword rank tracker built on your own Search Console data (daily positions, 7/28-day movement)
* Automatic image alt text from file names, attachment-page redirects and RSS feed backlinks
* Focus keyword analysis with density and placement checks
* Readability scoring (Flesch-Kincaid)
* One connected JSON-LD graph: Organization/Person, WebSite, WebPage, Article, author, breadcrumbs and image, linked by @id
* Article schema with full author, date, and category support
* Custom JSON-LD per post (paste any Schema.org markup; validated and merged into the graph)
* Schema tab in the editor: fill-in forms for Recipe, Event, Review, Course, SoftwareApplication, JobPosting and VideoObject, with a link to Google's Rich Results Test
* Headline analyzer: scores each title for length, word balance, power and emotional words, and numbers as you type
* SEO title, description, focus keyword and noindex in Quick Edit and Bulk Edit on the posts list
* Admin bar menu with the page's scores and links to the Rich Results Test, Schema Markup Validator and PageSpeed Insights
* RSS sitemap (`/sitemap.rss`) of recently updated content, for fast discovery
* Page builders: Elementor, Bricks, Beaver Builder, Divi and WPBakery content is read for scores, descriptions and schema
* Image titles and alt text from file names, with your choice of letter case and punctuation, and a Media > Missing alt text screen to fill the gaps
* Local Business block and `[jat_search_intel_local]` shortcode: show a location's address, opening hours or a map, matching its schema
* Headless sites: every published post and page has `jat_search_intel_head` (HTML) and `jat_search_intel_head_json` in the WordPress REST API

**AEO — Answer Engine Optimization**
Optimize your content for featured snippets, voice assistants, and People Also Ask boxes.

* FAQPage schema — manual entry or auto-detection from question-style headings
* HowTo schema with step-by-step structured data
* SpeakableSpecification for voice assistant results (Google Assistant, Alexa, Siri)
* Featured snippet optimization scoring
* Content structure analysis (lists, tables, concise paragraphs)
* Shortcodes for FAQ and HowTo blocks with built-in schema

**GEO — Generative Engine Optimization**
Optimize for AI-powered answer engines such as ChatGPT, Perplexity and Google AI Overviews.

* AI Summary field — provide a concise, factual summary for LLM consumption
* AI Citations report — list the questions your customers ask; each week ChatGPT, Claude, Gemini and Perplexity are asked them with web search on (using your own API keys), and the report shows whether they cited your site, read it without citing it, or named you, and which sites they cited instead
* GEO health score based on what research shows AI engines reward: AI search crawler access, Google and Bing indexing, freshness, answer-ready content, entity and author details, and structured data
* Pages that lose an AI citation go to the top of the Refresh Queue
* llms.txt endpoint — machine-readable site summary following the llms.txt specification
* Entity markup — define people, organizations, places, and products with Schema.org microdata
* E-E-A-T author signals — credentials, expertise areas, years of experience
* Content freshness meta tags — signal recency to AI crawlers
* Content type detection — article, how-to, FAQ, comparison, review, guide
* Markdown copy of every public post for AI agents (`/post-slug.md`), linked from the page and from llms.txt
* Schema map: the structured data of every public page in a few feeds (advertised in robots.txt), so AI agents can read your whole site's schema without crawling it
* Google Preferred Sources button (block, shortcode or added after posts) so readers can choose to see more of your site in Google's Top Stories
* AI crawler access: allow or block 21 AI bots individually in robots.txt, grouped by purpose (training, AI search, live user fetch), with one-click presets
* Content Signals in robots.txt: say yes or no to search, AI answers and AI training separately
* One-click IndexNow: Bing (which powers ChatGPT search and Copilot) hears about new and updated pages within minutes, with no key to set up (opt-in, because it sends your URLs to Bing)
* AI traffic analytics: AI crawler visits and visitors arriving from ChatGPT, Perplexity, Claude, Gemini, Copilot and more
* Per-post AI crawl directives
* Fact-check date tracking
* Key topics extraction and semantic keyword signals

**Unified Content Analyzer**
Real-time scoring dashboard in the post editor with separate SEO, AEO, and GEO scores plus actionable recommendations.

**REST API**
Full REST API under the `jat-search-intel/v1` namespace with toggleable endpoints:

* `GET /scores/{id}` — SEO, AEO, GEO, readability scores (users who can edit the post)
* `GET /geo/{id}` — full GEO metadata
* `GET/PUT /meta/{id}` — read/write SEO meta fields (public fields only for visitors; editing requires edit access)
* `POST /analyze/{id}` — trigger content analysis
* `POST /bulk/rescore` — batch rescore with pagination
* `GET/PUT /settings` — read/write plugin settings (admin only)
* `POST /sitemap/invalidate` — clear sitemap caches (admin only)

**Bulk Tools**
Admin page with batch operations and live progress bars:

* Bulk rescore all published content
* Bulk regenerate empty metadata fields (non-destructive)
* Sitemap cache clear and warm
* Export/import settings as JSON (merge or overwrite mode)

**WP-CLI Commands**

* `wp jat-search-intel analyze <post_id>` — analyze a single post
* `wp jat-search-intel rescore --all` — bulk rescore with progress bar
* `wp jat-search-intel regenerate --all` — bulk regenerate metadata
* `wp jat-search-intel sitemap cache|clear` — manage sitemap caches
* `wp jat-search-intel export / import` — settings export/import
* `wp jat-search-intel scores` — display score table (table, CSV, JSON, YAML)
* `wp jat-search-intel index-status [--post=<id>] [--limit=<n>]` — check Google index status
* `wp jat-search-intel citations [--check]` — show (or run) AI citation checks

**Gutenberg Sidebar Panel**
Native Block Editor integration with a tabbed sidebar panel:

* SEO tab: title, description, keyword, canonical, robots, social overrides, live SERP preview
* AEO tab: direct answer, schema type override
* GEO tab: AI summary, key topics, content type, intent, audience, expertise, fact-check date
* Analysis tab: score ring visualizations + one-click analysis via REST API

**Custom Gutenberg Blocks**

* FAQ block — visual FAQ editor with automatic FAQPage schema
* How To block — step editor with per-step images and HowTo schema
* Table of Contents — auto-generated from post headings
* Breadcrumbs — theme-aware navigation with BreadcrumbList schema
* Answer Box — featured snippet-optimized answer formatting
* Citation — inline source citation with CreativeWork schema
* Preferred Source on Google — a button that lets readers add your site as a preferred source

**Optional modules**
Turn on only what you need under Settings > General > Module Toggles: redirect manager with 404 log, redirect chain and loop warnings and export to .htaccess or Nginx, IndexNow, robots.txt editor, broken link checker, internal linking suggestions, Local Business and WooCommerce schema, multilingual hreflang (WPML/Polylang), role-based access, AI writing assistance, content decay and keyword cannibalization reports, Core Web Vitals, GA4 insights and scheduled reports. Disabled modules are not loaded at all.

== Installation ==

1. In WordPress, go to Plugins > Add New, search for "JAT Search Intel", then install and activate it. (Or upload the `jat-search-intel` folder to `/wp-content/plugins/`.)
2. Click **Run setup** in the notice that appears (or go to **JAT Search Intel > Tools > Setup Wizard**) and answer the three steps.
3. Check **JAT Search Intel > Readiness** and use the Fix buttons for anything it flags.
4. Edit any post or page: the JAT Search Intel sidebar shows live scores and fields, and Passage Coach (in the Post tab) lists the passages to improve.

Optional modules are switched on under **Settings > General**.

If you are switching from another SEO plugin, use **JAT Search Intel > Tools > Migration** to import titles, descriptions and focus keywords from Yoast SEO, Rank Math, All in One SEO, SEOPress or The SEO Framework, then deactivate the old plugin to avoid duplicate meta tags.

== Frequently Asked Questions ==

= Can I move from another SEO plugin? =
Yes. Tools > Migration imports titles, descriptions, focus keyphrases, robots settings, social fields, canonical URLs, primary categories, category and tag SEO, site-wide settings and redirects from All in One SEO, Yoast SEO, Rank Math and SEOPress. Title variables such as `#post_title` or `%%title%%` are converted. Preview the counts first; nothing in the other plugin is changed or deleted.

= Can I use it alongside another SEO plugin? =

It is designed to be your only SEO plugin. Running two will print duplicate meta tags and schema, and the plugin shows a notice if it detects one. Use the built-in migration tool to bring your existing data across first.

= What is AEO? =

Answer Engine Optimization focuses on getting your content into featured snippets, voice assistant results, and People Also Ask boxes. It emphasizes structured data (FAQ, HowTo), concise answers, and content formatting.

= What is GEO? =

Generative Engine Optimization is the practice of optimizing content for AI-powered search engines and answer systems like ChatGPT, Perplexity, Google AI Overviews, and Bing Copilot. It focuses on clear definitions, source citations, entity clarity, and author authority.

= What is llms.txt? =

llms.txt is a proposed standard (similar to robots.txt) that provides a machine-readable summary of your website for Large Language Models. JAT Search Intel auto-generates this file from your site content.

= Will this slow down my site? =

No. It prints lightweight meta tags and one JSON-LD block in the page head and loads no JavaScript or CSS on the public site. Measured on WordPress 7.1, normal page views run exactly as many database queries with the plugin active as without it: modules you leave off are never loaded, settings are read from WordPress's option cache, and redirects are matched against a cached index.

= Does it send my data anywhere? =

Not by default. Outside requests only happen when you enable and configure an integration listed under External services, and only to that service.

= Where is the source code for the editor scripts? =

The compiled block-editor files in `build/` are generated from the human-readable sources in `src/` (included in the plugin) with `npm install && npm run build`. The full development repository is at https://github.com/CptNope/JAT-Search-Intel.

= What happens to my data if I delete the plugin? =

It is kept, so nothing is lost if you reinstall. To remove everything on deletion, tick **Delete all plugin data** under Settings > General > Data Management first.

== External services ==

JAT Search Intel makes no outside requests by default. Each service below is contacted only after you enable the related module and enter your own credentials, and only from your server.

= OpenAI API (AI Engine, optional) =
Used to generate meta descriptions, summaries, FAQs and content suggestions when you click an AI button or run a bulk AI task. Sends the post title, content excerpt and the instruction for that task, with your API key. [Terms of use](https://openai.com/policies/terms-of-use/), [Privacy policy](https://openai.com/policies/privacy-policy/).

= Anthropic Claude API (AI Engine, optional) =
Same purpose and data as above when Anthropic is the selected provider. [Commercial terms](https://www.anthropic.com/legal/commercial-terms), [Privacy policy](https://www.anthropic.com/legal/privacy).

= Google Gemini, Mistral, DeepSeek and OpenRouter APIs (AI Engine, optional) =
Same purpose and data as above when one of these is the selected provider: Google Gemini ([Terms](https://ai.google.dev/gemini-api/terms), [Privacy](https://policies.google.com/privacy)), Mistral AI ([Terms](https://legal.mistral.ai/terms/commercial-terms-of-service), [Privacy](https://legal.mistral.ai/terms/privacy-policy)), DeepSeek ([Terms](https://platform.deepseek.com/downloads/DeepSeek%20Open%20Platform%20Terms%20of%20Service.html), [Privacy](https://chat.deepseek.com/downloads/DeepSeek%20Privacy%20Policy.html)) and OpenRouter, which forwards the request to the model you choose ([Terms](https://openrouter.ai/terms), [Privacy](https://openrouter.ai/privacy)).

= OpenAI, Anthropic, Google Gemini and Perplexity web search (AI Citations, optional) =
When you click Check now in the AI Citations report, or turn on weekly checks, each question you track is sent to the engines you have an API key for, with their web search tool on: OpenAI (Responses API), Anthropic (Messages API), Google Gemini (Grounding with Google Search) and Perplexity (Sonar API). It sends only the question and your API key; nothing about your visitors. Each check is billed to your account by the provider. Perplexity: [API Terms](https://www.perplexity.ai/hub/legal/perplexity-api-terms-of-service), [Privacy policy](https://www.perplexity.ai/hub/legal/privacy-policy). The other providers' terms are linked above.

= OpenAI-compatible endpoint (AI Engine, optional) =
If you choose a custom OpenAI-compatible endpoint, the same data goes to the URL you enter (for example your own LM Studio, vLLM or LiteLLM server). Which third party, if any, receives it depends on that endpoint.

= Ollama (AI Engine, optional) =
If you choose Ollama, requests go to the endpoint you configure (by default a server on your own machine at localhost). No third party is involved unless you point it at one.

= Google OAuth and Search Console API (Rank Tracker, optional) =
After you paste a Google service account key, the plugin requests an access token from Google's OAuth endpoint (oauth2.googleapis.com) once an hour at most, then fetches search performance for your tracked keywords from the Search Console API once a day or when you click Fetch now. Sends a token request signed with your key, your Search Console property and the keywords to look up. [Google APIs Terms](https://developers.google.com/terms), [Google Privacy Policy](https://policies.google.com/privacy).

= Google Search Console URL Inspection API (Index Status, optional) =
Uses the same service account connection as the Rank Tracker. When you turn on daily Index Status checks or click Check now, it sends the page URL and your Search Console property to Google and stores the returned index status. Up to 200 pages a day. [Google APIs Terms](https://developers.google.com/terms), [Google Privacy Policy](https://policies.google.com/privacy).

= Google Preferred Sources link (optional) =
The Preferred Source button is an ordinary link to google.com with your domain in it. Nothing is sent until a visitor clicks it.

= Bing Webmaster Tools API (AI Visibility Dashboard, optional) =
Fetches query statistics for your site. Sends your site URL and Bing API key. [Microsoft Services Agreement](https://www.microsoft.com/servicesagreement), [Microsoft Privacy Statement](https://privacy.microsoft.com/privacystatement).

= Google Analytics Data API (GA4 Insights, optional) =
Fetches organic traffic per page once a day. Sends your GA4 property ID and access token. [Google APIs Terms](https://developers.google.com/terms), [Google Privacy Policy](https://policies.google.com/privacy).

= Google PageSpeed Insights API (Core Web Vitals, optional) =
Measures Core Web Vitals for your public URLs weekly or on demand. Sends the page URLs being tested and your API key. [Google APIs Terms](https://developers.google.com/terms), [Google Privacy Policy](https://policies.google.com/privacy).

= IndexNow (optional) =
Notifies search engines when you publish or update content. Sends the changed URL and your IndexNow key to the engines you select: Bing ([Services Agreement](https://www.microsoft.com/servicesagreement), [Privacy](https://privacy.microsoft.com/privacystatement)), Yandex ([Terms](https://yandex.com/legal/rules/), [Privacy](https://yandex.com/legal/confidential/)) and Naver ([Terms](https://policy.naver.com/policy/service_en.html), [Privacy](https://policy.naver.com/policy/privacy_en.html)). See [indexnow.org](https://www.indexnow.org/).

= Slack incoming webhooks (Notifications, optional) =
Posts score-drop alerts and the weekly digest to the Slack webhook URL you enter. Sends post titles, URLs and scores. [Slack Terms](https://slack.com/terms-of-service), [Slack Privacy Policy](https://slack.com/trust/privacy/privacy-policy).

= OpenStreetMap and Google Maps (Local Business block, optional) =
When you show a location as a map with the Local Business block or shortcode, the visitor's browser loads the map from openstreetmap.org, which receives the visitor's IP address and the map area. The Get directions link opens Google Maps only when a visitor clicks it. Nothing is sent from your server. OpenStreetMap: [Terms](https://wiki.osmfoundation.org/wiki/Terms_of_Use), [Privacy policy](https://wiki.osmfoundation.org/wiki/Privacy_Policy). Google: [Terms](https://policies.google.com/terms), [Privacy policy](https://policies.google.com/privacy).

= Broken link checker (optional) =
Sends an HTTP HEAD request to each external URL linked from your content to check whether it still resolves. Only the linked URL is requested; no site data is sent.

== Changelog ==

= 3.8.33 =
* Fixed: on hosts with a persistent object cache (Redis, Memcached), a newly published post or page now shows in the sitemap straight away instead of up to 12 hours later.

= 3.8.32 =
* Fixed: the Noindex and Nofollow settings on the page chosen as the blog (posts) page now take effect on that page.

= 3.8.31 =
* Fixed: with a static front page, the home page appeared twice in the page sitemap. It is now listed once, with its last-modified date and images.

= 3.8.30 =
* Fixed: a default social image stored in the theme (not the Media Library) gets its width and height in the link-preview tags again, so Facebook shows it on the first share.

= 3.8.29 =
* Fixed: links shared on LinkedIn now show the page's SEO or social title instead of the bare WordPress page title (for example \"Home\"). LinkedIn reads WordPress's embed data before the Open Graph title; that data now carries the same title.

= 3.8.28 =
* Fixed: a JPEG upload whose full size the theme saved as WebP was shared as that WebP instead of a JPEG link-preview copy.

= 3.8.27 =
* Fixed: posts and pages now print og:image:type and og:image:secure_url like the blog page and archives do, and leave out an image size that is unknown.

= 3.8.26 =
* Improved: link previews show their image in more apps. Each shared image now has a light copy made for link previews: at most 1200 px wide, saved as JPEG (PNG when it has transparency), so WhatsApp, iMessage, LinkedIn and others show it even when the original is a large PNG or a WebP. The copy is made when the image is chosen and is deleted with the image.
* Fixed: preview images were announced as 1200×630 when their real size was unknown. Image tags now give the real size, or none, plus og:image:type and og:image:secure_url.

= 3.8.25 =
* Fixed: a shared link to the blog page or a post type archive (such as a list of guides or events) now shows a description and an image in Facebook, LinkedIn, X, iMessage and Slack previews, instead of a bare title. The blog page uses its own social image or featured image; archives use the default social image. The blog page's Social title and description now apply too.

= 3.8.24 =
* Improved: SoftwareApplication schema (Schema tab, type "Software") now falls back to the post title when App name is empty, as its help text promised, instead of printing nothing. It also gives the page URL and a new "Store and download pages" field (one URL per line, such as itch.io, Steam, GitHub or an app store) as sameAs, so games and apps are tied to their store listings.

= 3.8.23 =
* Fixed: publishing a post that was a draft, pending or a different post type (for example a submitted event being approved) could add an automatic redirect from the home page to that post, because an unpublished post's old address is a query URL on the home page. Automatic slug-change redirects are now only added when a published post's address changes, never from the home page or a ?p= address. Existing "Auto: slug change" rules with / as the source should be deleted under Search Intel → Redirects.

= 3.8.22 =
* Fixed: images marked as decorative (an empty alt="") no longer get alt text added from the file name. Screen readers skip decorative images; filling them in made pages noisier for people who use one. Only images with no alt attribute at all get one.
* Fixed: the canonical link now follows WordPress's own canonical URL, so the pages of a split post and views other plugins add beneath a post (such as an agent's listings page) point at themselves instead of the post.
* Fixed: archive pages of custom post types (such as /communities/) now get a canonical link, and a meta description when the post type has one.
* Fixed: robots.txt no longer lists the JSON schema map as a "Sitemap:". Search Console only accepts XML, RSS or text sitemaps there; the map is now named in a comment.
* New: developers can change the canonical URL and meta description of any page (jat_search_intel_canonical, jat_search_intel_meta_description) and add URLs to a post type's sitemap (jat_search_intel_sitemap_extra_urls).

= 3.8.21 =
* New: entities saved in the GEO tab (people, organizations, places, software) are now added to the page's structured data as schema.org "mentions", so search engines and AI assistants can read them. Before, they were only used for optional inline markup.

= 3.8.20 =
* Improved: the GEO "source citations" check also counts sources saved in the GEO tab and links to two or more outside sites, not only phrases like "according to".
* Fixed: the AI Search Readiness count of pages scoring below 50 included sections stored as their own posts that have no page of their own (such as service packages shown inside a service page).

= 3.8.19 =
* New: developers can add links kept outside the post content (a listing's website or menu in post meta) and extra post types to the broken link checker with the jat_search_intel_link_checker_links and jat_search_intel_link_checker_post_types filters.
* Fixed: the link checker no longer skips posts with an empty body before those filters run.

= 3.8.18 =
* Fixed: saving a Search Console domain property (sc-domain:example.com) through the REST API no longer erases it.
* Fixed: turning on IndexNow through the REST API now creates its key, as the settings page does.

= 3.8.17 =
* Fixed: the first-paragraph keyword check also skips short labels and taglines (under eight words) that block themes put above the opening paragraph.

= 3.8.16 =
* Fixed: the "focus keyword in first paragraph" check read the first paragraph tag even when it was empty (a subtitle line or spacer), so it failed on pages that open with the keyword. It now uses the first paragraph with text.

= 3.8.15 =
* Fixed: "Rescore all" (Tools page, REST API and WP-CLI) never saved new scores, so scores stayed at whatever they were when each page was last saved. It now saves them, and it reads the same content as saving a post does (page builders and server-rendered blocks included).

= 3.8.14 =
* Fixed: pages built from blocks that render on the server (theme or plugin blocks such as a hero, pricing table or testimonials) were analyzed as empty, so they scored near zero. Those blocks are now rendered before analysis.

= 3.8.13 =
* Fixed: sitemaps for post types and taxonomies whose names contain an underscore, digit or hyphen (such as a theme's custom places or events type) no longer return "page not found". Later pages of a large sitemap are now sitemap-post-2.xml; the old sitemap-post2.xml addresses still work.
* Fixed: after a bulk rescore, the Readiness page shows the new count of scored pages at once.

= 3.8.12 =
* New: AI agents that ask for Markdown (an Accept header of text/markdown) get the Markdown version of a post or page at its normal address. Browsers still get HTML. If a CDN caches your HTML, have it skip the cache for those requests.

= 3.8.11 =
* Fixed: saving FAQs, entities, citations or topics through the REST API (and tools that use it) stored empty items. They are now kept and cleaned the same way as in the editor.
* Improved: a featured image with alt text now counts toward the SEO image check, since the theme shows it with the post.

= 3.8.10 =
* Fixed: redirects from addresses with emoji or other non-English characters (such as /%F0%9F%A7%A0-post/) never matched, because the request path was cleaned in a way that deleted the encoded characters. The 404 log now records those addresses correctly too.

= 3.8.9 =
* Fixed: Core Web Vitals showed 0 for every SEO, AEO and GEO score.
* Fixed: Core Web Vitals scans picked random pages and timed out, so some pages were never tested. Never-tested pages now go first, then the oldest results; a manual run tests 8 pages and a daily run tests 15. Sections of other pages and noindex pages are skipped.

= 3.8.8 =
* Fixed: Index Status checked items whose address is a section of another page (such as a service tier at /service/care-plans/#guardian). Google can never index those on their own, so they showed as "URL is unknown to Google" and used up the daily inspection quota. They are now skipped.

= 3.8.7 =
* Fixed: the Broken Link Checker reported links as broken when a site refuses automated requests (403, 429). Those are now treated as blocked, not broken, and a refused HEAD request is retried with GET.

= 3.8.6 =
* Fixed: the Broken Link Checker never ran, because nothing scheduled its hourly scan. It now starts when the module is on, and has a "Check the next 25 posts now" button.

= 3.8.5 =
* Fixed: Rank Tracker keywords with capital letters ("WordPress developer") never matched, because Search Console reports queries in lower case.
* Fixed: after changing the tracked keywords, Fetch now loads the last 28 days for them instead of only the last few days.

= 3.8.4 =
* Fixed: changing which post types or taxonomies are in the sitemap shows at once, instead of after the 12-hour sitemap cache expires.
* Fixed: saving settings through the REST API no longer breaks title templates (%category%, %date% and %search_query% lost characters) or nested settings such as Local Business locations.
* Fixed: migrating from All in One SEO no longer turns a date or author archive title into just the site name when it uses a variable this plugin doesn't have; #archive_date is now converted.

= 3.8.3 =
* Fixed: sitemaps no longer list items whose link points to a section of another page (such as /services/security/#audit), which repeated the parent page.

= 3.8.2 =
* Fixed: on Tools, Reports and other grouped pages the tab bar pushed the page down by the height of the admin menu, leaving a large blank space.
* Fixed: when the site represents a person who also writes its posts, schema now describes one Person instead of two (author and publisher share an @id).
* Fixed: migration adds the post types that had SEO data (for example Projects or Services) to the analysed post types and the XML sitemap, as the other plugin did.

= 3.8.1 =
* Fixed: a static front page uses its own SEO title (as All in One SEO does) instead of the homepage title template.
* Fixed: the blog page chosen as "Posts page" uses its own SEO title, description and canonical URL.

= 3.8.0 =
A clean move from All in One SEO, and the features it sells, free.

* Fixed: migration from All in One SEO now reads its own tables (titles, descriptions, focus keyphrases, robots, social fields, canonical URLs, primary category and custom schema), category and tag SEO, site-wide settings and redirects. Title variables are converted for every source plugin, all public post types are included, and the Migration page's buttons work.
* Fixed: the Redirects page buttons (Add, Edit, Delete, Import, Export, Clear log) now work, and sources with percent-encoded characters are kept intact.
* New: redirect chain and loop warnings; a rule that would create a loop is refused. Export redirects as .htaccess or Nginx rules.
* New: Schema tab with Recipe, Event, Review, Course, SoftwareApplication, JobPosting and VideoObject forms.
* New: headline analyzer, SEO fields in Quick Edit and Bulk Edit, and an admin bar SEO menu.
* New: RSS sitemap, page-builder content support (Elementor, Bricks, Beaver Builder, Divi, WPBakery), image title and alt text rules, and a Missing alt text screen.
* New: Local Business block and shortcode for address, opening hours and map.
* New: SEO head in the REST API for headless sites.
* Fixed: title templates keep `%category%` and other variables when saved.
* Security: explicit capability checks on every admin page.

= 3.7.0 =
Measure what works, and score what matters.

* New: AI Citations report. Track up to 25 questions; ChatGPT, Claude, Gemini and Perplexity are asked them weekly (or on demand) with web search on, using your own API keys. See whether each one cited your site, read it without citing it or named you, which of your pages it cited, and which sites it cited instead. Also `wp jat-search-intel citations` and a get-ai-citations ability for AI agents.
* New: pages that an AI engine stopped citing move to the top of the Refresh Queue, with the questions they lost.
* Changed: the GEO health score now follows research on what AI engines cite. 100 points across AI search crawler access, Google and Bing indexing, freshness, answer-ready content, entity and author details, and structured data. llms.txt and the AI meta tags stay available but earn no points, because no major AI engine has said it uses them. Scores will change; the breakdown explains each factor.
* Tests: a CI cleanup removed duplicate tests and runs the same checks in 6 jobs instead of 15.

= 3.6.0 =
Every feature other SEO plugins sell in their premium plans, free.

* New: SEO fields for categories, tags, product categories and any public taxonomy (title, description, canonical, robots, social), with noindex for whole taxonomies and for empty terms (on by default). Noindexed terms leave the sitemap.
* New: primary category for posts and products, used in permalinks, breadcrumbs, Article schema and the %category% variable.
* New: title templates for taxonomy archives and other archives.
* New: site verification codes for Google, Bing, Yandex, Baidu, Pinterest, Facebook, Naver and Seznam.
* New: crawl cleanup options (shortlinks, RSD, WLW manifest, generator, emoji, oEmbed and REST links, X-Pingback, comment and extra feeds, spam searches).
* New: Index Status report from the Search Console URL Inspection API, with a daily check, a manual batch and `wp jat-search-intel index-status`.
* New: SEO change history with one-click restore, in the block editor and the classic meta box; robots.txt keeps its last 10 versions.
* New: freeze the last-modified date for small edits.
* New: reading time in Article schema, X cards and a shortcode; X cards show the author too.
* New: schema map for AI agents, listed in robots.txt.
* New: Google Preferred Sources button as a block, a shortcode or after posts.
* New: Facebook and X share previews in the block editor.
* New: AI providers Google Gemini, Mistral, DeepSeek, OpenRouter and any OpenAI-compatible endpoint; OpenAI now defaults to gpt-5-mini and Anthropic to Claude Haiku 5.5.
* New: abilities for AI agents to read and update term SEO, list posts that need attention, read index status and list or create redirects.
* Fix: a provider's status message on the AI settings page could render HTML from the API response.

= 3.5.0 =
* First WordPress.org release, renamed JAT Search Intel. Every identifier now uses `jat-search-intel` / `jat_search_intel` (main file, text domain, options, post meta, tables, REST namespace `jat-search-intel/v1`, blocks, WP-CLI `wp jat-search-intel`).
* Added: automatic one-time migration from the earlier GitHub edition (settings, post meta, redirects, 404 log, roles, block markup); old FAQ/HowTo shortcodes keep working.
* Added: connected JSON-LD @graph (single script, nodes linked by @id).
* Added: custom JSON-LD per post.
* Added: Google News sitemap, video sitemap and HTML sitemap shortcode.
* Added: automatic image alt text, attachment-page redirects and RSS feed backlinks.
* Added: Markdown copies of posts for AI agents, per-bot AI crawler rules in robots.txt, and AI crawler / AI referral analytics.
* Added: Rank Tracker using your Search Console data via a Google service account.
* Added: AI Search Readiness audit with a 0–100 score and one-click fixes, plus a Refresh Queue of stale pages weighted by Search Console clicks.
* Added: WordPress Abilities API support (6.9+) for AI agents and MCP: search content, get page SEO data, analyse a post, update SEO fields, run the readiness audit, get the refresh queue.
* Added: Content Signals (search / ai-input / ai-train) in robots.txt, and crawler presets (allow all, block training only, block AI).
* Changed: IndexNow generates its key automatically and pings on publish, update and unpublish.
* Fixed: the REST analyse endpoint and the background rescore job never saved scores.
* Added: three-step setup wizard, offered through a dismissible notice after activation.
* Added: Passage Coach panel in the block editor, with one-click answer-paragraph and comparison-table inserts.
* Added: Page Performance report combining Search Console, AI referrals, AI crawler visits, GEO score and freshness per page.
* Changed: the admin menu is now six entries (Readiness, Settings, Redirects, AI Search, Reports, Tools) with tabs; every page keeps its address.
* Fixed: canonical URL, social title/description/image, audience, key topics and fact-check date set in the block-editor sidebar were saved under keys the front end never read. They are now kept in sync both ways and existing values are copied over on update. X (Twitter) title, description and image overrides are now used.
* Removed: CSP headers, Subresource Integrity, white-label branding and the MainWP reporter (outside the scope of an SEO plugin).
* Security: password-protected posts no longer leak their text through auto-generated descriptions, Open Graph/X tags, schema descriptions, FAQ/HowTo/speakable schema, the direct-answer tag or the ai-summary tag. An explicitly written meta description is still used.
* Fixed: under the block editor, the classic meta box re-saved every field with its page-load values after the sidebar saved, undoing sidebar changes (for example canonical URL and noindex). It now saves only fields changed in the meta box.
* Fixed: the setup wizard no longer replaces individually set crawler rules, and recommends blocking AI training only on a fresh install.
* Fixed: Readiness no longer offers a fix on a passing check; the Refresh Queue now also considers high-traffic pages beyond the 300 oldest; Readiness and Page Performance stay fast on large sites.
* Security: REST SEO data is only public for published, non-password-protected posts; focus keywords and scores now require edit access; duplicate /scores and /meta route registrations from the headless module were removed.
* Fixed: settings pages no longer round-trip every option through hidden fields, which could flatten nested settings and stop some checkboxes from being turned off.
* Fixed: title templates using %category%, %search_query% or %sep% were mangled when saved.
* Fixed: entity markup repeated the rest of the sentence after each marked entity, and matched names inside longer words.
* Fixed: the %tag% title variable and breadcrumbs could cause a fatal error when WordPress returned an error instead of terms.
* Fixed: the Table of Contents block preview could fail on WordPress 6.5+ where heading text is stored as rich text.
* Fixed: saving the SEO page switched off breadcrumb and site-search schema (they now have their own controls).
* Fixed: /sitemap.xml could be redirected to /sitemap.xml/; robots.txt now lists your sitemaps.
* Fixed: a second robots meta tag was printed next to WordPress's own; directives are now merged into one.
* Performance: home, post, page and archive views run no extra database queries with the plugin active (measured on WordPress 7.1).
* Performance: page views now load 18 plugin files instead of 25 (about 250 KB instead of 405 KB) and register 72 hook callbacks instead of 163; the REST API, Passage Coach, abilities, content analyzer and AI Optimizer are built only when a REST request, save or admin screen needs them, and editor meta fields are registered for REST requests only. Extra memory per page view fell from about 0.37 MB to 0.12 MB.
* Security: editor meta fields can only be changed by users who can edit that particular post.
* Changed: llms.txt now lists articles as well as pages, skips noindex content and is cached; llms-full.txt includes the full Markdown of each item.
* Removed the GitHub self-updater; updates now come from WordPress.org.
* Performance: disabled modules are no longer loaded on each request.
* Performance: redirect matching uses a cached index, so pages without a redirect run no redirect queries.
* Performance: growing logs and queues are no longer autoloaded.
* Added: opt-in "Delete all plugin data" setting; uninstall now also removes tables, cron events and roles.
* Added: scheduled tasks are cleared on deactivation, and database tables are created on update as well as activation.
* Changed: a static llms.txt left by another plugin is no longer deleted automatically; you get a notice with a one-click remove button.
* Changed: llms.txt no longer publishes the admin email address (use the `jat_search_intel_llms_contact_email` filter to add a contact).
* Changed: AI-crawler access log stores hashed IPs only.
* Removed: Google/Bing sitemap ping (both endpoints were retired; use IndexNow).
* Fixed: background queue cron interval was never registered.
* Fixed: llms.txt access tracking never ran.
* Security and coding standards: prepared queries throughout, input sanitization, output escaping and translator comments.

= 3.4.2 =
* Fixed: saving settings on one tab no longer wipes toggles on other tabs.
* Fixed: reports show the correct plugin version.

= 3.4.1 =
* FAQ generation works with any configured AI provider; Generate FAQs and AI Suggest Edits buttons; score history table.

= 3.4.0 =
* AI Optimizer: score-driven content suggestions with one-click apply.

Earlier releases are listed in CHANGELOG.md in the development repository.

== Upgrade Notice ==

= 3.8.33 =
New posts reach the sitemap at once on sites with an object cache.

= 3.8.32 =
The blog page's Noindex setting now works.

= 3.8.31 =
The home page is listed once in the sitemap.

= 3.8.30 =
Link previews from a theme image include its size again.

= 3.8.29 =
LinkedIn link previews use the SEO title instead of the bare page title.

= 3.8.28 =
Link previews also get a JPEG copy when the theme stored the image as WebP.

= 3.8.27 =
Posts and pages print the preview image's type, as archives already do.

= 3.8.26 =
Shared links show their preview image in more apps: light JPEG copies of large PNG and WebP images.

= 3.8.25 =
Link previews for the blog page and archives now include a description and an image.

= 3.8.24 =
Software schema now uses the post title when App name is empty and can link store and download pages (itch.io, Steam, app stores).

= 3.8.23 =
Stops approving a pending post from redirecting the home page to it. Check Redirects for automatic rules from / and delete them.

= 3.8.22 =
Keeps decorative images decorative, fixes canonicals for split posts and custom post-type archives, and stops listing the JSON schema map as a sitemap.

= 3.8.21 =
Saved entities now appear in structured data as schema.org mentions.

= 3.8.20 =
Citations by link now count for GEO, and the readiness count skips sections without their own page. Rescore after updating.

= 3.8.19 =
The broken link checker can now check links stored outside post content.

= 3.8.18 =
Search Console domain properties and IndexNow can now be set through the REST API.

= 3.8.17 =
The first-paragraph keyword check now skips short labels above the opening paragraph.

= 3.8.16 =
The first-paragraph keyword check now skips empty paragraphs.

= 3.8.15 =
Rescoring all pages now saves the new scores. Run it once after updating.

= 3.8.14 =
Pages built from server-rendered blocks are now scored on their real content.

= 3.8.13 =
Sitemaps for custom post types with an underscore in their name now load.

= 3.8.12 =
Pages answer AI agents in Markdown when they ask for it.

= 3.8.11 =
FAQs, entities and citations can now be saved through the REST API.

= 3.8.10 =
Redirects now work for addresses with emoji or accented characters.

= 3.8.9 =
Core Web Vitals now covers every page and shows the right scores.

= 3.8.8 =
Index Status no longer reports page sections as unindexed.

= 3.8.7 =
Fewer false alarms from the Broken Link Checker.

= 3.8.6 =
The Broken Link Checker now actually runs.

= 3.8.5 =
Rank Tracker keywords now match regardless of capitals, and a changed keyword list loads 28 days of history.

= 3.8.4 =
Sitemap setting changes now show at once.

= 3.8.3 =
Sitemaps skip links to a section of another page.

= 3.8.2 =
Fixes a blank gap on grouped admin pages, a duplicate Person in schema, and migrated post types missing from the sitemap.

= 3.8.1 =
A static front page now shows its own SEO title.

= 3.8.0 =
Fixes migration from All in One SEO and the Redirects page buttons, and adds schema forms, Quick Edit, redirect chain checks, a Local Business block and headless REST output.

= 3.7.0 =
Adds the AI Citations report and an evidence-based GEO health score. Your GEO score will change: llms.txt and AI meta tags no longer earn points.

= 3.6.0 =
Adds category and tag SEO, primary category, crawl cleanup, Google index status, SEO change history, a schema map for AI agents and five more AI providers. Empty category and tag archives are now noindexed by default (Settings > SEO).

= 3.5.0 =
First WordPress.org release. Existing data from the GitHub edition (Search Intel) is migrated automatically. Adds an AI Search Readiness audit, rank tracking, AI agent abilities, news/video sitemaps, Markdown for AI agents and AI crawler controls.
