# The `SeoProperties` class

`FoF\Seo\SeoProperties` is the developer-facing API for influencing a page's SEO
output. Inside a [page driver](seometa-objects.md#page-drivers) you receive a
`SeoProperties` instance and call methods on it; whatever you set overrides the
default SEO data for that page.

> In version 2 of the extension the recommended approach is to drive metadata
> from a `SeoMeta` object — read the [SeoMeta developer guide](seometa-objects.md)
> first. `SeoProperties` remains the low-level way to set individual tags.

Every setter returns `$this`, so calls can be chained.

## Title

```php
$properties->setTitle('Example title');
```

Sets the page `<title>`, plus the `og:title` and `twitter:title` meta tags.

The second argument controls whether the page `<title>` is updated. Pass `false`
to update **only** the social-media tags:

```php
$properties->setTitle('Social media title only', false);
```

Renders:

```html
<title>Example title</title>
<meta name="twitter:title" content="Example title">
<meta property="og:title" content="Example title">
<script type="application/ld+json">[{ "@context":"http://schema.org", "...", "title": "Example title" }]</script>
```

## Description

```php
$properties->setDescription('Example description');
```

Sets the `description`, `og:description` and `twitter:description` tags (and the
JSON-LD `description`). Passing `null` is a no-op.

Long content can be trimmed to a safe length first:

```php
$description = $properties->generateDescriptionFromContent($someLongContent);
$properties->setDescription($description);
```

`generateDescriptionFromContent()` strips tags, collapses whitespace and
truncates to 157 characters (adding an ellipsis when truncated).

## URL

```php
$properties->setUrl('/d/5-example-topic');
```

Sets `og:url`, `twitter:url` and the JSON-LD `url`. The application URL is
prepended by default; pass a full URL with `false` to opt out:

```php
$properties->setUrl('https://flarum.local/d/5-example-topic', false);
```

## Canonical URL

Use this when you have duplicate pages and need to tell search engines which one
is canonical. Adds/updates the `<link rel="canonical">` tag.

```php
$properties->setCanonicalUrl('/d/5-example-topic');
```

## Keywords

```php
$properties->setKeywords(['keyword 1', 'flarum', 'site', 'blog']);
```

Accepts an array or a comma-separated string. Renders:

```html
<meta name="keywords" content="keyword 1, flarum, site, blog">
```

## Social media image

```php
$properties->setImage('https://example.org/image.png');
```

Sets `og:image`, `twitter:image` and the JSON-LD `image`.

## Published / updated timestamps

```php
$properties->setPublishedOn('2020-08-22 14:14:00'); // schema.org datePublished
$properties->setUpdatedOn('2020-08-25 18:55:00');   // schema.org dateModified
```

These record the dates on the schema.org entity. The matching Open Graph
`article:published_time` / `article:modified_time` (`property`) tags are emitted
automatically — but only when the page's `og:type` is `article`.

`generateTagsFromMetaData()` sets both from the `SeoMeta` record's `created_at`
and `updated_at`, which are page-level times. The discussion drivers then use
post dates instead: `datePublished` is when the discussion was started, and
`dateModified` is when its opening post was last edited, or is removed when it
never was. Replies and answers carry their own `datePublished` and, when
edited, `dateModified`.

## Removing a JSON-LD property

```php
$properties->removeSchemaJson('dateModified');
```

Removes a top-level key from the page's main JSON-LD node (the one
`setSchemaJson()` writes to). Removing a key that isn't set does nothing, and
the method returns `$this` like the setters.

It only affects that node's own top-level keys:

- the `BreadcrumbList` and `WebSite` nodes in the same `@graph` are left alone;
- nested values stay: to change part of e.g. `mainEntity` or `author`, read
  what you need and set the whole value again with `setSchemaJson()`;
- meta and Open Graph tags you set yourself stay. The exception is
  `article:published_time` / `article:modified_time`, which are written from
  `datePublished` / `dateModified` after `PreparingPageMeta`: removing the
  schema date in a page driver or a `PreparingPageMeta` listener leaves the
  generated tag out too, but not one set explicitly with
  `setMetaPropertyTag()`.

The last call wins: a later `setSchemaJson()` for the same key, a later
`generateTagsFromMetaData()` (which sets `datePublished`, `dateModified` and
others again) or a later `PreparingPageMeta` listener can put the key back.
Removing a key the page type needs, such as `@type`, produces invalid
structured data, as setting a wrong value would.

## Lower-level helpers

```php
$properties->setMetaTag('robots', 'index, follow');         // arbitrary <meta name>
$properties->setMetaPropertyTag('og:site_name', 'My forum'); // arbitrary <meta property>
$properties->setSchemaJson('@type', 'WebPage');              // arbitrary JSON-LD key
$properties->removeSchemaJson('dateModified');               // remove a JSON-LD key
```

`addRobotsDirectives()` merges directives as a set in a stable order:

```php
$properties->addRobotsDirectives(['noindex']);
```

Existing directives are retained. `noindex` takes precedence over `index`, and
`nofollow` over `follow`; adding `index` or `follow` cannot lift a restriction.
`generateTagsFromMetaData()` also adds its robots directives through this merge.
Use `setMetaTag('robots', ...)` when you intend to replace the complete value.

## Content helpers

```php
// Find the first usable image URL inside some content
$image = $properties->getImageFromContent($content);

// Estimate reading time (seconds) for some content
$seconds = $properties->getEstimatedReadingTime($content);
```

## Structured data

```php
// Build a schema.org BreadcrumbList
$properties->generateSchemaBreadcrumb([
    ['name' => 'Coffee corner', 'url' => 'https://flarum.local/t/coffee-corner'],
]);

// Generate all default tags from a SeoMeta record
$properties->generateTagsFromMetaData($seoMeta);
```

See [`src/SeoProperties.php`](../../src/SeoProperties.php) for the full, current
signature of every method.
