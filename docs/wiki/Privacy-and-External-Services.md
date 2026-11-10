# Privacy and External Services

JAT Search Intel makes **no outside requests by default**. It adds no tracking, needs no account, and stores everything in your own database.

Each service below is contacted only after you switch on the related module and enter your own credentials, and only from your server.

| Service | Module | What is sent | When |
|---|---|---|---|
| OpenAI API | AI Engine | post title, content excerpt and the task instruction, with your API key | when you click an AI button or run a bulk AI task |
| Anthropic Claude API | AI Engine | the same as above | the same as above |
| Google Gemini, Mistral, DeepSeek or OpenRouter | AI Engine | the same as above | the same as above |
| OpenAI-compatible endpoint | AI Engine | the same, to the URL you enter | the same as above |
| Ollama | AI Engine | the same, to the endpoint you configure (your own machine by default) | the same as above |
| Google OAuth and Search Console API | Rank Tracker, Refresh Queue, Page Performance | a token request signed with your service-account key, your property and keywords | at most hourly for the token; daily or on "Fetch now" for data |
| OpenAI, Anthropic, Google Gemini and Perplexity, with web search | AI Citations | each tracked question, with your API key | weekly if switched on, or when you click Check now |
| Google Search Console URL Inspection API | Index Status | each page URL and your Search Console property | daily (up to 200 pages) if switched on, or when you click Check now |
| Bing Webmaster Tools API | AI Visibility | your site URL and Bing API key | when the dashboard refreshes |
| Google Analytics Data API | GA4 Insights | your GA4 property ID and access token | daily |
| Google PageSpeed Insights API | Core Web Vitals | the page URLs being tested and your API key | weekly or on demand |
| IndexNow (Bing, Yandex, Naver) | IndexNow | the changed URL and your IndexNow key | when you publish or update content |
| Slack incoming webhook | Notifications | post titles, URLs and scores | score-drop alerts and the weekly digest |
| Linked websites | Broken link checker | an HTTP `HEAD` request to each external link (no site data) | when links are checked |

The **Preferred Source on Google** button is only a link to google.com; nothing is sent unless a visitor clicks it.

Each provider's own terms and privacy policy apply. Links to them are in the plugin's `readme.txt` under **External services**.

## What the plugin stores

- Settings in the `jat_search_intel_settings` option
- Per-post SEO fields, scores, SEO change history and index status in post meta (`_jat_search_intel_*`)
- Category and tag SEO fields in term meta (`_jat_search_intel_*`)
- Redirects and the 404 log in their own database tables
- AI crawler visits and AI-assistant referrals (for AI Visibility). Visitor IP addresses are stored only as salted hashes, never in raw form, and the same applies to the 404 log.

Deleting the plugin keeps this data unless you tick **Delete all plugin data** first.
