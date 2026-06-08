# Per-item SEO — the *Configure SEO* dialog

Most of the time the extension manages each item's metadata for you. When you
want to hand-tune a specific discussion (or any other supported object), use the
**Configure SEO** control.

> Requires the **Allow configuring SEO** permission — see
> [Installation](installation.md#permissions).

On a discussion, open the … (more) controls and choose **Configure SEO**. A
dialog opens showing every meta field for that item.

## Managed vs. custom

Each item's metadata is backed by a *SeoMeta* record. By default it is
**managed**: the extension keeps it in sync automatically in the background
whenever the underlying content changes (a discussion's title, its first post,
its tags, and so on), so pages don't have to be recomputed on every request.

While **Auto update meta tags** is on, the data is managed and only **keywords**
can be edited. Turn it off to take full manual control of every field.

> If you edit a managed item, auto-updating switches off for it. Re-enabling
> **Auto update meta tags** discards your manual changes and resumes automatic
> updates from then on.

## Fields you can set

- **Meta title** and **description**
- **Keywords**
- **Robots directives** — `noindex`, `nofollow`, `noarchive`, `noimageindex`,
  `nosnippet` for that page
- **Estimated reading time**
- **Twitter/X card** — a custom title, description and image
- **Open Graph** — a custom title, description and image

## How overrides resolve

The rule is: **if a field is `null`, it falls back to the more general value.**

- A Twitter or Open Graph field falls back to the item's main title/description.
- The item's values fall back to the forum-wide defaults.

So if you set only a **description**, all three description tags use it:

```html
<meta name="description" content="This is a demo description">
<meta name="twitter:description" content="This is a demo description">
<meta property="og:description" content="This is a demo description">
```

Set a **Twitter description** as well and only that tag changes:

```html
<meta name="description" content="This is a demo description">
<meta name="twitter:description" content="A custom description for Twitter/X">
<meta property="og:description" content="This is a demo description">
```

## Images

An item's image is usually detected from its content (for example, the first
image in the opening post). When found, it populates both the Open Graph and
Twitter image tags; you can override either in the dialog.

> The **Open Graph image is leading**: if no Twitter image is set, the Open Graph
> image is used for Twitter too. Setting *only* a Twitter image (with no Open
> Graph image) will not emit image tags.

Auto-detection skips **SVG** images: Slack, X and most networks reject SVG for
preview images, so a post that opens with an SVG badge (e.g. a shields.io
license badge) would otherwise unfurl with no image. The first **raster** image
(PNG/JPG/WebP/GIF) in the post is used instead; if there is none, the image
falls back to the forum-wide social media image (then the logo, then favicon).

If another extension owns an item's image, FoF SEO records its *source* and won't
overwrite it — see the [developer guide](developers/seometa-objects.md#images).

### Fixing previously-stored SVG images

The detected image is stored on the item when it is saved, so discussions that
were saved *before* the SVG exclusion existed may still have an SVG stored as
their Open Graph image. They self-heal the next time the discussion is edited,
but you can fix them all at once with a console command:

```bash
php flarum fof:seo:fix-svg-images
```

For each discussion whose stored image is an SVG (and was set automatically, not
manually or by another extension), it re-derives the first raster image from the
post — or clears the stored image so rendering falls back to the forum social
image. Manually-set and other-extension-managed images are left untouched.

The command processes discussions in batches to keep memory and database load
flat on large forums:

| Option | Description |
| --- | --- |
| `--batch=<n>` | Number of rows to process per chunk. Defaults to `100`. |
| `--dry-run` | Report what would change without writing anything. |

```bash
# Preview the changes without saving
php flarum fof:seo:fix-svg-images --dry-run

# Use smaller chunks on a constrained server
php flarum fof:seo:fix-svg-images --batch=25
```
