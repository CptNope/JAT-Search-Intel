=== JAT Search Intel ===
Contributors: cptnope
Tags: seo, schema, llms-txt, ai-search, redirects
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SEO, answer-engine and AI-search optimization: meta tags, schema, sitemaps, llms.txt, redirects and live SEO/AEO/GEO content scores.

== Description ==

JAT Search Intel covers the three pillars of modern search visibility in one plugin: classic SEO, Answer Engine Optimization for featured snippets and voice results, and Generative Engine Optimization for AI answer engines.

Everything runs on your own site. No account, no tracking and no outside requests unless you switch on an optional integration (see External services below).

**SEO — Search Engine Optimization**
Traditional search optimization with a modern implementation.

* Custom meta titles and descriptions with SERP preview
* Open Graph tags for Facebook, LinkedIn, and social sharing
* Twitter Card tags with per-post configuration
* Canonical URL management
* Robots meta directives (noindex, nofollow) per post
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
* llms.txt endpoint — machine-readable site summary following the llms.txt specification
* Entity markup — define people, organizations, places, and products with Schema.org microdata
* E-E-A-T author signals — credentials, expertise areas, years of experience
* Content freshness meta tags — signal recency to AI crawlers
* Content type detection — article, how-to, FAQ, comparison, review, guide
* Markdown copy of every public post for AI agents (`/post-slug.md`), linked from the page and from llms.txt
* AI crawler access: allow or block 21 AI bots individually in robots.txt, grouped by purpose (training, AI search, live user fetch)
* AI traffic analytics: AI crawler visits and visitors arriving from ChatGPT, Perplexity, Claude, Gemini, Copilot and more
* Per-post AI crawl directives
* Fact-check date tracking
* Key topics extraction and semantic keyword signals

**Unified Content Analyzer**
Real-time scoring dashboard in the post editor with separate SEO, AEO, and GEO scores plus actionable recommendations.

**REST API**
Full REST API under the `jat-search-intel/v1` namespace with toggleable endpoints:

* `GET /scores/{id}` — SEO, AEO, GEO, readability scores
* `GET /geo/{id}` — full GEO metadata
* `GET/PUT /meta/{id}` — read/write SEO meta fields
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

**Optional modules**
Turn on only what you need under Settings > General > Module Toggles: redirect manager with 404 log, IndexNow, robots.txt editor, broken link checker, internal linking suggestions, Local Business and WooCommerce schema, multilingual hreflang (WPML/Polylang), role-based access, white-label branding, AI writing assistance, content decay and keyword cannibalization reports, Core Web Vitals, GA4 insights and scheduled reports. Disabled modules are not loaded at all.

== Installation ==

1. In WordPress, go to Plugins > Add New, search for "JAT Search Intel", then install and activate it. (Or upload the `jat-search-intel` folder to `/wp-content/plugins/`.)
2. Open **JAT Search Intel > General** to set your site identity and choose which modules to enable.
3. Review the SEO, AEO and GEO tabs, and add your social profiles under **JAT Search Intel > Social**.
4. Edit any post or page: the JAT Search Intel sidebar panel shows live scores and fields.

If you are switching from another SEO plugin, use **JAT Search Intel > Migration** to import titles, descriptions and focus keywords from Yoast SEO, Rank Math, All in One SEO, SEOPress or The SEO Framework, then deactivate the old plugin to avoid duplicate meta tags.

== Frequently Asked Questions ==

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

= Ollama (AI Engine, optional) =
If you choose Ollama, requests go to the endpoint you configure (by default a server on your own machine at localhost). No third party is involved unless you point it at one.

= Google OAuth and Search Console API (Rank Tracker, optional) =
After you paste a Google service account key, the plugin requests an access token from Google's OAuth endpoint (oauth2.googleapis.com) once an hour at most, then fetches search performance for your tracked keywords from the Search Console API once a day or when you click Fetch now. Sends a token request signed with your key, your Search Console property and the keywords to look up. [Google APIs Terms](https://developers.google.com/terms), [Google Privacy Policy](https://policies.google.com/privacy).

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

= Broken link checker (optional) =
Sends an HTTP HEAD request to each external URL linked from your content to check whether it still resolves. Only the linked URL is requested; no site data is sent.

== Changelog ==

= 3.5.0 =
* First WordPress.org release, renamed JAT Search Intel. Every identifier now uses `jat-search-intel` / `jat_search_intel` (main file, text domain, options, post meta, tables, REST namespace `jat-search-intel/v1`, blocks, WP-CLI `wp jat-search-intel`).
* Added: automatic one-time migration from the earlier GitHub edition (settings, post meta, redirects, 404 log, roles, block markup); old FAQ/HowTo shortcodes keep working.
* Added: connected JSON-LD @graph (single script, nodes linked by @id).
* Added: custom JSON-LD per post.
* Added: Google News sitemap, video sitemap and HTML sitemap shortcode.
* Added: automatic image alt text, attachment-page redirects and RSS feed backlinks.
* Added: Markdown copies of posts for AI agents, per-bot AI crawler rules in robots.txt, and AI crawler / AI referral analytics.
* Added: Rank Tracker using your Search Console data via a Google service account.
* Fixed: settings pages no longer round-trip every option through hidden fields, which could flatten nested settings and stop some checkboxes from being turned off.
* Fixed: title templates using %category%, %search_query% or %sep% were mangled when saved.
* Fixed: saving the SEO page switched off breadcrumb and site-search schema (they now have their own controls).
* Fixed: /sitemap.xml could be redirected to /sitemap.xml/; robots.txt now lists your sitemaps.
* Fixed: a second robots meta tag was printed next to WordPress's own; directives are now merged into one.
* Performance: home, post, page and archive views run no extra database queries with the plugin active (measured on WordPress 7.1).
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

= 3.5.0 =
First WordPress.org release. Existing data from the GitHub edition (Search Intel) is migrated automatically. Adds rank tracking, news/video sitemaps, Markdown for AI agents and AI crawler controls.
