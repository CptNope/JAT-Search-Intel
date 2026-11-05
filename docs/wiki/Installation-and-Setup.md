# Installation and Setup

## Install

**From a release zip**

1. Download `jat-search-intel.zip` from the [latest release](https://github.com/CptNope/JAT-Search-Intel/releases/latest). Each release also has a `.sha256` file if you want to check the download.
2. In WordPress, go to **Plugins → Add New → Upload Plugin**, choose the zip and click **Install Now**.
3. Click **Activate**.

**From WordPress.org**: once the plugin is listed, search for "JAT Search Intel" under **Plugins → Add New**.

## The setup wizard

After activation, a notice offers **Run setup**. You can also open it any time at **JAT Search Intel → Tools → Setup Wizard**. It has three steps:

1. **Who you are**: organization or person, name, logo and official profiles. These feed the Organization / Person node of the schema graph and the brand checks in Readiness.
2. **Search engines**: whether the site may be indexed, the XML sitemap and IndexNow.
3. **AI assistants**: choose how AI crawlers are treated:
   - **Allow AI search, block training** (recommended for a new site)
   - **Allow everything**
   - **Block AI crawlers**
   - **Keep my current crawler rules** (shown when your existing rules don't match a preset; nothing is changed)

The wizard ends on your **Readiness** score.

## After setup

- Open **JAT Search Intel → Readiness** and click **Fix** next to anything the plugin can fix for you. See [AI Search](AI-Search#readiness).
- Edit any post: the **JAT Search Intel** sidebar shows live scores and fields, and **Passage Coach** in the Post tab lists the passages to improve. See [Editor Tools](Editor-Tools).
- Switch on optional modules under **Settings → General → Module Toggles**. Modules you leave off are never loaded.

## The admin menu

Everything lives under six entries. Pages inside each one appear as tabs.

| Entry | Tabs |
|---|---|
| **Readiness** | AI Search Readiness score and Refresh Queue |
| **Settings** | General (incl. module toggles and data management), SEO, AEO, GEO, Sitemap, Social, Robots.txt, AI Engine |
| **Redirects** | Redirects and 404 log, Broken Links |
| **AI Search** | AI crawler rules and Content Signals, AI Visibility, Citation Graph, Authority Map |
| **Reports** | Page Performance, Rank Tracker, Analytics, GA4, Core Web Vitals, Content Decay, Cannibalization, Scheduled Reports |
| **Tools** | Bulk Tools, Bulk Editor, Internal Linking, Migration, Setup Wizard |

Tabs for optional modules only appear once the module is switched on. Add-ons can change the grouping with the [`jat_search_intel_admin_menu_groups`](Developer-Hooks#jat_search_intel_admin_menu_groups) filter.

## Switching from another SEO plugin

Use **Tools → Migration** (or `wp jat-search-intel migrate`, see [WP-CLI](WP-CLI)) to import titles, descriptions and focus keywords from **Yoast SEO, Rank Math, All in One SEO, SEOPress or The SEO Framework**, then deactivate the old plugin. Running two SEO plugins prints duplicate meta tags and schema, and JAT Search Intel shows a notice if it detects one.

## Upgrading from "Search Intel"

Versions before 3.5.0 were called Search Intel and used `search-intel` / `search_intel` names. The first time 3.5.0 runs, it moves your settings, post meta, custom tables and scheduled events to the new `jat_search_intel` names automatically. Nothing needs to be done by hand.

## Uninstalling

Deleting the plugin keeps your data, so nothing is lost if you reinstall. To remove everything, tick **Delete all plugin data** under **Settings → General → Data Management** before deleting.
