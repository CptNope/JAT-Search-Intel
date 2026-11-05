# FAQ and Troubleshooting

## Questions

**Can I use it alongside another SEO plugin?**
It's designed to be your only SEO plugin. Two SEO plugins print duplicate meta tags and schema, and JAT Search Intel shows a notice if it detects another one. Import your data with **Tools → Migration** first, then deactivate the old plugin.

**Will it slow my site down?**
No. On a normal page view the plugin runs the same number of database queries as with it switched off, and it loads no JavaScript or CSS on the public site. Modules you leave off are never loaded.

**Does it send my data anywhere?**
Not by default. Outside requests happen only for integrations you switch on and configure. See [Privacy and External Services](Privacy-and-External-Services).

**What happens to my data if I delete the plugin?**
It is kept. To remove everything, tick **Delete all plugin data** under **Settings → General → Data Management** before deleting.

**What are AEO and GEO?**
AEO (Answer Engine Optimization) targets featured snippets, voice answers and People Also Ask. GEO (Generative Engine Optimization) targets AI answer engines such as ChatGPT, Perplexity, Google AI Overviews and Copilot.

## Troubleshooting

**`/sitemap.xml`, `/llms.txt` or `/my-post.md` returns a 404**
Go to **Settings → Permalinks** in WordPress and click **Save Changes** to refresh rewrite rules. Then check that the feature is switched on: sitemaps under **Settings → Sitemap**, llms.txt and Markdown copies under **AI Search**.

**My robots.txt changes don't show up**
A physical `robots.txt` file in your site root overrides WordPress's virtual one. Delete or rename it, or copy the plugin's rules into it. Readiness flags this.

**Scores show 0 or don't update**
Scores are calculated when a post is saved. Re-run them for one post from the **Analysis** tab in the editor, or for everything with **Tools → Bulk Tools → Rescore** or `wp jat-search-intel rescore --all`.

**Passage Coach doesn't appear**
It needs the block editor and the content analyzer (**Settings → General**). It appears in the **Post** tab of the editor's settings sidebar.

**Duplicate meta tags or schema**
Another SEO plugin, or a theme with built-in SEO, is printing its own. Deactivate the other plugin or turn off the theme's SEO features.

**AI assistants don't cite my site**
Open **Readiness**. The most common causes are robots.txt blocking the AI search crawlers (OAI-SearchBot, Claude-SearchBot, PerplexityBot), a site-wide `noindex`, or missing meta descriptions and author details.

## Still stuck?

[Open an issue](https://github.com/CptNope/JAT-Search-Intel/issues/new/choose) with your plugin, WordPress and PHP versions and any errors from `wp-content/debug.log`.
