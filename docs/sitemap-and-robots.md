# Sitemap & robots.txt

Both your XML **sitemap** and your **robots.txt** are provided by the
[**fof/sitemap**](https://github.com/FriendsOfFlarum/sitemap) extension, not by
FoF SEO itself. Installing it is strongly recommended — the SEO health check will
flag both as missing until it's enabled.

```bash
composer require fof/sitemap
```

Then enable **fof/sitemap** from *Administration → Extensions*.

## What you get

Once fof/sitemap is enabled:

- **`/sitemap.xml`** — an automatically generated, always-up-to-date index of
  your discussions, tags and (if installed) [fof/pages](https://github.com/FriendsOfFlarum/pages)
  content. There is nothing to maintain.
- **`/robots.txt`** — generated for you, telling crawlers which areas they may
  index and pointing them at your sitemap.

## Telling search engines about your sitemap

After the sitemap is live, submit it to the search engines you care about. FoF
SEO's **Search engine information** admin page walks through registering with
Google Search Console, Bing Webmaster Tools, Yandex.Webmaster and Yahoo, and
where to provide your `sitemap.xml` URL in each.

> **Historical note:** earlier versions of this extension shipped their own
> robots.txt editor. That responsibility now lives entirely in fof/sitemap to
> avoid two extensions fighting over the same file.
