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
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;

class DiscussionBestAnswerPage implements PageDriverInterface
{
    use DispatchEventsTrait;

    protected SettingsRepositoryInterface $settingsRepositoryInterface;

    protected DiscussionRepository $discussionRepository;

    protected UserRepository $userRepository;

    protected ExtensionManager $extensionManager;

    protected UrlGenerator $urlGenerator;

    protected DiscussionPage $discussionFallback;

    protected SlugManager $slugManager;

    public function __construct(
        SettingsRepositoryInterface $settingsRepositoryInterface,
        DiscussionRepository $discussionRepository,
        UserRepository $userRepository,
        ExtensionManager $extensionManager,
        UrlGenerator $urlGenerator,
        DiscussionPage $discussionFallback,
        Dispatcher $events,
        SlugManager $slugManager
    ) {
        $this->settingsRepositoryInterface = $settingsRepositoryInterface;
        $this->discussionRepository = $discussionRepository;
        $this->userRepository = $userRepository;
        $this->extensionManager = $extensionManager;
        $this->urlGenerator = $urlGenerator;
        $this->discussionFallback = $discussionFallback;
        $this->events = $events;
        $this->slugManager = $slugManager;
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
        $discussionId = Arr::get($request->getQueryParams(), 'id');

        try {
            // Find discussion
            $discussion = $this->discussionRepository->findOrFail($discussionId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Do nothing, no model found
            return;
        }

        // Fallback to simple discussions for not-answer tags
        $enableBestAnswer = $this->extensionManager->isEnabled('fof-best-answer');

        /** @var Collection<Tag> $discussionTags */
        $discussionTags = $discussion->tags;

        if (!$enableBestAnswer || !$discussionTags->contains(fn (Tag $tag) => (bool) $tag->is_qna)) {
            $this->discussionFallback->handle($request, $properties);

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
            'author'      => [
                '@type' => 'Person',
                'name'  => $discussion->user?->getDisplayNameAttribute(),
                'url'   => $discussion->user ? $this->urlGenerator->to('forum')->route('user', ['username' => $this->slugManager->forResource(User::class)->toSlug($discussion->user)]) : null,
            ],
            'answerCount' => $discussion->comment_count - 1,
        ];

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
        /** @var Collection<Post> $posts */
        $posts = $discussion->posts()
            ->where('number', '>', '1')->get();

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
                'author'      => [
                    '@type' => 'Person',
                    'name'  => $post->user ? $post->user->display_name : null,
                    'url'   => $post->user ? $this->urlGenerator->to('forum')->route('user', ['username' => $this->slugManager->forResource(User::class)->toSlug($post->user)]) : null,
                ],
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
    }
}
