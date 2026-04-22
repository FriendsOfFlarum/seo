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

use Flarum\Foundation\DispatchEventsTrait;
use Flarum\Tags\TagRepository;
use FoF\Seo\SeoMeta\SeoMeta;
use FoF\Seo\SeoProperties;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TagPage implements PageDriverInterface
{
    use DispatchEventsTrait;

    protected TranslatorInterface $translator;

    public function __construct(
        TranslatorInterface $translator,
        Dispatcher $events
    ) {
        $this->events = $events;
        $this->translator = $translator;
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
        $tagId = Arr::get($request->getQueryParams(), 'slug');

        // I do support it, but it didn't work
        if (!is_numeric($tagId)) {
            $tagId = resolve(TagRepository::class)->getIdForSlug($tagId);
        }

        try {
            $tag = resolve(TagRepository::class)->findOrFail($tagId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Do nothing, no model found
            return;
        }

        $seoMeta = SeoMeta::findByModelOrCreate($tag);

        // Run events in case the model was created
        $this->dispatchEventsFor($seoMeta);

        $properties->generateTagsFromMetaData($seoMeta);

        $properties
            // Add Schema.org metadata: CollectionPage https://schema.org/CollectionPage
            ->setSchemaJson('@type', 'CollectionPage')
            ->setSchemaJson('about', $seoMeta->description)
            // Tag URL
            ->setUrl('/t/'.$tag->slug)

            // Canonical url
            ->setCanonicalUrl('/t/'.$tag->slug);
    }
}
