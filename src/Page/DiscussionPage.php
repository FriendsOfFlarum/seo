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
        // Get discussion ID from params
        $discussionId = Arr::get($request->getQueryParams(), 'id');

        try {
            // Find discussion
            $discussion = $this->discussionRepository->findOrFail($discussionId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Do nothing, no model found
            return;
        }

        $tagsEnabled = $this->extensionManager->isEnabled('flarum-tags');
        $enableBestAnswer = $this->extensionManager->isEnabled('fof-best-answer');

        /** @var Collection<Tag> $discussionTags */
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

            // Like count as a LikeAction interaction, when likes are available.
            if ($this->extensionManager->isEnabled('flarum-likes')) {
                $interactionStatistic[] = [
                    '@type'                => 'InteractionCounter',
                    'interactionType'      => 'https://schema.org/LikeAction',
                    'userInteractionCount' => $firstPost->likes()->count(),
                ];
            }
        }

        $properties->setSchemaJson('interactionStatistic', $interactionStatistic);

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
}
