# Editor Tools

## The sidebar

In the block editor, open the **JAT Search Intel** panel from the toolbar. It has four tabs:

| Tab | Fields |
|---|---|
| **SEO** | title, description, focus keyword, canonical URL, robots, social overrides and a live SERP preview |
| **AEO** | direct answer and schema type override |
| **GEO** | AI summary, key topics, content type, intent, audience, expertise and fact-check date |
| **Analysis** | SEO, AEO and GEO score rings with recommendations, and a button to re-run the analysis |

The **Post** tab of the editor's settings sidebar also has these panels:

| Panel | What it does |
|---|---|
| **Primary category** | Choose the main category when a post has more than one (shown only then) |
| **Reading time & last modified** | Estimated reading time, and **Don't update the last-modified date** for small fixes |
| **Social preview** | How the post looks when shared on Facebook, LinkedIn and X |
| **SEO change history** | Recent changes to the SEO fields, who made them, and **Restore previous values** |

The classic editor gets the same fields in a meta box below the content. Both write to the same post meta, so a value set in one place is used everywhere. When you save from the block editor, the meta box only writes the fields you actually changed in it.

## Passage Coach

Search results and AI answers quote single passages, not whole pages. **Passage Coach** (in the **Post** tab of the editor's settings sidebar) checks each block of the post as you write and points to the exact passage to improve:

| Finding | Why it matters |
|---|---|
| The intro warms up instead of answering | Engines quote the first direct answer they find |
| A question heading with no direct answer under it | The answer should be in the first sentence after the question |
| A statistic with no source | Unsourced numbers are less likely to be cited |
| A paragraph too long to quote | Answer passages are usually 40–60 words |
| A comparison with no table | Tables are easy for engines to extract |
| A reference to an out-of-date year | Signals stale content |
| An image with no alt text | Invisible to search and screen readers |
| A heading that skips a level | Breaks the outline engines use to understand the page |
| No AI summary | Lets you write one right in the panel |

**Show passage** selects and scrolls to the block. For a missing answer or comparison table, Passage Coach can insert a starter paragraph or table for you. It runs on your own server and needs no AI service.

## Blocks

| Block | Output |
|---|---|
| **FAQ** | Question and answer pairs with FAQPage schema |
| **How To** | Steps with optional images and HowTo schema |
| **Table of Contents** | Built from the post's headings, with a chosen maximum depth |
| **Breadcrumbs** | Theme-aware trail with BreadcrumbList schema |
| **Answer Box** | A short, highlighted direct answer for featured snippets |
| **Citation** | An inline source with CreativeWork schema |
| **Preferred Source on Google** | A button that lets readers add the site as a preferred source in Google, with an optional custom label |

## Shortcodes

- `[jat_search_intel_html_sitemap post_types="page,post" limit="500"]`: an HTML sitemap for visitors.
- `[jat_search_intel_reading_time before="" after="read"]`: estimated reading time of the current post, for example "4 min read".
- `[jat_search_intel_preferred_source label=""]`: the Preferred Source on Google button.
- `[jat_search_intel_faq]` and `[jat_search_intel_howto]` produce the same markup and schema as the FAQ and How To blocks, for the classic editor. The pre-3.5 names `[search_intel_faq]` and `[search_intel_howto]` still work.
