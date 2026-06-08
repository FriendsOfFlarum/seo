# SeoMeta objects — developer guide

FoF SEO lets other extensions describe their own objects to search engines. This
guide covers the `SeoMeta` model, how to surface the *Configure SEO* dialog for
your objects, and how to keep their metadata up to date on the backend.

## What is a SeoMeta object?

A `SeoMeta` record holds the SEO metadata for one object in Flarum — a
discussion, a post, a tag, a page, a blog article, anything. It carries a title,
descriptions, keywords, robots directives, an estimated reading time, and
separate Open Graph and Twitter/X overrides.

Usually a record is **managed** (kept in sync automatically), but it can be
customised — see [Per-item SEO](../meta-management.md).

## Overriding properties

The resolution rule is: **a `null` field falls back to the more general value.**
A `twitter_description` falls back to the item `description`, which falls back to
the forum default. This lets you customise only what you need; see the worked
examples in [Per-item SEO](../meta-management.md#how-overrides-resolve).

## Auto-updating ("managed")

Managed records are updated in the background when the underlying object changes
(e.g. a discussion's title, its first post, or its tags), so tags don't have to
be recalculated on every page load.

While managed, only **keywords** are editable. Turning off **Auto update meta
tags** unlocks every field. Re-enabling it discards manual changes and resumes
automatic updates.

> You **must** respect the `auto_update_data` flag in your own code: if it is
> `false`, do **not** overwrite the record's data.

## Images

An image is normally detected from an object's content (for example, the first
image in a post's body) and used for both the Open Graph and Twitter image tags.
SVG images are skipped (social networks reject them), so the first **raster**
image is chosen; see [Per-item SEO](../meta-management.md#images) for the
`fof:seo:fix-svg-images` command that backfills items stored before this.

If a **different extension** owns the image, it must set the `image_source`
column so FoF SEO knows not to overwrite it (the value is also shown to the user
in the dialog). For example, a blog extension might set the source to
`my-blog-extension`.

> The `open_graph_image` is leading for both Open Graph and Twitter tags. If only
> a Twitter image is set (with no Open Graph image), no image tags are emitted.

---

## Frontend integration

To open the *Configure SEO* dialog from your extension, add `fof-seo` to the
`useExtensions` array in your `webpack.config.js`:

```js
const config = require('flarum-webpack-config');

module.exports = config({
  useExtensions: ['fof-upload', 'fof-seo'], // example list
});
```

> **Guard your code.** If your extension can run without FoF SEO, wrap the
> integration in a check so it doesn't break when the SEO extension is disabled.
> Also confirm the user has the `canConfigureSeo` permission, or the request will
> be denied.

### Open the dialog by object type and ID

```js
// `discussion` is a Discussion model from the store
if ('fof-seo' in flarum.extensions && app.forum.attribute('canConfigureSeo')) {
  const {
    components: { MetaSeoModal },
  } = require('@fof-seo');

  app.modal.show(MetaSeoModal, {
    objectType: 'discussions',
    objectId: discussion.id(),
  });
}
```

### Open the dialog by model

Alternatively, pass the model directly. This loads its `seoMeta` relationship, so
that relationship must be **registered and loaded** on the model.

```js
if ('fof-seo' in flarum.extensions && app.forum.attribute('canConfigureSeo')) {
  const {
    components: { MetaSeoModal },
  } = require('@fof-seo');

  app.modal.show(MetaSeoModal, {
    object: discussion, // automatically queries the .seoMeta relationship
  });
}
```

---

## Backend integration

The backend uses event subscribers to keep `SeoMeta` records updated, and *page
drivers* to emit tags for specific routes.

### Registering in `extend.php`

Optionally wire up FoF SEO from your extension. **Do not** `use` the SEO
extender class at the top of the file — if a site doesn't have FoF SEO installed,
your extension would fail to load. Guard it with `class_exists()` instead:

```php
$extend = [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/Forum.less'),
    // ...the rest of your extenders...
];

$events = (new Extend\Event())
    ->listen(/* ...your other listeners... */);

// Extend FoF SEO only when it is installed
if (class_exists(\FoF\Seo\Extend\SEO::class)) {
    $extend[] = (new \FoF\Seo\Extend\SEO())
        ->addExtender('blog_article', \Acme\Blog\Seo\BlogArticlePage::class);

    $events->subscribe(\Acme\Blog\Subscribers\SeoBlogSubscriber::class);
}

$extend[] = $events;

return $extend;
```

### A custom subscriber

Write a subscriber that updates the `SeoMeta` record when your object changes.
The bundled [`DiscussionSubscriber`](../../src/Subscribers/DiscussionSubscriber.php)
is a good reference.

> You **must** respect `auto_update_data` (don't update when it's `false`) and
> the `image_source` column (don't override an image that isn't yours).

#### Fetch or create a record

```php
use FoF\Seo\SeoMeta\SeoMeta;

$seoMeta = SeoMeta::findByModelOrCreate($discussion);

// Dispatch any events raised on creation
// (requires `use DispatchEventsTrait;` and an injected `Dispatcher $events`)
$this->dispatchEventsFor($seoMeta);
```

### Page drivers

To emit meta tags / JSON-LD for a particular route, register a class that
implements [`PageDriverInterface`](../../src/Page/PageDriverInterface.php) via
`->addExtender('your_purpose', YourPage::class)`.

The extension ships drivers you can use as references:

| Extender name | Class |
| --- | --- |
| `index` | [`IndexPage`](../../src/Page/IndexPage.php) |
| `profile` | [`ProfilePage`](../../src/Page/ProfilePage.php) |
| `tags` | [`TagPage`](../../src/Page/TagPage.php) |
| `page_extension` | [`PageExtensionPage`](../../src/Page/PageExtensionPage.php) |
| `discussion` | [`DiscussionPage`](../../src/Page/DiscussionPage.php) |
| `discussion_best_answer` | [`DiscussionBestAnswerPage`](../../src/Page/DiscussionBestAnswerPage.php) |

A driver declares which Flarum route names it handles (the same route names the
frontend uses):

```php
public function handleRoutes(): array
{
    return ['discussion', 'blog'];
}
```

…and may declare extensions it depends on, so it only runs when they're enabled:

```php
public function extensionDependencies(): array
{
    return ['flarum-tags'];
}
```

Inside `handle()`, you receive a [`SeoProperties`](seoproperties.md) instance —
use it to set the page's tags and structured data.
