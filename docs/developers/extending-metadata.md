# Extending & intercepting the metadata

There are three ways a third-party extension can integrate with FoF SEO. Pick the
smallest one that fits:

| You want to… | Use | Doc |
| --- | --- | --- |
| Emit/replace SEO for a **route the extension doesn't handle yet** (your own page) | A **page driver** registered via the `SEO` extender | [SeoMeta objects](seometa-objects.md#page-drivers) |
| Manage per-object metadata for **your own model** (a managed `SeoMeta`, the *Configure SEO* dialog) | The `SeoMeta` model + a subscriber | [SeoMeta objects](seometa-objects.md) |
| **Tweak/override** what FoF SEO already produces on existing pages (language, description, extra schema keys) | The **`PreparingPageMeta` event** | this page |

All three ultimately use the same [`SeoProperties`](seoproperties.md) API, so the
method reference there applies everywhere.

Beyond registering [page drivers](seometa-objects.md#page-drivers), third-party
extensions can intercept the fully-prepared metadata for **any** page and modify
it — the language, title, description, social image, or arbitrary schema.org
properties — through the `PreparingPageMeta` event.

## The `PreparingPageMeta` event

`FoF\Seo\Event\PreparingPageMeta` is dispatched after all page drivers have run,
but **before** the meta tags and JSON-LD are written to the document. Listeners
receive the [`SeoProperties`](seoproperties.md) API, the Flarum `Document`, and
the request:

```php
use Flarum\Extend;
use FoF\Seo\Event\PreparingPageMeta;

return [
    (new Extend\Event())
        ->listen(PreparingPageMeta::class, function (PreparingPageMeta $event) {
            // Override the document language
            $event->properties->setSchemaJson('inLanguage', 'de');

            // Replace the description
            $event->properties->setDescription('A localised description.');

            // Add any schema.org property
            $event->properties->setSchemaJson('isAccessibleForFree', true);

            // Inspect the request if you need to scope your changes
            // $event->request->getAttribute('routeName');
        }),
];
```

Because every page driver and this event mutate the **same** underlying
properties, a value you set here overrides whatever the core drivers produced.

The event carries:

| Property | Type | Purpose |
| --- | --- | --- |
| `$event->properties` | `SeoProperties` | The fluent API to read/override the prepared meta |
| `$event->document` | `Flarum\Frontend\Document` | The frontend document (e.g. to set `->language`) |
| `$event->request` | `ServerRequestInterface` | The current request, for scoping decisions |

## Overriding the language

`inLanguage` defaults to the request locale, but it is **overridable**:

- A **page driver** may set it (`$properties->setSchemaJson('inLanguage', …)`); the
  default won't clobber a value a driver already set.
- A **`PreparingPageMeta` listener** may set it; this always wins.

## Optional extension integrations

FoF SEO enriches its structured data when these extensions are present — no
configuration required:

| Extension | Effect |
| --- | --- |
| [fof/discussion-views](https://github.com/FriendsOfFlarum/discussion-views) | Adds a `ViewAction` `InteractionCounter` (view count) to a discussion's `DiscussionForumPosting` / `QAPage`. |
| [fof/discussion-language](https://github.com/FriendsOfFlarum/discussion-language) | Sets `inLanguage` on a discussion from its assigned language, overriding the viewer's locale (useful for multi-language forums). |
| [fof/best-answer](https://github.com/FriendsOfFlarum/best-answer) | Renders Q&A discussions as a `QAPage` with accepted/suggested answers. |
| [flarum/likes](https://github.com/flarum/likes) | Adds `LikeAction` interaction counts (and answer `upvoteCount`). |

If you maintain an extension that exposes comparable data for your own objects,
the `PreparingPageMeta` event is the place to add it.

## A complete page driver

To produce structured data for your own route — say a blog article at
`/blog/{id}` — implement `PageDriverInterface` and register it. The driver
receives the request and a [`SeoProperties`](seoproperties.md) instance:

```php
namespace Acme\Blog\Seo;

use Acme\Blog\BlogPostRepository;
use FoF\Seo\Page\PageDriverInterface;
use FoF\Seo\SeoProperties;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;

class BlogArticlePage implements PageDriverInterface
{
    public function __construct(
        protected readonly BlogPostRepository $posts,
    ) {
    }

    /** Only run when these extensions are enabled (empty = always). */
    public function extensionDependencies(): array
    {
        return [];
    }

    /** The Flarum route name(s) this driver handles. */
    public function handleRoutes(): array
    {
        return ['blog.article'];
    }

    public function handle(ServerRequestInterface $request, SeoProperties $properties): void
    {
        $post = $this->posts->find(Arr::get($request->getQueryParams(), 'id'));

        if ($post === null) {
            return;
        }

        $properties
            ->setSchemaJson('@type', 'BlogPosting')
            ->setMetaPropertyTag('og:type', 'article')
            ->setTitle($post->title)
            ->setDescription($properties->generateDescriptionFromContent($post->content))
            ->setImage($properties->getImageFromContent($post->content))
            ->setPublishedOn($post->created_at)
            ->setUrl('/blog/'.$post->id)
            ->setCanonicalUrl('/blog/'.$post->id);
    }
}
```

Register it in your `extend.php`, guarded so your extension still loads when FoF
SEO isn't installed:

```php
if (class_exists(\FoF\Seo\Extend\SEO::class)) {
    $extend[] = (new \FoF\Seo\Extend\SEO())
        ->addExtender('blog_article', \Acme\Blog\Seo\BlogArticlePage::class);
}
```

You can also `removeExtender('discussion')` to drop a built-in driver, or
register a driver under an existing route name to run alongside the core ones —
later-registered drivers override earlier values. For driving metadata from a
managed `SeoMeta` record (so it appears in the *Configure SEO* dialog and updates
automatically), see the [SeoMeta developer guide](seometa-objects.md).
