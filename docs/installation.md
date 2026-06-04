# Installation

## Install

Require the package with Composer:

```bash
composer require fof/seo
```

Then enable **FoF SEO** from the *Administration → Extensions* page (or with
`php flarum extension:enable fof-seo`).

That's it — the extension starts generating SEO tags for your forum's pages
immediately, using your existing forum title, description and logo as sensible
defaults.

> **Upgrading from `v17development/flarum-seo`?** This package declares
> `replace` for it, so Composer will swap it out automatically. Settings and
> stored per-item metadata are preserved.

## Permissions

Most of the extension works with no configuration. To let users **configure** SEO
— access the admin pages and edit per-item metadata via the *Configure SEO*
dialog — grant the **Allow configuring SEO** permission.

Go to *Administration → Permissions* and assign the permission (it appears under
its own **SEO** category) to the groups you trust. Administrators always have it.

Internally this is the `fof-seo.canConfigure` permission, surfaced to the
frontend as the `canConfigureSeo` forum attribute.

## Recommended companion extension

robots.txt and XML sitemap generation are provided by
[**fof/sitemap**](https://github.com/FriendsOfFlarum/sitemap). Installing and
enabling it is strongly recommended — see
[Sitemap & robots.txt](sitemap-and-robots.md).

```bash
composer require fof/sitemap
```
