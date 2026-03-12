=== Search Intel ===
Contributors: searchintel
Tags: seo, aeo, geo, schema, structured-data, ai-optimization, llms, featured-snippets, voice-search
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
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

= 1.0.0 =
Initial release of Search Intel.
