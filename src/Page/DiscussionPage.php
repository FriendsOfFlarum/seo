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

use Flarum\Database\Eloquent\Collection;
use Flarum\Discussion\DiscussionRepository;
use Flarum\Extension\ExtensionManager;
use Flarum\Foundation\DispatchEventsTrait;
use Flarum\Http\SlugManager;
use Flarum\Http\UrlGenerator;
use Flarum\Post\CommentPost;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Tags\Tag;
use Flarum\User\User;
use Flarum\User\UserRepository;
use FoF\Seo\SeoMeta\SeoMeta;
use FoF\Seo\SeoProperties;
use FoF\Seo\TagIndexingPolicy;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;

class DiscussionPage implements PageDriverInterface
{
    use DispatchEventsTrait;

    protected Dispatcher $events;

    public function __construct(
        protected readonly SettingsRepositoryInterface $settingsRepositoryInterface,
        protected readonly DiscussionRepository $discussionRepository,
        protected readonly UserRepository $userRepository,
        protected readonly ExtensionManager $extensionManager,
        protected readonly UrlGenerator $urlGenerator,
        Dispatcher $events,
        protected readonly SlugManager $slugManager,
        protected readonly TagIndexingPolicy $tagIndexingPolicy,
    ) {
        $this->events = $events;
    }

    public function extensionDependencies(): array
    {
        return [];
    }

    public function handleRoutes(): array
    {
        return ['discussion'];
    }

    public function handle(
        ServerRequestInterface $request,
        SeoProperties $properties
    ): void {
        // Get discussion ID from params. The route param is the `{id}-{slug}`
        // form (e.g. "1-bake-bread"); cast to int to extract the numeric id so
        // the lookup works regardless of database (SQLite won't coerce it).
        $discussionId = (int) Arr::get($request->getQueryParams(), 'id');

        try {
            // Find discussion
            $discussion = $this->discussionRepository->findOrFail($discussionId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Do nothing, no model found
            return;
        }

        $tagsEnabled = $this->extensionManager->isEnabled('flarum-tags');
        $enableBestAnswer = $this->extensionManager->isEnabled('fof-best-answer');
        $enableLikes = $this->extensionManager->isEnabled('flarum-likes');
        $enableGamification = $this->extensionManager->isEnabled('fof-gamification');

        /** @var Collection<int, Tag> $discussionTags */
        $discussionTags = $discussion->tags;

        // Defer to DiscussionBestAnswerPage only when it will actually emit a
        // QAPage for this discussion, i.e. when "crawl all posts" is enabled,
        // best-answer is installed, and this is a Q&A discussion. In every other
        // case (no best-answer, non-Q&A, crawler off) we emit the standard
        // DiscussionForumPosting here.
        if (
            $this->settingsRepositoryInterface->get('seo_post_crawler', 0) == 1 &&
            $tagsEnabled && $enableBestAnswer && $discussionTags->contains(fn (Tag $tag) => (bool) $tag->is_qna || (bool) $tag->parent?->is_qna)
        ) {
            return;
        }

        // Get seo-meta-date
        $seoMeta = SeoMeta::findByModelOrCreate(
            $discussion
        );

        // Run events in case the model was created
        $this->dispatchEventsFor($seoMeta);

        // Update ld-json
        $properties
            ->setSchemaJson('@type', 'DiscussionForumPosting')

            // Set page type article
            ->setMetaPropertyTag('og:type', 'article');

        // Generate data
        $properties->generateTagsFromMetaData($seoMeta);

        // Update topic url
        $properties->setUrl($this->urlGenerator->to('forum')->route('discussion', ['id' => $discussion->id.'-'.$discussion->slug]), false);

        // Optional fof/discussion-language integration: a discussion's own
        // language drives inLanguage, overriding the viewer's locale.
        if ($this->extensionManager->isEnabled('fof-discussion-language')) {
            $languageCode = data_get($discussion->getAttribute('language'), 'code');

            if ($languageCode !== null) {
                $properties->setSchemaJson('inLanguage', $languageCode);
            }
        }

        // Schema.org DiscussionForumPosting enrichment (Google forum guidelines).
        $properties->setSchemaJson('headline', $seoMeta->title ?? $discussion->title);

        $replyCount = max(0, $discussion->comment_count - 1);
        $properties->setSchemaJson('commentCount', $replyCount);

        $interactionStatistic = [
            [
                '@type'                => 'InteractionCounter',
                'interactionType'      => 'https://schema.org/CommentAction',
                'userInteractionCount' => $replyCount,
            ],
        ];

        // Optional fof/discussion-views integration: expose the view count.
        if ($this->extensionManager->isEnabled('fof-discussion-views')) {
            $interactionStatistic[] = [
                '@type'                => 'InteractionCounter',
                'interactionType'      => 'https://schema.org/ViewAction',
                'userInteractionCount' => (int) $discussion->getAttribute('view_count'),
            ];
        }

        $firstPost = $discussion->firstPost;

        if ($firstPost instanceof CommentPost) {
            // Full post text for the `text` property.
            $text = trim(strip_tags($firstPost->formatContent()));

            if ($text !== '') {
                $properties->setSchemaJson('text', $text);
            }

            // Like/upvote count as a LikeAction interaction. Combines flarum/likes
            // and fof/gamification upvotes when either (or both) is enabled.
            if ($enableLikes || $enableGamification) {
                $interactionStatistic[] = [
                    '@type'                => 'InteractionCounter',
                    'interactionType'      => 'https://schema.org/LikeAction',
                    'userInteractionCount' => $this->approvalCount($firstPost, $enableLikes, $enableGamification),
                ];
            }
        }

        $properties->setSchemaJson('interactionStatistic', $interactionStatistic);

        // Expose replies as schema.org Comment nodes (each with its like/upvote
        // count) when post crawling is enabled. This restores the per-reply
        // approval signal search engines use to surface standout replies (GH #130),
        // using the standards-compliant DiscussionForumPosting > comment structure.
        if ($this->settingsRepositoryInterface->get('seo_post_crawler', 0) == 1) {
            $countRelations = [];

            if ($enableLikes) {
                $countRelations[] = 'likes';
            }

            if ($enableGamification) {
                $countRelations[] = 'upvotes';
            }

            /** @var Collection<int, Post> $replies */
            $replies = $discussion->posts()
                ->where('number', '>', 1)
                ->where('type', 'comment')
                ->where('is_private', false)
                ->with('user')
                ->withCount($countRelations)
                ->orderBy('number')
                ->get();

            $comments = $replies->map(function (Post $post) use ($discussion, $enableLikes, $enableGamification) {
                $comment = [
                    '@type'       => 'Comment',
                    'text'        => trim(strip_tags($post->content)),
                    'dateCreated' => $post->created_at->toIso8601String(),
                    'url'         => $this->urlGenerator->to('forum')->route('discussion', ['id' => $discussion->id.'-'.$discussion->slug, 'near' => $post->number]),
                ];

                // Author, when the post still has one (skip for deleted users).
                if ($post->user !== null) {
                    $comment['author'] = [
                        '@type' => 'Person',
                        'name'  => $post->user->getAttribute('display_name'),
                        'url'   => $this->urlGenerator->to('forum')->route('user', ['username' => $this->slugManager->forResource(User::class)->toSlug($post->user)]),
                    ];
                }

                if ($enableLikes || $enableGamification) {
                    $count = 0;

                    if ($enableLikes) {
                        $count += (int) $post->getAttribute('likes_count');
                    }

                    if ($enableGamification) {
                        $count += (int) $post->getAttribute('upvotes_count');
                    }

                    $comment['interactionStatistic'] = [
                        '@type'                => 'InteractionCounter',
                        'interactionType'      => 'https://schema.org/LikeAction',
                        'userInteractionCount' => $count,
                    ];
                }

                return $comment;
            })->toArray();

            if (count($comments) > 0) {
                $properties->setSchemaJson('comment', $comments);
            }
        }

        try {
            // Add author to the page meta data
            $user = $discussion->user;

            // Set author data if found
            if ($user !== null) {
                // author: https://schema.org/author typeof: https://schema.org/Person
                $properties->setSchemaJson('author', [
                    '@type' => 'Person',
                    'name'  => $user->getDisplayNameAttribute(),
                    'url'   => $this->urlGenerator->to('forum')->route('user', ['username' => $this->slugManager->forResource(User::class)->toSlug($user)]),
                ]);
            }
        } catch (\Exception $e) {
            // User does not exists anymore
        }

        // Generate a breadcrum if discussion has tags
        if ($tagsEnabled && $discussionTags->count() >= 1) {
            $properties->generateSchemaBreadcrumb(
                $discussionTags->map(fn (Tag $tag) => [
                    'name' => $tag->name,
                    'url'  => $this->urlGenerator->to('forum')->route('tag', ['slug' => $tag->slug]),
                ])->toArray()
            );
        }

        // Keep discussions in admin-excluded tags out of the index (GH #117).
        // Overrides the robots directive set by generateTagsFromMetaData above.
        if ($tagsEnabled && $this->tagIndexingPolicy->shouldNoindex($discussionTags)) {
            $properties->setMetaTag('robots', 'noindex, follow');
        }
    }

    /**
     * Total community approval for a single post: flarum/likes likes plus
     * fof/gamification upvotes (downvotes excluded), for whichever is enabled.
     */
    private function approvalCount(Post $post, bool $enableLikes, bool $enableGamification): int
    {
        $count = 0;

        if ($enableLikes) {
            $count += $post->likes()->count();
        }

        if ($enableGamification) {
            // Positive gamification votes only (mirrors its `upvotes` relation).
            $count += $post->votes()->where('value', '>', 0)->count();
        }

        return $count;
    }
}
