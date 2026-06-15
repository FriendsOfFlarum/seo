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
use Flarum\Discussion\Discussion as FlarumDiscussion;
use Flarum\Discussion\DiscussionRepository;
use Flarum\Extension\ExtensionManager;
use Flarum\Foundation\DispatchEventsTrait;
use Flarum\Http\RequestUtil;
use Flarum\Http\SlugManager;
use Flarum\Http\UrlGenerator;
use Flarum\Post\CommentPost;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Tags\Tag;
use Flarum\User\UserRepository;
use FoF\Seo\Breadcrumb\TagBreadcrumb;
use FoF\Seo\SeoMeta\SeoMeta;
use FoF\Seo\SeoProperties;
use FoF\Seo\Support\AuthorSchema;
use FoF\Seo\Support\PostMedia;
use FoF\Seo\TagIndexingPolicy;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;

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
        protected readonly AuthorSchema $authorSchema,
    ) {
        $this->events = $events;
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
        $slug = Arr::get($request->getQueryParams(), 'id');

        if ($slug === null) {
            return;
        }

        try {
            // Resolve through the configured discussion slug driver.
            /** @var FlarumDiscussion $discussion */
            $discussion = $this->slugManager->forResource(FlarumDiscussion::class)->fromSlug(
                $slug,
                RequestUtil::getActor($request)
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Do nothing, no model found
            return;
        }

        // The canonical discussion slug under the configured driver, for URLs.
        $discussionSlug = $this->slugManager->forResource(FlarumDiscussion::class)->toSlug($discussion);

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

        $crawlerEnabled = $this->settingsRepositoryInterface->get('seo_post_crawler', 0) == 1;

        // With the post crawler on we emit the full QAPage (every answer). With
        // it off we still emit a QAPage — but only when an accepted answer
        // exists, and only that answer, which is cheap because the best-answer
        // post is loaded on its own. A Q&A discussion with no accepted answer
        // falls through to DiscussionPage's DiscussionForumPosting.
        if (!$crawlerEnabled && $discussion->best_answer_post_id === null) {
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
        $properties->setUrl($this->urlGenerator->to('forum')->route('discussion', ['id' => $discussionSlug]), false);

        // Schema
        $mainEntity = [
            '@type'       => 'Question',
            'name'        => $seoMeta->title,
            'dateCreated' => $seoMeta->created_at,
            'answerCount' => $discussion->comment_count - 1,
        ];

        // text/image/video for the question (its first post).
        $questionMedia = $firstPost instanceof CommentPost
            ? PostMedia::schemaFields($firstPost->formatContent())
            : [];

        // Drop `text` when it merely repeats the question `name` — Google
        // rejects identical sibling values ("unique values are required").
        if (($questionMedia['text'] ?? null) === $mainEntity['name']) {
            unset($questionMedia['text']);
        }

        $mainEntity += $questionMedia;

        // `author` is required on the Question; AuthorSchema yields a
        // "[deleted]" Person for a removed starter rather than omitting it.
        $mainEntity['author'] = $this->authorSchema->forUser($discussion->user);

        // Upvotes on the question itself (the first post), when likes are available.
        if ($enableLikes && $firstPost !== null) {
            $mainEntity['upvoteCount'] = $firstPost->likes()->count();
        }

        // Breadcrumb: Home › Tags › {primary lineage} › {discussion title}, one
        // trail per primary lineage (secondary tags excluded).
        (new TagBreadcrumb($this->urlGenerator))->emitDiscussionTrails($properties, $discussionTags, $discussion->title);

        // Only add suggested answers property if there are posts
        $mainEntity['suggestedAnswer'] = [];

        // Get the answer posts for this discussion.
        // Eager-load the author (and likes, when the extension is enabled)
        // relations referenced in the loop below to avoid an N+1 per answer post.
        $with = ['user'];
        if ($enableLikes) {
            $with[] = 'likes';
        }

        $postsQuery = $discussion->posts()
            ->where('number', '>', '1')
            ->with($with);

        // With the crawler off we only surface the accepted answer, so load
        // just that post rather than the whole thread.
        if (!$crawlerEnabled) {
            $postsQuery->where('id', $bestAnswerId);
        }

        /** @var Collection<int, Post> $posts */
        $posts = $postsQuery->get();

        foreach ($posts as $post) {
            /** @var Post $post */
            if ($post->is_private || !$post instanceof CommentPost) {
                continue;
            }

            // Temp post. text/image/video from the rendered HTML so an
            // image-only answer carries `image` instead of an empty `text`
            // (Google's "Either 'text', 'image' or 'video'" rule).
            $generatedPost = [
                '@type'       => 'Answer',
                'dateCreated' => $post->created_at->toIso8601String(),
                // A `#post-{id}` fragment keeps each answer URL unique even when
                // post `number` collides on old discussions — Google requires
                // unique URLs across suggestedAnswer items.
                'url'         => $this->urlGenerator->to('forum')->route('discussion', ['id' => $discussionSlug, 'near' => $post->number]).'#post-'.$post->id,
            ] + PostMedia::schemaFields($post->formatContent());

            // `author` is recommended on an Answer; AuthorSchema yields a
            // "[deleted]" Person for a removed user (with a name, so the
            // `author.name` requirement is met) rather than omitting it.
            $generatedPost['author'] = $this->authorSchema->forUser($post->user);

            // Upvote/like count
            $generatedPost['upvoteCount'] = $enableLikes ? $post->likes->count() : 0;

            // Set accepted answer
            if ($bestAnswerId === $post->id) {
                $mainEntity['acceptedAnswer'] = $generatedPost;
            }
            // Add to answers — but only when the answer has the content an
            // Answer requires (text/image/video). A contentless suggested
            // answer would trip "Missing field 'text' (in suggestedAnswer)".
            elseif (isset($generatedPost['text']) || isset($generatedPost['image']) || isset($generatedPost['video'])) {
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
