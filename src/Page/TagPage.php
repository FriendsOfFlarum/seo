<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Page;

use Flarum\Discussion\Discussion as FlarumDiscussion;
use Flarum\Foundation\DispatchEventsTrait;
use Flarum\Http\RequestUtil;
use Flarum\Http\SlugManager;
use Flarum\Http\UrlGenerator;
use Flarum\Tags\Tag;
use FoF\Seo\Breadcrumb\Crumb;
use FoF\Seo\Breadcrumb\TagBreadcrumb;
use FoF\Seo\SeoMeta\SeoMeta;
use FoF\Seo\SeoProperties;
use FoF\Seo\TagIndexingPolicy;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TagPage implements PageDriverInterface
{
    use DispatchEventsTrait;

    protected Dispatcher $events;

    public function __construct(
        protected readonly TranslatorInterface $translator,
        Dispatcher $events,
        protected readonly TagIndexingPolicy $tagIndexingPolicy,
        protected readonly UrlGenerator $urlGenerator,
        protected readonly SlugManager $slugManager,
    ) {
        $this->events = $events;
    }

    public function extensionDependencies(): array
    {
        return ['flarum-tags'];
    }

    public function handleRoutes(): array
    {
        return ['tag'];
    }

    public function handle(
        ServerRequestInterface $request,
        SeoProperties $properties
    ): void {
        $slug = Arr::get($request->getQueryParams(), 'slug');

        if ($slug === null) {
            return;
        }

        $tagSlugger = $this->slugManager->forResource(Tag::class);

        try {
            // Resolve through the configured tag slug driver, as flarum/tags does
            // for the page itself: it decodes the slug (a tag slugged `動力` is
            // requested as /t/%E5%8B%95%E5%8A%9B) and understands `id_with_slug`
            // URLs. Looking the raw value up with getIdForSlug() returned null for
            // both, and the null reached findOrFail(int): a 500 for the whole page.
            /** @var Tag $tag */
            $tag = $tagSlugger->fromSlug($slug, RequestUtil::getActor($request));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // No tag matched the slug.
            return;
        }

        $seoMeta = SeoMeta::findByModelOrCreate($tag);

        // Run events in case the model was created
        $this->dispatchEventsFor($seoMeta);

        $properties->generateTagsFromMetaData($seoMeta);

        $tagUrl = $this->urlGenerator->to('forum')->route('tag', ['slug' => $tagSlugger->toSlug($tag)]);

        $properties
            // Add Schema.org metadata: CollectionPage https://schema.org/CollectionPage
            ->setSchemaJson('@type', 'CollectionPage')
            ->setSchemaJson('name', $tag->name)
            ->setSchemaJson('about', $seoMeta->description)
            // Tag URL, as the configured slug driver writes it
            ->setUrl($tagUrl, false)
            // Canonical url
            ->setCanonicalUrl($tagUrl, false);

        // Breadcrumb: Home › Tags › {ancestors} › {this tag}. The tag itself is
        // the current page, so it has no url.
        $tagBreadcrumb = new TagBreadcrumb($this->urlGenerator);
        $tagBreadcrumb->pushAncestorsOf($properties->breadcrumb(), $tag);
        $properties->breadcrumb()->push(new Crumb($tag->name));

        // List the tag's most recent public discussions as a schema.org ItemList.
        $discussions = $tag->discussions()
            ->where('is_private', false)
            ->whereNull('hidden_at')
            ->latest('last_posted_at')
            ->limit(20)
            ->get();

        $discussionSlugger = $this->slugManager->forResource(FlarumDiscussion::class);

        $itemListElement = $discussions->values()->map(fn ($discussion, int $index) => [
            '@type'    => 'ListItem',
            'position' => $index + 1,
            'url'      => $this->urlGenerator->to('forum')->route('discussion', ['id' => $discussionSlugger->toSlug($discussion)]),
            'name'     => $discussion->title,
        ])->toArray();

        $properties->setSchemaJson('mainEntity', [
            '@type'           => 'ItemList',
            'numberOfItems'   => count($itemListElement),
            'itemListElement' => $itemListElement,
        ]);

        // Keep the listing page of an admin-excluded tag out of the index (GH #117).
        // Overrides the robots directive set by generateTagsFromMetaData above.
        if ($this->tagIndexingPolicy->shouldNoindex([$tag])) {
            $properties->setMetaTag('robots', 'noindex, follow');
        }
    }
}
