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

use FoF\MergeDiscussions\Events\DiscussionWasMerged;
use FoF\Seo\SeoMeta\SeoMeta;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Refresh a discussion's meta when fof/merge-discussions merges others into it.
 *
 * A merge changes the discussion's posts, and with a merge by date its first
 * post, without any of the events the meta is otherwise refreshed on.
 */
class MergeDiscussionsSubscriber
{
    public function __construct(
        private readonly DiscussionSubscriber $discussionSubscriber,
    ) {
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(DiscussionWasMerged::class, [$this, 'onMerged']);
    }

    public function onMerged(DiscussionWasMerged $event): void
    {
        $meta = SeoMeta::findOneByModel($event->discussion);

        if (!$meta) {
            $meta = SeoMeta::buildByModel($event->discussion);
        }

        if (!$meta->auto_update_data) {
            return;
        }

        $this->discussionSubscriber->updateMeta($meta, $event->discussion);

        $meta->save();
    }
}
