# FoF SEO

[![MIT license](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/FriendsOfFlarum/seo/blob/1.x/LICENSE.md) [![Latest Stable Version](https://img.shields.io/packagist/v/fof/seo.svg)](https://packagist.org/packages/fof/seo) [![Total Downloads](https://img.shields.io/packagist/dt/fof/seo.svg)](https://packagist.org/packages/fof/seo)

A [Flarum](https://flarum.org) extension that adds SEO tags to your forum — meta description, Open Graph, Twitter cards, schema.org structured data, and a dynamic `robots.txt`.

## Installation

```sh
composer require fof/seo
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
- A dynamic `robots.txt` (with a link to your sitemap when `fof/sitemap` is enabled)
- Open Graph tags (`og:type`, `og:title`, `og:description`, `og:url`, `article:published_time`, `article:updated_time`)
- Twitter cards
- Schema.org structured data:
  - [WebPage](https://schema.org/WebPage)
  - [QAPage](https://schema.org/QAPage) (default)
  - [DiscussionForumPosting](https://schema.org/DiscussionForumPosting) (when Q&A results are disabled)
  - [CollectionPage](https://schema.org/CollectionPage)
  - [ProfilePage](https://schema.org/ProfilePage)
- Uses the first image in the post as the social-media image when one is present, falling back to the configured default.

## Works with

Compatible — but not required — alongside:

- [flarum/likes](https://github.com/flarum/likes)
- [flarum/tags](https://github.com/flarum/tags)
- [fof/best-answer](https://github.com/FriendsOfFlarum/best-answer)
- [fof/sitemap](https://github.com/FriendsOfFlarum/sitemap)
- [fof/pages](https://github.com/FriendsOfFlarum/pages)

## Extending

Third-party extensions can register custom SEO page drivers by extending the `FoF\Seo\Extend\SEO` extender and implementing `FoF\Seo\Page\PageDriverInterface`. Full extension documentation will follow.

## Credits

This extension was originally created and maintained by [V17 Development](https://v17.dev) as `v17development/flarum-seo`. FriendsOfFlarum is grateful for their work bringing SEO support to the Flarum ecosystem and continues development with their blessing.

## Links

- [Packagist](https://packagist.org/packages/fof/seo)
- [GitHub](https://github.com/FriendsOfFlarum/seo)
- [Issues](https://github.com/FriendsOfFlarum/seo/issues)

## License

This extension is licensed under the MIT License. See the [LICENSE.md](LICENSE.md) file for details.
