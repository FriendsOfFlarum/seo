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
use Symfony\Contracts\Translation\TranslatorInterface;

class DiscussionBestAnswerPage implements PageDriverInterface
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
        protected readonly TranslatorInterface $translator,
    ) {
        $this->events = $events;
    }

    /**
     * Build a schema.org Person for a question/answer author. `author` is a
     * required field, so a deleted user falls back to the localized "[deleted]"
     * display name with no profile url rather than `name: null` (GH #140).
     *
     * @return array<string, string>
     */
    private function authorSchema(?User $user): array
    {
        if ($user === null) {
            return [
                '@type' => 'Person',
                'name'  => $this->translator->trans('core.lib.username.deleted_text'),
            ];
        }

        return [
            '@type' => 'Person',
            'name'  => $user->getDisplayNameAttribute(),
            'url'   => $this->urlGenerator->to('forum')->route('user', ['username' => $this->slugManager->forResource(User::class)->toSlug($user)]),
        ];
    }

    public function extensionDependencies(): array
    {
        return ['flarum-tags'];
    }

    public function handleRoutes(): array
    {
        return ['discussion'];
    }

    public function handle(
        ServerRequestInterface $request,
        SeoProperties $properties
    ): void {
        // Simple discussion tags is set up
        if ($this->settingsRepositoryInterface->get('seo_post_crawler', 0) == 0) {
            return;
        }

        // Get discussion ID from params
        // Cast to int to extract the numeric id from the `{id}-{slug}` route
        // param so the lookup works on all databases (SQLite won't coerce it).
        $discussionId = (int) Arr::get($request->getQueryParams(), 'id');

        try {
            // Find discussion
            $discussion = $this->discussionRepository->findOrFail($discussionId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Do nothing, no model found
            return;
        }

        // When best-answer isn't installed, DiscussionPage emits the standard
        // DiscussionForumPosting, so there's nothing for us to do.
        $enableBestAnswer = $this->extensionManager->isEnabled('fof-best-answer');

        if (!$enableBestAnswer) {
            return;
        }

        /** @var Collection<int, Tag> $discussionTags */
        $discussionTags = $discussion->tags;

        // Not a Q&A discussion — DiscussionPage already emitted DiscussionForumPosting.
        // A child of a Q&A tag counts as Q&A too (flarum-tags nests one level).
        if (!$discussionTags->contains(fn (Tag $tag) => (bool) $tag->is_qna || (bool) $tag->parent?->is_qna)) {
            return;
        }

        $enableLikes = $this->extensionManager->isEnabled('flarum-likes');

        // Get seo-meta-date
        $seoMeta = SeoMeta::findByModelOrCreate($discussion);

        // Run events in case the model was created
        $this->dispatchEventsFor($seoMeta);

        $firstPost = $discussion->firstPost;

        // Update ld-json
        $properties
            ->setSchemaJson('@type', 'QAPage')

            // Set page type article
            ->setMetaPropertyTag('og:type', 'article');

        // Generate data
        $properties->generateTagsFromMetaData($seoMeta);

        // Optional fof/discussion-views integration: expose the view count.
        if ($this->extensionManager->isEnabled('fof-discussion-views')) {
            $properties->setSchemaJson('interactionStatistic', [
                [
                    '@type'                => 'InteractionCounter',
                    'interactionType'      => 'https://schema.org/ViewAction',
                    'userInteractionCount' => (int) $discussion->getAttribute('view_count'),
                ],
            ]);
        }

        // Optional fof/discussion-language integration: a discussion's own
        // language drives inLanguage, overriding the viewer's locale.
        if ($this->extensionManager->isEnabled('fof-discussion-language')) {
            $languageCode = data_get($discussion->getAttribute('language'), 'code');

            if ($languageCode !== null) {
                $properties->setSchemaJson('inLanguage', $languageCode);
            }
        }

        // Get posted on and Last posted on
        $bestAnswerId = $discussion->best_answer_post_id;

        // Update topic url
        $properties->setUrl($this->urlGenerator->to('forum')->route('discussion', ['id' => $discussion->id.'-'.$discussion->slug]), false);

        // Schema
        $mainEntity = [
            '@type'       => 'Question',
            'name'        => $seoMeta->title,
            'text'        => $firstPost !== null ? strip_tags($firstPost->content) : '',
            'dateCreated' => $seoMeta->created_at,
            'author'      => $this->authorSchema($discussion->user),
            'answerCount' => $discussion->comment_count - 1,
        ];

        // Upvotes on the question itself (the first post), when likes are available.
        if ($enableLikes && $firstPost !== null) {
            $mainEntity['upvoteCount'] = $firstPost->likes()->count();
        }

        // Generate a breadcrumb if discussion has tags
        if ($discussionTags->count() >= 1) {
            $properties->generateSchemaBreadcrumb(
                $discussionTags->map(fn (Tag $tag) => [
                    'name' => $tag->name,
                    'url'  => $this->urlGenerator->to('forum')->route('tag', ['slug' => $tag->slug]),
                ])->toArray()
            );
        }

        // Only add suggested answers property if there are posts
        $mainEntity['suggestedAnswer'] = [];

        // Get all public comments for this discussion
        // Eager-load the author (and likes, when the extension is enabled)
        // relations referenced in the loop below to avoid an N+1 per answer post.
        $with = ['user'];
        if ($enableLikes) {
            $with[] = 'likes';
        }

        /** @var Collection<int, Post> $posts */
        $posts = $discussion->posts()
            ->where('number', '>', '1')
            ->with($with)
            ->get();

        foreach ($posts as $post) {
            /** @var Post $post */
            if ($post->is_private || $post->type !== 'comment') {
                continue;
            }

            // Temp post
            $generatedPost = [
                '@type'       => 'Answer',
                'text'        => strip_tags($post->content),
                'dateCreated' => $post->created_at->toIso8601String(),
                'url'         => $this->urlGenerator->to('forum')->route('discussion', ['id' => $discussion->id.'-'.$discussion->slug, 'near' => $post->number]),
                'author'      => $this->authorSchema($post->user),
            ];

            // Upvote/like count
            $generatedPost['upvoteCount'] = $enableLikes ? $post->likes->count() : 0;

            // Set accepted answer
            if ($bestAnswerId === $post->id) {
                $mainEntity['acceptedAnswer'] = $generatedPost;
            }
            // Add to answers
            else {
                $mainEntity['suggestedAnswer'][] = $generatedPost;
            }
        }

        $properties->setSchemaJson('mainEntity', $mainEntity);

        // Keep discussions in admin-excluded tags out of the index (GH #117).
        // Overrides the robots directive set by generateTagsFromMetaData above.
        if ($this->tagIndexingPolicy->shouldNoindex($discussionTags)) {
            $properties->setMetaTag('robots', 'noindex, follow');
        }
    }
}
