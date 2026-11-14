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
use FoF\Seo\Indexability\IndexabilityPolicy;
use FoF\Seo\SeoMeta\SeoMeta;
use FoF\Seo\SeoProperties;
use FoF\Seo\Support\AuthorSchema;
use FoF\Seo\Support\PostMedia;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;

class DiscussionBestAnswerPage implements PageDriverInterface
{
    use DispatchEventsTrait;
    use LoadsMentionRelations;

    protected Dispatcher $events;

    public function __construct(
        protected readonly SettingsRepositoryInterface $settingsRepositoryInterface,
        protected readonly DiscussionRepository $discussionRepository,
        protected readonly UserRepository $userRepository,
        protected readonly ExtensionManager $extensionManager,
        protected readonly UrlGenerator $urlGenerator,
        Dispatcher $events,
        protected readonly SlugManager $slugManager,
        protected readonly IndexabilityPolicy $indexability,
        protected readonly AuthorSchema $authorSchema,
        protected readonly DiscussionPage $discussionPage,
        protected readonly Container $container,
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

        if (!$this->discussionPage->isQnaDiscussion($discussion)) {
            return;
        }

        /** @var Collection<int, Tag> $discussionTags */
        $discussionTags = $discussion->tags;

        $crawlerEnabled = $this->settingsRepositoryInterface->get('seo_post_crawler', 0) == 1;

        $enableLikes = $this->extensionManager->isEnabled('flarum-likes');

        $bestAnswerId = $discussion->best_answer_post_id;

        // The answers readers can see (`comments()` leaves out deleted and
        // private posts), with what rendering and describing each one reads (its
        // author, its mentions, its like count) loaded for all of them, to
        // avoid queries per answer. This page only runs with flarum/tags
        // enabled (see extensionDependencies()), so its mention relation exists.
        $answers = fn () => $discussion->comments()
            ->where('number', '>', '1')
            ->with(['user', ...$this->mentionRelations(true)])
            ->when($enableLikes, fn ($query) => $query->withCount('likes'))
            ->orderBy('number');

        /** @var Collection<int, Post> $posts */
        $posts = new Collection();

        // With the crawler off we only surface the accepted answer. With it on,
        // rendering each answer is the dominant page-load cost, so it's capped
        // as on other discussions; a value <= 0 disables the cap.
        if ($crawlerEnabled) {
            $limit = (int) $this->settingsRepositoryInterface->get('seo_post_crawler_limit', 100);
            $posts = $answers()->when($limit > 0, fn ($query) => $query->limit($limit))->get();
        }

        // The accepted answer is always included, wherever it falls.
        if ($bestAnswerId !== null && !$posts->contains('id', $bestAnswerId)) {
            $posts = $posts->merge($answers()->where('id', $bestAnswerId)->get());
        }

        $answerSchema = [];
        $replyMedia = [];

        foreach ($posts as $post) {
            /** @var Post $post */
            if (!$post instanceof CommentPost) {
                continue;
            }

            $replyMedia[$post->id] = PostMedia::schemaFields($post->formatContent());

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
            ] + $replyMedia[$post->id];

            // `author` is recommended on an Answer; AuthorSchema yields a
            // "[deleted]" Person for a removed user (with a name, so the
            // `author.name` requirement is met) rather than omitting it.
            $generatedPost['author'] = $this->authorSchema->forUser($post->user);

            // Upvote/like count
            $generatedPost['upvoteCount'] = $enableLikes ? (int) $post->getAttribute('likes_count') : 0;

            // An Answer needs text, image or video; one without is left out
            // ("Missing field 'text'"), accepted or not.
            if ($replyMedia[$post->id] === []) {
                continue;
            }

            if ($bestAnswerId === $post->id) {
                $answerSchema['acceptedAnswer'] = $generatedPost;
            } else {
                $answerSchema['suggestedAnswer'][] = $generatedPost;
            }
        }

        if ($answerSchema === []) {
            // Describe it like any other discussion, through whichever driver
            // is registered as `discussion`. A replacement that isn't a
            // DiscussionPage owns the page itself, so leave its output alone.
            // Resolved here, not injected: PageManager builds this driver.
            $discussionDriver = $this->container->make(PageManager::class)->getExtenders('discussion')['discussion'] ?? null;

            if ($discussionDriver instanceof DiscussionPage) {
                $discussionDriver->buildForumPosting($discussion, $properties, $crawlerEnabled ? $posts : null, $replyMedia);
            }

            return;
        }

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

        $properties->setSchemaJson('mainEntity', $mainEntity + $answerSchema);

        // Keep discussions in admin-excluded tags out of the index (GH #117).
        if ($this->indexability->tagsNoindexed($discussionTags)) {
            $properties->addRobotsDirectives(['noindex']);
        }
    }
}
