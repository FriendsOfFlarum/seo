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

use Flarum\Discussion\Discussion;
use Flarum\Discussion\Event as DiscussionEvent;
use Flarum\Post\CommentPost;
use FoF\Seo\SeoMeta\Event\Created;
use FoF\Seo\SeoMeta\SeoMeta;
use FoF\Seo\SeoProperties;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Subscribe to discussion creation, update or deleted.
 */
class DiscussionSubscriber
{
    public function __construct(
        private readonly SeoProperties $seoProperties,
    ) {
    }

    /**
     * Subscribe to events.
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(DiscussionEvent\Deleting::class, [$this, 'onModelEvent']);
        $events->listen(DiscussionEvent\Started::class, [$this, 'onModelEvent']);
        $events->listen(DiscussionEvent\Renamed::class, [$this, 'onModelEvent']);
        $events->listen(Created::class, [$this, 'onMetaCreated']);
    }

    /**
     * Handle model event.
     */
    public function onModelEvent(object $event): void
    {
        // Find meta
        $meta = SeoMeta::findOneByModel($event->discussion);

        // Find and delete meta-data
        if ($event::class === DiscussionEvent\Deleting::class) {
            if ($meta) {
                $meta->delete();
            }

            return;
        }

        // Create new meta by model
        if (!$meta) {
            $meta = SeoMeta::buildByModel($event->discussion);
        }

        // Do not auto update
        if (!$meta->auto_update_data) {
            return;
        }

        $this->updateMeta($meta, $event->discussion);

        $meta->save();
    }

    /**
     * Handle meta created event.
     */
    public function onMetaCreated(Created $event): void
    {
        if ($event->objectType !== 'discussions') {
            return;
        }

        $discussion = Discussion::find($event->objectId);

        if ($discussion === null) {
            return;
        }

        $this->updateMeta($event->seoMeta, $discussion);

        $event->seoMeta->save();
    }

    /**
     * Populate the SeoMeta row from the discussion's current state.
     */
    public function updateMeta(SeoMeta $meta, Discussion $discussion): void
    {
        $meta->title = $discussion->title;

        $meta->created_at = $discussion->created_at;

        $firstPost = $discussion->firstPost;

        // If a discussion has a first post, use edited_at time if intial post was more recent edited than the last post was posted
        if ($firstPost) {
            $meta->updated_at = $firstPost->edited_at > $discussion->last_posted_at ? $firstPost->edited_at : $discussion->last_posted_at;
        } else {
            $meta->updated_at = $discussion->last_posted_at;
        }

        // Set discussion description and image
        if ($firstPost instanceof CommentPost) {
            $content = $firstPost->formatContent();

            // Set page description
            $meta->description = $this->seoProperties->generateDescriptionFromContent($content);

            // Set estimated reading time
            $estimatedReadingTime = $this->seoProperties->getEstimatedReadingTime($content);

            // If higher than zero, update reading time
            if ($estimatedReadingTime > 0) {
                $meta->estimated_reading_time = $estimatedReadingTime;
            }

            // Only update image if source was set to auto and is not managed by a different extension
            if (!$meta->open_graph_image_source || $meta->open_graph_image_source === 'auto') {
                // Set page image
                if ($image = $this->seoProperties->getImageFromContent($content)) {
                    $meta->open_graph_image = $image;
                    $meta->open_graph_image_source = 'auto';
                }
            }
        }
    }
}
