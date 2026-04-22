<?php

namespace FoF\Seo\Subscribers;

use FoF\Seo\SeoMeta\SeoMeta;
use Flarum\Post\Event as PostEvent;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Subscribe to post deleting, posted or revised
 */
class PostSubscriber
{
    public function __construct(
        private DiscussionSubscriber $discussionSubscriber
    ) {}

    /**
     * Subscribe to events
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(PostEvent\Deleting::class, [$this, 'onModelEvent']);
        $events->listen(PostEvent\Posted::class, [$this, 'onModelEvent']);
        $events->listen(PostEvent\Revised::class, [$this, 'onModelEvent']);
    }

    /**
     * Handle model event
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
