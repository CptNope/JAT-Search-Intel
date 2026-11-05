# AI Search

Tools for being found, read, trusted and cited by search engines and AI assistants.

## Readiness

**JAT Search Intel → Readiness** answers one question: *can Google, Bing and AI assistants find, read, trust and cite this site?* It gives a 0–100 score from these checks:

- **Access**: indexing allowed, robots.txt not blocking Googlebot, Bingbot or the AI search crawlers, and no conflicting SEO plugin
- **Basics**: HTTPS, pretty permalinks, XML sitemap
- **Discovery**: IndexNow, llms.txt, Markdown copies
- **Understanding**: structured data, brand profiles and author bios
- **Content**: meta descriptions and content freshness

Anything the plugin controls gets a **Fix** button. A fix only changes JAT Search Intel's own settings, never your theme or other plugins.

### Refresh Queue

Beside the score, the Refresh Queue lists the pages that have gone longest without an update. When Search Console is connected, it is weighted by real clicks, so the pages worth refreshing first come first.

## AI crawler rules

**AI Search** lets you allow or block 21 AI crawlers in robots.txt, grouped by what they do:

| Purpose | Crawlers |
|---|---|
| **Training**: collect content to train models | GPTBot, ClaudeBot, GoogleOther, meta-externalagent, Amazonbot, Bytespider, CCBot |
| **AI search**: index pages for AI answers that link back | OAI-SearchBot, Claude-SearchBot, PerplexityBot, Amzn-SearchBot, meta-webindexer, DuckAssistBot |
| **User fetch**: open a page because a person asked an assistant about it | ChatGPT-User, Claude-User, Perplexity-User, Amzn-User, MistralAI-User, meta-externalfetcher |
| **Opt-out signals**: tokens that tell a company not to use your content for AI | Google-Extended, Applebot-Extended |

One-click presets:

- **Allow AI search, block training** (recommended): AI answers can cite and link you, while the training crawlers and opt-out tokens are blocked.
- **Allow everything**
- **Block all AI crawlers**

Readiness warns if your rules block the AI *search* crawlers, because that removes you from AI answers.

## Content Signals

Content Signals add a line to the `User-agent: *` group of robots.txt that states your preferences separately for search, AI answers and AI training:

```
Content-Signal: search=yes, ai-input=yes, ai-train=no
```

## llms.txt

- `/llms.txt`: a short, machine-readable summary of the site (name, description, key pages and sections) following the [llms.txt proposal](https://llmstxt.org/).
- `/llms-full.txt`: the same with fuller page content.

Change the output with the [`jat_search_intel_llms_txt`](Developer-Hooks#jat_search_intel_llms_txt) filter, or add a contact address with [`jat_search_intel_llms_contact_email`](Developer-Hooks#jat_search_intel_llms_contact_email).

## Markdown copies

Every public post and page is also available as clean Markdown at its URL plus `.md` (for example `/my-post.md`). The page links to it with `<link rel="alternate" type="text/markdown">`, and llms.txt lists it. Password-protected, private and `noindex` content is never exposed. Change the output with [`jat_search_intel_markdown`](Developer-Hooks#jat_search_intel_markdown).

## IndexNow

IndexNow tells Bing about new and updated pages within minutes. Bing powers ChatGPT search and Copilot. The key is generated and served for you, so there's nothing to set up. IndexNow is opt-in because it sends your URLs to Bing (and optionally Yandex and Naver).

## AI traffic

**AI Search → AI Visibility** shows AI crawler visits and the visitors arriving from ChatGPT, Perplexity, Claude, Gemini, Copilot and other assistants. **Reports → Page Performance** puts that next to Search Console clicks, GEO score and freshness for every page, with a suggested next step for each.

## AI agents and MCP

On WordPress 6.9 and later, the plugin registers abilities with the WordPress Abilities API. AI agents connected through the REST API or the [MCP Adapter](https://github.com/WordPress/mcp-adapter) can use them. See [REST API and Abilities](REST-API-and-Abilities#abilities).
