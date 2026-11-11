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

## Noindex, the sitemap and robots.txt

While both extensions are enabled, FoF SEO decides which pages search engines
may index, and fof/sitemap only lists what FoF SEO lets through. A page that
emits `noindex` is never submitted in the sitemap:

| Page | Left out of the sitemap when |
| --- | --- |
| Discussion | one of its tags is selected under **De-index tags**, or is a child of a selected tag; or its own [SEO meta](meta-management.md) has `noindex` |
| Tag page | the tag is selected under **De-index tags**, or is a child of a selected tag; or its own SEO meta has `noindex` (a tag's own `noindex` does not carry over to its discussions) |
| [fof/pages](https://github.com/FriendsOfFlarum/pages) Page | its own SEO meta has `noindex` |
| User profile | **De-index profile pages** is on (every profile) |

fof/sitemap's own rules still apply on top: only guest-visible content is
listed, its minimum-count thresholds for tags and users still apply, and its
own **exclude users** / **exclude tags** settings still leave those out of the
sitemap.

Search engines can only read a `noindex` on a page they are allowed to fetch,
so a robots.txt `Disallow` would hide it. fof/sitemap's **exclude users** and
**exclude tags** settings normally also block those pages in robots.txt; FoF
SEO lifts a block only while it noindexes the pages behind it:

| fof/sitemap blocks | Lifted while |
| --- | --- |
| Profiles (`Disallow: /u/`, with **exclude users** on) | **De-index profile pages** is on |
| Tags (`Disallow: /t/` and `/tags`, with **exclude tags** on) | a tag guests can open is selected under **De-index tags** (or is a child of a selected tag), or has `noindex` in its own SEO meta |

Otherwise robots.txt stays exactly as fof/sitemap writes it, and without FoF
SEO fof/sitemap keeps blocking them as before. The tags block is all or
nothing (fof/sitemap blocks every tag page), so with one tag noindexed every
tag page becomes crawlable; the tags you don't noindex are then indexable. A
robots.txt served by your web server instead of fof/sitemap is outside both
extensions' control: check that it doesn't block pages you want de-indexed.

Notes:

- Leaving a page out of the sitemap doesn't remove it from search results; the
  `noindex` on the page does, the next time a search engine crawls it.
- In fof/sitemap's cached modes, a change shows up in the sitemap at its next
  scheduled build.
- This needs fof/sitemap 3.1.0 or later; Composer refuses older versions next
  to FoF SEO.
- Upgrading an existing forum? See [Upgrading](upgrading.md) for what changes.

## Telling search engines about your sitemap

After the sitemap is live, submit it to the search engines you care about. FoF
SEO's **Search engine information** admin page walks through registering with
Google Search Console, Bing Webmaster Tools, Yandex.Webmaster and Yahoo, and
where to provide your `sitemap.xml` URL in each.

> **Historical note:** earlier versions of this extension shipped their own
> robots.txt editor. That responsibility now lives entirely in fof/sitemap to
> avoid two extensions fighting over the same file.
