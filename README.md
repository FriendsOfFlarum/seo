# FoF SEO

[![MIT license](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/FriendsOfFlarum/seo/blob/1.x/LICENSE.md) [![Latest Stable Version](https://img.shields.io/packagist/v/fof/seo.svg)](https://packagist.org/packages/fof/seo) [![Total Downloads](https://img.shields.io/packagist/dt/fof/seo.svg)](https://packagist.org/packages/fof/seo)

A [Flarum](https://flarum.org) extension that adds SEO tags to your forum — meta description, Open Graph, Twitter cards, and schema.org structured data.

## Documentation

Full documentation lives in the [`docs/`](docs/README.md) folder:

- [Installation](docs/installation.md)
- [Features & configuration](docs/features.md)
- [Per-item SEO (the *Configure SEO* dialog)](docs/meta-management.md)
- [Do-follow link list](docs/do-follow-links.md)
- [Sitemap & robots.txt](docs/sitemap-and-robots.md)
- Developers: [SeoMeta objects](docs/developers/seometa-objects.md) · [the `SeoProperties` class](docs/developers/seoproperties.md)
- [Contributing](docs/contributing.md)

## Installation

```sh
composer require fof/seo:"*"
```

## Updating

```sh
composer update fof/seo
php flarum cache:clear
```

## Migrating from v17development/flarum-seo

This extension was transferred to FriendsOfFlarum in April 2026 and was previously published as `v17development/flarum-seo`. A migration guide for forum admins and third-party extension authors will be published before release.

## Features

SEO tags are generated for the following pages:

- Home page
- Tags page (if `flarum/tags` is enabled)
- Discussion page
- User profile
- Pages page (if `fof/pages` is enabled)

Methods used:

- HTML meta tags (`application-name`, `description`, `keywords`, `language`)
- Open Graph tags (`og:type`, `og:title`, `og:description`, `og:url`, `article:published_time`, `article:updated_time`)
- Twitter cards
- `inLanguage` on every page
- Schema.org structured data:
  - [WebPage](https://schema.org/WebPage) + [WebSite](https://schema.org/WebSite) `SearchAction`
  - [DiscussionForumPosting](https://schema.org/DiscussionForumPosting) — with `headline`, `text` and comment/like/view [interactionStatistic](https://schema.org/interactionStatistic)s
  - [QAPage](https://schema.org/QAPage) for Q&A discussions (with `fof/best-answer`)
  - [CollectionPage](https://schema.org/CollectionPage) — with an `ItemList` of the tag's discussions
  - [ProfilePage](https://schema.org/ProfilePage) — with a rich `Person` (identity + activity stats)
- Uses the first image in the post as the social-media image when one is present, falling back to the configured default.

> Your `robots.txt` and XML sitemap are provided by [fof/sitemap](https://github.com/FriendsOfFlarum/sitemap). See [Sitemap & robots.txt](docs/sitemap-and-robots.md).

## Works with

Compatible — but not required — alongside:

- [flarum/likes](https://github.com/flarum/likes) — like counts in the interaction statistics
- [flarum/tags](https://github.com/flarum/tags)
- [fof/best-answer](https://github.com/FriendsOfFlarum/best-answer) — Q&A (`QAPage`) structured data
- [fof/sitemap](https://github.com/FriendsOfFlarum/sitemap) — sitemap & robots.txt
- [fof/pages](https://github.com/FriendsOfFlarum/pages)
- [fof/discussion-views](https://github.com/FriendsOfFlarum/discussion-views) — view counts in the structured data
- [fof/discussion-language](https://github.com/FriendsOfFlarum/discussion-language) — per-discussion `inLanguage` for multi-language forums

## Extending

Third-party extensions have three integration points:

- **Page drivers** — emit/replace structured data for your own routes by extending `FoF\Seo\Extend\SEO` and implementing `FoF\Seo\Page\PageDriverInterface`.
- **`SeoMeta` objects** — give your own models managed, per-object metadata that appears in the *Configure SEO* dialog.
- **The `PreparingPageMeta` event** — intercept and override the prepared metadata (language, description, any schema.org property) on any page.

See the developer guides for details:

- [SeoMeta objects — developer guide](docs/developers/seometa-objects.md)
- [The `SeoProperties` class](docs/developers/seoproperties.md)
- [Extending & intercepting the metadata](docs/developers/extending-metadata.md)

## Credits

This extension was originally created and maintained by [V17 Development](https://v17.dev) as `v17development/flarum-seo`. FriendsOfFlarum is grateful for their work bringing SEO support to the Flarum ecosystem and continues development with their blessing.

## Links

- [Documentation](docs/README.md)
- [Packagist](https://packagist.org/packages/fof/seo)
- [GitHub](https://github.com/FriendsOfFlarum/seo)
- [Issues](https://github.com/FriendsOfFlarum/seo/issues)
- [Support](https://discuss.flarum.org/d/39374)

## License

This extension is licensed under the MIT License. See the [LICENSE.md](LICENSE.md) file for details.
