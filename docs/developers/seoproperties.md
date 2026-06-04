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
$properties->setPublishedOn('2020-08-22 14:14:00'); // article:published_time + datePublished
$properties->setUpdatedOn('2020-08-25 18:55:00');   // article:updated_time + dateModified
```

## Lower-level helpers

```php
$properties->setMetaTag('robots', 'index, follow');         // arbitrary <meta name>
$properties->setMetaPropertyTag('og:site_name', 'My forum'); // arbitrary <meta property>
$properties->setSchemaJson('@type', 'WebPage');              // arbitrary JSON-LD key
```

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
