# Features & configuration

Open the extension's settings from *Administration → Extensions → FoF SEO*. The
page is organised into a few sections, reachable from the menu at the top:

- **Health check** — an audit of your SEO setup (see below).
- **SEO settings** — the main configuration fields.
- **Sitemap information** — guidance on installing [fof/sitemap](sitemap-and-robots.md).
- **Search engine information** — a guide to submitting your site to Google, Bing, Yandex and Yahoo.
- **Set up SSL** — why a secure connection matters and how to enable it.

## Health check

The health check lists each SEO technique and shows whether it passes. Items that
need attention link straight to the relevant setting. It checks, among other
things, that you have a forum description and keywords, a secure (HTTPS)
connection, a social-media image, a sitemap and robots.txt
([via fof/sitemap](sitemap-and-robots.md)), and that you've registered with search
engines.

The dashboard widget will also prompt you to re-review your settings every couple
of months.

## SEO settings

| Setting | What it controls |
| --- | --- |
| **Forum description** | The site-wide `description` / `og:description` / `twitter:description`. (Shared with Flarum core's basic settings.) |
| **Forum keywords** | The site-wide `keywords` meta tag. Separate keywords with commas. |
| **Twitter card size** | Whether shared links render as a large image card (`summary_large_image`) or a small summary card (`summary`). |
| **Social media image** | The image used when a page is shared on Facebook, Twitter/X, Reddit, etc. A square 1200×1200 image is recommended; otherwise a 1200×630 landscape image. Falls back to your logo, then favicon. |
| **Discussion post crawl settings** | Whether search engines index only the first post of a discussion (default) or all posts. See below. |
| **No-follow / do-follow links** | External links get `rel="nofollow"` by default; manage exceptions in the [do-follow list](do-follow-links.md). |
| **Open external links in new tab** | External links open in a new tab. (Always on.) |

### Discussion post crawl settings

By default, search engines are pointed at the **main post** of each discussion.
You can switch to indexing **all posts** in a discussion, which gives search
engines more context (and, with [fof/best-answer](https://github.com/FriendsOfFlarum/best-answer)
installed, lets them surface the answer) at some extra rendering cost. Choose
based on how much load your server can handle.

## What gets rendered

For every page, the extension outputs:

- `<title>`, `description`, `keywords` and a `robots` directive
- `og:*` (Open Graph) and `twitter:*` meta tags, including an image
- a `<link rel="canonical">`
- a `schema.org` JSON-LD block

The JSON-LD is tailored per page type and carries `inLanguage` on every page:

| Page | schema.org type | Notable properties |
| --- | --- | --- |
| Index | `WebPage` + `WebSite` | `publisher`, `SearchAction` (sitelinks search box) |
| Discussion | `DiscussionForumPosting` | `author`, `headline`, `text`, `datePublished`/`dateModified`, breadcrumb, and `interactionStatistic` for comments, likes and views |
| Q&A discussion | `QAPage` | `Question` with `acceptedAnswer`/`suggestedAnswer`, `upvoteCount`, `answerCount` (needs fof/best-answer) |
| Tag | `CollectionPage` | `name`, and a `mainEntity` `ItemList` of the tag's recent discussions |
| User profile | `ProfilePage` | `Person` with `alternateName`, `identifier`, `agentInteractionStatistic` (posts/discussions) |

Discussions, user profiles and tag pages each receive their own title,
description and image derived from their content. To override any of these for a
specific item, use the [Configure SEO dialog](meta-management.md).

### Optional extension integrations

The structured data is enriched automatically when these are installed — no
configuration needed:

- **[fof/discussion-views](https://github.com/FriendsOfFlarum/discussion-views)** — adds a view-count `ViewAction` to discussion structured data.
- **[fof/discussion-language](https://github.com/FriendsOfFlarum/discussion-language)** — sets `inLanguage` per discussion from its assigned language (multi-language forums).
- **[fof/best-answer](https://github.com/FriendsOfFlarum/best-answer)** — renders Q&A discussions as a `QAPage`.
- **[flarum/likes](https://github.com/flarum/likes)** — adds like counts to the interaction statistics.

Developers can add their own data via the `PreparingPageMeta` event — see
[Extending & intercepting the metadata](developers/extending-metadata.md).
