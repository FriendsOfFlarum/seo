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

use Flarum\Discussion\Discussion;
use Flarum\Http\RequestUtil;
use Flarum\Http\SlugManager;
use Flarum\Http\UrlGenerator;
use Flarum\Post\Post;
use FoF\Seo\Breadcrumb\TagBreadcrumb;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Psr\Http\Message\ServerRequestInterface;
use WeakMap;

/** @internal Shared by the drivers, with lifetime bounded by the request/model. */
class DiscussionContext
{
    /** @var WeakMap<ServerRequestInterface, Discussion|false> */
    private WeakMap $discussions;

    /** @var WeakMap<Discussion, TagBreadcrumb> */
    private WeakMap $breadcrumbs;

    public function __construct(
        private readonly SlugManager $slugs,
        private readonly UrlGenerator $url,
    ) {
        $this->discussions = new WeakMap();
        $this->breadcrumbs = new WeakMap();
    }

    public function resolve(ServerRequestInterface $request): ?Discussion
    {
        if (!isset($this->discussions[$request])) {
            $slug = $request->getQueryParams()['id'] ?? null;
            $discussion = false;

            if ($slug !== null) {
                try {
                    // Preserve the configured slug driver and its actor visibility.
                    $discussion = $this->slugs->forResource(Discussion::class)->fromSlug($slug, RequestUtil::getActor($request));
                } catch (ModelNotFoundException) {
                    // Cache misses as well as successful lookups.
                }
            }

            $this->discussions[$request] = $discussion;
        }

        return $this->discussions[$request] ?: null;
    }

    public function breadcrumb(Discussion $discussion): TagBreadcrumb
    {
        return $this->breadcrumbs[$discussion] ??= new TagBreadcrumb($this->url);
    }

    public function firstPost(Discussion $discussion, bool $likes, bool $gamification): ?Post
    {
        if (!$discussion->relationLoaded('firstPost')) {
            $counts = [];
            if ($likes) {
                $counts[] = 'likes';
            }
            if ($gamification) {
                $counts[] = 'upvotes';
            }
            $discussion->setRelation('firstPost', $discussion->firstPost()->withCount($counts)->first());
        }

        return $discussion->firstPost;
    }
}
