# FoF SEO — Documentation

FoF SEO adds search-engine optimization to your Flarum forum. It renders the
meta tags, [Open Graph](https://ogp.me/) / Twitter cards and
[schema.org](https://schema.org) structured data that search engines and social
networks use to understand and display your content — automatically, and with
per-item overrides where you need them.

> This extension is the FriendsOfFlarum continuation of the original
> `v17development/flarum-seo` extension and replaces it.

## What it does

- **Site-wide meta tags** — application name, description, canonical URL, and a
  `robots` directive on every page.
- **Open Graph & Twitter cards** — `og:*` and `twitter:*` tags so links shared on
  social media render rich previews. The Twitter card size is configurable.
- **Structured data (JSON-LD)** — `WebPage`, `DiscussionForumPosting` (with
  `headline`, `text`, comment/like/view `interactionStatistic`s), `QAPage`,
  `CollectionPage` (with an `ItemList` of discussions), `ProfilePage`,
  breadcrumbs, an organization `publisher` block, a site `SearchAction`, and
  `inLanguage` on every page.
- **Per-page metadata** — discussions, tags and user profiles each get tailored
  titles, descriptions, images, and published/updated timestamps.
- **Per-item overrides** — a *Configure SEO* dialog lets you hand-tune the meta
  for an individual discussion (or any registered object), or let it stay
  automatically *managed*.
- **External-link control** — outbound links get `rel="nofollow"` and open in a
  new tab by default, with a configurable *do-follow* allow-list.
- **A health check** — an admin dashboard that audits your configuration and
  points out what to improve.

## Documentation

### Using the extension

- [Installation](installation.md)
- [Features & configuration](features.md)
- [Per-item SEO (the *Configure SEO* dialog)](meta-management.md)
- [Do-follow link list](do-follow-links.md)
- [Sitemap & robots.txt](sitemap-and-robots.md)

### For extension developers

- [SeoMeta objects — developer guide](developers/seometa-objects.md)
- [The `SeoProperties` class](developers/seoproperties.md)
- [Extending & intercepting the metadata](developers/extending-metadata.md)

### Project

- [Contributing](contributing.md)

## Requirements

- Flarum `^1.8`
- PHP `^8.2`
