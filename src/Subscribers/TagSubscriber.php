<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Subscribers;

use Carbon\Carbon;
use Flarum\Tags\Event as TagEvent;
use Flarum\Tags\Tag;
use FoF\Seo\SeoMeta\Event\Created;
use FoF\Seo\SeoMeta\SeoMeta;
use FoF\Seo\SeoProperties;
use Illuminate\Contracts\Events\Dispatcher;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Subscribe to tags creation, update or deleted.
 */
class TagSubscriber
{
    public function __construct(
        private readonly SeoProperties $seoProperties,
    ) {
    }

    /**
     * Subscribe function.
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(\Flarum\Tags\Event\Deleting::class, [$this, 'onModelEvent']);
        $events->listen(\Flarum\Tags\Event\Saving::class, [$this, 'onModelEvent']);
        $events->listen(Created::class, [$this, 'onMetaCreated']);
    }

    /**
     * Handle model event.
     */
    public function onModelEvent(object $event): void
    {
        $meta = SeoMeta::findOneByModel($event->tag);

        if ($event::class === TagEvent\Deleting::class) {
            if ($meta) {
                $meta->delete();
            }

            return;
        }

        if (!$meta) {
            $meta = SeoMeta::buildByModel($event->tag);
        }

        if (!$meta->auto_update_data) {
            return;
        }

        $this->updateMeta($meta, $event->tag);

        $meta->save();
    }

    /**
     * Handle meta created event.
     */
    public function onMetaCreated(Created $event): void
    {
        if ($event->objectType !== 'tags') {
            return;
        }

        $tag = Tag::find($event->objectId);

        if ($tag === null) {
            return;
        }

        $this->updateMeta($event->seoMeta, $tag);

        $event->seoMeta->save();
    }

    /**
     * Populate the SeoMeta row from the tag's current state.
     */
    public function updateMeta(SeoMeta $meta, Tag $tag): void
    {
        $meta->title = $tag->name;

        $meta->created_at = $tag->getAttribute('created_at') ?? Carbon::now();

        $meta->updated_at = $tag->last_posted_at;

        // Set discussion description and image
        $description = $tag->description ?? resolve(TranslatorInterface::class)->trans('flarum-tags.forum.tag.meta_description_text', ['{tag}' => $tag->name]);

        // Get Tag description
        $meta->description = $this->seoProperties->generateDescriptionFromContent($description);
    }
}
