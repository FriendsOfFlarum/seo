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

use Flarum\Post\Event as PostEvent;
use FoF\Seo\SeoMeta\SeoMeta;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Subscribe to post deleting, posted or revised.
 */
class PostSubscriber
{
    public function __construct(
        private readonly DiscussionSubscriber $discussionSubscriber,
    ) {
    }

    /**
     * Subscribe to events.
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(PostEvent\Deleting::class, [$this, 'onModelEvent']);
        $events->listen(PostEvent\Posted::class, [$this, 'onModelEvent']);
        $events->listen(PostEvent\Revised::class, [$this, 'onModelEvent']);
    }

    /**
     * Handle model event.
     */
    public function onModelEvent(object $event): void
    {
        $meta = SeoMeta::findOneByModel($event->post->discussion);

        if (!$meta) {
            $meta = SeoMeta::buildByModel($event->post->discussion);
        }

        if (!$meta->auto_update_data) {
            return;
        }

        $this->discussionSubscriber->updateMeta($meta, $event->post->discussion);

        $meta->save();
    }
}
