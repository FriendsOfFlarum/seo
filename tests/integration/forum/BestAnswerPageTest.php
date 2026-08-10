<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Tests\integration\forum;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Foundation\Application;
use Flarum\Post\Post;
use Flarum\Tags\Tag;
use FoF\Seo\Tests\integration\ForumHtmlTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests the optional fof/best-answer integration.
 *
 * When the "crawl all posts" setting (`seo_post_crawler`) is enabled and a
 * discussion lives in a Q&A tag, the DiscussionBestAnswerPage driver emits a
 * schema.org `QAPage` describing the question and its answers — instead of the
 * regular `DiscussionForumPosting`. This is what lets Google show the accepted
 * answer in search results, so the structure matters.
 */
class BestAnswerPageTest extends ForumHtmlTestCase
{
    private const QNA_TAG_ID = 1;
    private const PLAIN_TAG_ID = 2;

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-tags', 'fof-best-answer', 'fof-seo');

        $this->setting('forum_title', 'Example Forum');
        $this->setting('forum_description', 'An example forum.');
    }

    /**
     * Seed a Q&A discussion: a question (post 1), an accepted answer (post 2)
     * and a further suggested answer (post 3).
     *
     * @param array<string, mixed> $extra extra discussion column overrides
     */
    private function seedQnaDiscussion(array $extra = []): void
    {
        $now = Carbon::now();

        $this->prepareDatabase([
            Tag::class => [
                ['id' => self::QNA_TAG_ID, 'name' => 'Questions', 'slug' => 'questions', 'description' => null, 'color' => '#000', 'position' => 0, 'is_restricted' => false, 'is_hidden' => false, 'is_qna' => true],
                ['id' => self::PLAIN_TAG_ID, 'name' => 'Chatter', 'slug' => 'chatter', 'description' => null, 'color' => '#000', 'position' => 1, 'is_restricted' => false, 'is_hidden' => false, 'is_qna' => false],
            ],
            Discussion::class => [
                array_merge([
                    'id'                  => 1,
                    'title'               => 'How do I bake bread?',
                    'slug'                => 'how-do-i-bake-bread',
                    'user_id'             => 1,
                    'first_post_id'       => 1,
                    'comment_count'       => 3,
                    'best_answer_post_id' => 2,
                    'created_at'          => $now,
                    'last_posted_at'      => $now,
                ], $extra),
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>How do I bake bread?</p></t>', 'created_at' => $now],
                ['id' => 2, 'discussion_id' => 1, 'number' => 2, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Use flour, water and yeast.</p></t>', 'created_at' => $now],
                ['id' => 3, 'discussion_id' => 1, 'number' => 3, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Try a sourdough starter.</p></t>', 'created_at' => $now],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => self::QNA_TAG_ID],
            ],
        ]);
    }

    #[Test]
    public function qa_discussion_emits_qapage_schema_when_post_crawler_enabled(): void
    {
        $this->setting('seo_post_crawler', '1');
        $this->seedQnaDiscussion();

        $html = $this->fetchForumHtml('/d/1-how-do-i-bake-bread');

        $qaPage = $this->findSchemaEntry($html, 'QAPage');
        $this->assertNotNull($qaPage, 'Expected a QAPage entry for a Q&A discussion.');

        // The regular discussion schema should NOT also be present.
        $this->assertNull(
            $this->findSchemaEntry($html, 'DiscussionForumPosting'),
            'A Q&A page should not also emit DiscussionForumPosting.'
        );

        $this->assertSame('article', $this->findMetaByProperty($html, 'og:type'));

        // GH #92 — the QAPage must carry a top-level `url`.
        $this->assertSame('http://localhost/d/1-how-do-i-bake-bread', $qaPage['url'] ?? null);

        $question = $qaPage['mainEntity'] ?? [];
        $this->assertSame('Question', $question['@type'] ?? null);
        $this->assertSame('How do I bake bread?', $question['name'] ?? null);
        // answerCount = comment_count - 1
        $this->assertSame(2, $question['answerCount'] ?? null);
    }

    /**
     * A discussion tagged only with a *child* of a Q&A tag should still be
     * treated as Q&A and emit a QAPage. Regression lock for GH #108.
     */
    #[Test]
    public function qa_page_emitted_for_discussion_in_child_of_qna_tag(): void
    {
        $this->setting('seo_post_crawler', '1');

        $now = Carbon::now();

        $this->prepareDatabase([
            Tag::class => [
                ['id' => 10, 'name' => 'Support', 'slug' => 'support', 'description' => null, 'color' => '#000', 'position' => 0, 'is_restricted' => false, 'is_hidden' => false, 'is_qna' => true],
                ['id' => 11, 'name' => 'Install', 'slug' => 'install', 'description' => null, 'color' => '#000', 'position' => 0, 'parent_id' => 10, 'is_restricted' => false, 'is_hidden' => false, 'is_qna' => false],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'How to install?', 'slug' => 'how-to-install', 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 2, 'best_answer_post_id' => 2, 'created_at' => $now, 'last_posted_at' => $now],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>How?</p></t>', 'created_at' => $now],
                ['id' => 2, 'discussion_id' => 1, 'number' => 2, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Like this.</p></t>', 'created_at' => $now],
            ],
            // Tagged only with the child tag (#11), whose parent (#10) is the Q&A tag.
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 11],
            ],
        ]);

        $this->assertNotNull(
            $this->findSchemaEntry($this->fetchForumHtml('/d/1-how-to-install'), 'QAPage'),
            'A discussion in a child of a Q&A tag should emit a QAPage.'
        );
    }

    #[Test]
    public function qa_page_marks_accepted_and_suggested_answers(): void
    {
        $this->setting('seo_post_crawler', '1');
        $this->seedQnaDiscussion();

        $html = $this->fetchForumHtml('/d/1-how-do-i-bake-bread');

        $question = $this->findSchemaEntry($html, 'QAPage')['mainEntity'] ?? [];

        // Post 2 is the best answer.
        $accepted = $question['acceptedAnswer'] ?? null;
        $this->assertSame('Answer', $accepted['@type'] ?? null);
        $this->assertStringContainsString('Use flour, water and yeast.', $accepted['text'] ?? '');

        // Without flarum-likes enabled, the upvote count is always zero.
        $this->assertSame(0, $accepted['upvoteCount'] ?? null);

        // Post 3 is a non-accepted (suggested) answer.
        $suggested = $question['suggestedAnswer'] ?? [];
        $this->assertCount(1, $suggested);
        $this->assertStringContainsString('Try a sourdough starter.', $suggested[0]['text'] ?? '');
    }

    /**
     * A deleted answer author still yields a "[deleted]" Person (with a name,
     * no url) rather than omitting `author` — `author.name` is required, and an
     * omitted author trips "Missing field 'author' (in suggestedAnswer)".
     */
    #[Test]
    public function answer_by_deleted_user_still_has_a_named_author(): void
    {
        $this->setting('seo_post_crawler', '1');

        $now = Carbon::now();

        $this->prepareDatabase([
            Tag::class => [
                ['id' => self::QNA_TAG_ID, 'name' => 'Questions', 'slug' => 'questions', 'description' => null, 'color' => '#000', 'position' => 0, 'is_restricted' => false, 'is_hidden' => false, 'is_qna' => true],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'How do I bake bread?', 'slug' => 'how-do-i-bake-bread', 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 2, 'best_answer_post_id' => 2, 'created_at' => $now, 'last_posted_at' => $now],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>How do I bake bread?</p></t>', 'created_at' => $now],
                // Accepted answer by a user that no longer exists (no row id 99).
                ['id' => 2, 'discussion_id' => 1, 'number' => 2, 'user_id' => 99, 'type' => 'comment', 'content' => '<t><p>Use flour and water.</p></t>', 'created_at' => $now],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => self::QNA_TAG_ID],
            ],
        ]);

        $accepted = $this->findSchemaEntry($this->fetchForumHtml('/d/1-how-do-i-bake-bread'), 'QAPage')['mainEntity']['acceptedAnswer'] ?? [];

        $this->assertSame('Answer', $accepted['@type'] ?? null);
        // author present: Person with a name, no url.
        $this->assertSame('Person', $accepted['author']['@type'] ?? null);
        $this->assertNotEmpty($accepted['author']['name'] ?? null);
        $this->assertArrayNotHasKey('url', $accepted['author']);
    }

    /**
     * The question author (`mainEntity.author`) follows the same rule: a
     * deleted starter still yields a "[deleted]" Person (name, no url).
     */
    #[Test]
    public function question_by_deleted_user_still_has_a_named_author(): void
    {
        $this->setting('seo_post_crawler', '1');

        $now = Carbon::now();

        $this->prepareDatabase([
            Tag::class => [
                ['id' => self::QNA_TAG_ID, 'name' => 'Questions', 'slug' => 'questions', 'description' => null, 'color' => '#000', 'position' => 0, 'is_restricted' => false, 'is_hidden' => false, 'is_qna' => true],
            ],
            Discussion::class => [
                // Started by a user that no longer exists (no row id 99).
                ['id' => 1, 'title' => 'How do I bake bread?', 'slug' => 'how-do-i-bake-bread', 'user_id' => 99, 'first_post_id' => 1, 'comment_count' => 2, 'best_answer_post_id' => 2, 'created_at' => $now, 'last_posted_at' => $now],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 99, 'type' => 'comment', 'content' => '<t><p>How do I bake bread?</p></t>', 'created_at' => $now],
                ['id' => 2, 'discussion_id' => 1, 'number' => 2, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Use flour and water.</p></t>', 'created_at' => $now],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => self::QNA_TAG_ID],
            ],
        ]);

        $question = $this->findSchemaEntry($this->fetchForumHtml('/d/1-how-do-i-bake-bread'), 'QAPage')['mainEntity'] ?? [];

        $this->assertSame('Question', $question['@type'] ?? null);
        // author present: Person with a name, no url.
        $this->assertSame('Person', $question['author']['@type'] ?? null);
        $this->assertNotEmpty($question['author']['name'] ?? null);
        $this->assertArrayNotHasKey('url', $question['author']);
    }

    /**
     * A live author must still carry a `url` pointing at their profile page —
     * the property Google recommends and that satisfies Search Console.
     */
    #[Test]
    public function author_of_a_live_user_carries_a_profile_url(): void
    {
        $this->setting('seo_post_crawler', '1');
        $this->seedQnaDiscussion();

        $question = $this->findSchemaEntry($this->fetchForumHtml('/d/1-how-do-i-bake-bread'), 'QAPage')['mainEntity'] ?? [];

        $questionAuthor = $question['author'] ?? null;
        $this->assertIsArray($questionAuthor);
        $this->assertSame('Person', $questionAuthor['@type'] ?? null);
        $this->assertArrayHasKey('url', $questionAuthor);
        $this->assertNotEmpty($questionAuthor['url']);

        $answerAuthor = $question['acceptedAnswer']['author'] ?? null;
        $this->assertIsArray($answerAuthor);
        $this->assertArrayHasKey('url', $answerAuthor);
        $this->assertNotEmpty($answerAuthor['url']);
    }

    /**
     * The Question and Answer `text` must be plain text rendered from the
     * stored markup — not the raw source. Markup is stripped, links reduce to
     * their visible label, and HTML entities are decoded (matching the
     * DiscussionForumPosting comment behaviour).
     */
    #[Test]
    public function question_and_answer_text_is_rendered_to_plain_text(): void
    {
        $this->setting('seo_post_crawler', '1');

        $now = Carbon::now();

        // The accepted answer contains a labelled markdown link. The raw stored
        // source keeps the `[label](url)` syntax, so the old strip_tags(content)
        // path would leak it; rendering to HTML reduces it to the label.
        $this->prepareDatabase([
            Tag::class => [
                ['id' => self::QNA_TAG_ID, 'name' => 'Questions', 'slug' => 'questions', 'description' => null, 'color' => '#000', 'position' => 0, 'is_restricted' => false, 'is_hidden' => false, 'is_qna' => true],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'How do I bake bread?', 'slug' => 'how-do-i-bake-bread', 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 2, 'best_answer_post_id' => 2, 'created_at' => $now, 'last_posted_at' => $now],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<r><p>Flour <STRONG>and</STRONG> water?</p></r>', 'created_at' => $now],
                ['id' => 2, 'discussion_id' => 1, 'number' => 2, 'user_id' => 1, 'type' => 'comment', 'content' => '<r><p>Yes &amp; yeast, read <URL url="https://example.com"><s>[</s>the docs<e>](https://example.com)</e></URL></p></r>', 'created_at' => $now],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => self::QNA_TAG_ID],
            ],
        ]);

        $question = $this->findSchemaEntry($this->fetchForumHtml('/d/1-how-do-i-bake-bread'), 'QAPage')['mainEntity'] ?? [];

        // Question text: markup stripped, plain text only.
        $this->assertSame('Flour and water?', $question['text'] ?? null);

        // Answer text: link reduced to its label, entity decoded, no markdown
        // link syntax leaked.
        $answer = $question['acceptedAnswer']['text'] ?? '';
        $this->assertSame('Yes & yeast, read the docs', $answer);
        $this->assertStringNotContainsString('](', $answer);
        $this->assertStringNotContainsString('https://example.com', $answer);
        $this->assertStringNotContainsString('&amp;', $answer);
    }

    /**
     * Google's "Either 'text', 'image' or 'video' should be specified" applies
     * to QAPage Question/Answer nodes too. An image-only accepted answer must
     * carry `image` rather than an empty `text`.
     */
    #[Test]
    public function image_only_answer_emits_image_and_no_empty_text(): void
    {
        $this->extension('flarum-markdown');
        $this->setting('seo_post_crawler', '1');

        $now = Carbon::now();

        $this->prepareDatabase([
            Tag::class => [
                ['id' => self::QNA_TAG_ID, 'name' => 'Questions', 'slug' => 'questions', 'description' => null, 'color' => '#000', 'position' => 0, 'is_restricted' => false, 'is_hidden' => false, 'is_qna' => true],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'How do I bake bread?', 'slug' => 'how-do-i-bake-bread', 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 2, 'best_answer_post_id' => 2, 'created_at' => $now, 'last_posted_at' => $now],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>How?</p></t>', 'created_at' => $now],
                // Accepted answer that is only an image, no caption.
                ['id' => 2, 'discussion_id' => 1, 'number' => 2, 'user_id' => 1, 'type' => 'comment', 'content' => '<r><p><IMG alt="" src="https://example.com/loaf.png"><s>![</s><e>](https://example.com/loaf.png)</e></IMG></p></r>', 'created_at' => $now],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => self::QNA_TAG_ID],
            ],
        ]);

        $accepted = $this->findSchemaEntry($this->fetchForumHtml('/d/1-how-do-i-bake-bread'), 'QAPage')['mainEntity']['acceptedAnswer'] ?? [];

        $this->assertSame('Answer', $accepted['@type'] ?? null);
        // No empty `text`; the image is emitted instead.
        $this->assertArrayNotHasKey('text', $accepted);
        $this->assertSame('https://example.com/loaf.png', $accepted['image'] ?? null);
    }

    /**
     * The same rule applies to the Question node: an image-only first post
     * emits `image` (alongside the always-present `name`/title) rather than an
     * empty `text`.
     */
    #[Test]
    public function image_only_question_emits_image_and_no_empty_text(): void
    {
        $this->extension('flarum-markdown');
        $this->setting('seo_post_crawler', '1');

        $now = Carbon::now();

        $this->prepareDatabase([
            Tag::class => [
                ['id' => self::QNA_TAG_ID, 'name' => 'Questions', 'slug' => 'questions', 'description' => null, 'color' => '#000', 'position' => 0, 'is_restricted' => false, 'is_hidden' => false, 'is_qna' => true],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'Spot the difference', 'slug' => 'spot-the-difference', 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 2, 'best_answer_post_id' => 2, 'created_at' => $now, 'last_posted_at' => $now],
            ],
            Post::class => [
                // First post (the question) is only an image.
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<r><p><IMG alt="" src="https://example.com/q.png"><s>![</s><e>](https://example.com/q.png)</e></IMG></p></r>', 'created_at' => $now],
                ['id' => 2, 'discussion_id' => 1, 'number' => 2, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>It is the left one.</p></t>', 'created_at' => $now],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => self::QNA_TAG_ID],
            ],
        ]);

        $question = $this->findSchemaEntry($this->fetchForumHtml('/d/1-spot-the-difference'), 'QAPage')['mainEntity'] ?? [];

        $this->assertSame('Question', $question['@type'] ?? null);
        // The title is always present as `name`...
        $this->assertSame('Spot the difference', $question['name'] ?? null);
        // ...and the image stands in for the absent text.
        $this->assertArrayNotHasKey('text', $question);
        $this->assertSame('https://example.com/q.png', $question['image'] ?? null);
    }

    /**
     * When the question body renders to the same string as the title, the
     * `text` is dropped so it doesn't duplicate `name` — Google rejects
     * identical sibling values ("unique values are required (in mainEntity)").
     */
    #[Test]
    public function question_text_equal_to_name_is_dropped(): void
    {
        $this->setting('seo_post_crawler', '1');

        $now = Carbon::now();

        $this->prepareDatabase([
            Tag::class => [
                ['id' => self::QNA_TAG_ID, 'name' => 'Questions', 'slug' => 'questions', 'description' => null, 'color' => '#000', 'position' => 0, 'is_restricted' => false, 'is_hidden' => false, 'is_qna' => true],
            ],
            Discussion::class => [
                // Title equals the first post body once rendered to plain text.
                ['id' => 1, 'title' => 'Is it broken?', 'slug' => 'is-it-broken', 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 2, 'best_answer_post_id' => 2, 'created_at' => $now, 'last_posted_at' => $now],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Is it broken?</p></t>', 'created_at' => $now],
                ['id' => 2, 'discussion_id' => 1, 'number' => 2, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Yes.</p></t>', 'created_at' => $now],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => self::QNA_TAG_ID],
            ],
        ]);

        $question = $this->findSchemaEntry($this->fetchForumHtml('/d/1-is-it-broken'), 'QAPage')['mainEntity'] ?? [];

        $this->assertSame('Is it broken?', $question['name'] ?? null);
        // text is omitted because it would equal name.
        $this->assertArrayNotHasKey('text', $question);
    }

    /**
     * A suggested answer that renders to no text/image/video can't form a valid
     * Answer node, so it's dropped rather than emitted without `text` ("Missing
     * field 'text' (in suggestedAnswer)"). The accepted answer is unaffected.
     */
    #[Test]
    public function contentless_suggested_answer_is_dropped(): void
    {
        $this->setting('seo_post_crawler', '1');

        $now = Carbon::now();

        $this->prepareDatabase([
            Tag::class => [
                ['id' => self::QNA_TAG_ID, 'name' => 'Questions', 'slug' => 'questions', 'description' => null, 'color' => '#000', 'position' => 0, 'is_restricted' => false, 'is_hidden' => false, 'is_qna' => true],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'How do I bake bread?', 'slug' => 'how-do-i-bake-bread', 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 3, 'best_answer_post_id' => 2, 'created_at' => $now, 'last_posted_at' => $now],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>How?</p></t>', 'created_at' => $now],
                ['id' => 2, 'discussion_id' => 1, 'number' => 2, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Use flour.</p></t>', 'created_at' => $now],
                // Suggested answer that renders to nothing.
                ['id' => 3, 'discussion_id' => 1, 'number' => 3, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p> </p></t>', 'created_at' => $now],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => self::QNA_TAG_ID],
            ],
        ]);

        $question = $this->findSchemaEntry($this->fetchForumHtml('/d/1-how-do-i-bake-bread'), 'QAPage')['mainEntity'] ?? [];

        // Accepted answer present; the empty suggested answer is dropped.
        $this->assertArrayHasKey('text', $question['acceptedAnswer'] ?? []);
        $this->assertSame([], $question['suggestedAnswer'] ?? ['x']);
    }

    /**
     * Answer URLs carry a per-post `#post-{id}` fragment so they are unique by
     * construction. On legacy discussions post `number` can collide (two
     * answers → same `near=` URL), which made Google report "Identical property
     * values ... unique values are required (in mainEntity)"; anchoring on the
     * always-unique post id removes that risk regardless of numbering.
     */
    #[Test]
    public function answer_urls_are_anchored_on_the_unique_post_id(): void
    {
        $this->setting('seo_post_crawler', '1');
        $this->seedQnaDiscussion();

        $question = $this->findSchemaEntry($this->fetchForumHtml('/d/1-how-do-i-bake-bread'), 'QAPage')['mainEntity'] ?? [];

        $accepted = $question['acceptedAnswer'] ?? [];
        $suggested = $question['suggestedAnswer'] ?? [];

        // The accepted answer is post 2, the suggested answer post 3.
        $this->assertStringEndsWith('#post-2', $accepted['url'] ?? '');
        $this->assertStringEndsWith('#post-3', $suggested[0]['url'] ?? '');

        // Every answer URL is unique.
        $urls = array_merge([$accepted['url'] ?? null], array_column($suggested, 'url'));
        $this->assertSame($urls, array_values(array_unique($urls)));
    }

    /**
     * The optional flarum/likes integration: when it's enabled, an answer's
     * `upvoteCount` reflects its like count.
     */
    #[Test]
    public function upvote_count_reflects_likes_when_likes_enabled(): void
    {
        $this->extension('flarum-likes');
        $this->setting('seo_post_crawler', '1');

        $this->seedQnaDiscussion();

        // One like on the accepted answer (post 2), from the admin user.
        $this->prepareDatabase([
            'post_likes' => [
                ['post_id' => 2, 'user_id' => 1],
            ],
        ]);

        $html = $this->fetchForumHtml('/d/1-how-do-i-bake-bread');

        $accepted = $this->findSchemaEntry($html, 'QAPage')['mainEntity']['acceptedAnswer'] ?? [];

        $this->assertSame(1, $accepted['upvoteCount'] ?? null);
    }

    /**
     * Seed a Q&A discussion with a configurable number of answer posts, so we
     * can prove the rendered query count does not scale with answers (no N+1).
     */
    private function seedQnaWithAnswers(int $discussionId, string $slug, int $answers): void
    {
        $now = Carbon::now();
        $base = $discussionId * 100;

        $posts = [
            ['id' => $base + 1, 'discussion_id' => $discussionId, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Question?</p></t>', 'created_at' => $now],
        ];
        $likes = [];

        for ($i = 1; $i <= $answers; $i++) {
            $postId = $base + 1 + $i;
            $posts[] = ['id' => $postId, 'discussion_id' => $discussionId, 'number' => $i + 1, 'user_id' => 1, 'type' => 'comment', 'content' => "<t><p>Answer {$i}.</p></t>", 'created_at' => $now];
            // A like on every answer — exercises the per-post likes relation.
            $likes[] = ['post_id' => $postId, 'user_id' => 1];
        }

        $this->prepareDatabase([
            Tag::class => [
                ['id' => self::QNA_TAG_ID, 'name' => 'Questions', 'slug' => 'questions', 'description' => null, 'color' => '#000', 'position' => 0, 'is_restricted' => false, 'is_hidden' => false, 'is_qna' => true],
            ],
            Discussion::class => [
                ['id' => $discussionId, 'title' => 'Q '.$slug, 'slug' => $slug, 'user_id' => 1, 'first_post_id' => $base + 1, 'comment_count' => $answers + 1, 'best_answer_post_id' => $base + 2, 'created_at' => $now, 'last_posted_at' => $now],
            ],
            Post::class          => $posts,
            'discussion_tag'     => [['discussion_id' => $discussionId, 'tag_id' => self::QNA_TAG_ID]],
            'post_likes'         => $likes,
        ]);
    }

    private function countQueriesFor(string $path): int
    {
        /** @var \Illuminate\Database\Connection $db */
        $db = $this->database();
        $db->flushQueryLog();
        $db->enableQueryLog();
        $this->fetchForumHtml($path);
        $count = count($db->getQueryLog());
        $db->disableQueryLog();

        return $count;
    }

    /**
     * Regression guard against an N+1 over answer posts (their `user` and
     * `likes` relations). The query count for an 8-answer Q&A page must not be
     * materially higher than for a 2-answer one.
     */
    #[Test]
    public function qa_page_does_not_issue_per_answer_queries(): void
    {
        // The per-answer N+1 this guards against lives in core: UserResource's
        // `editCredentials`/`isAdmin` checks read `$user->groups` per serialized
        // author. flarum/framework#4696 (in 2.0.0-rc.3) fixed this for the direct
        // JSON:API posts endpoint by eager-loading `user.groups` — but it does
        // NOT cover the forum HTML render path. There the same authors are
        // serialized again through extra documents (the discussion's own `user`,
        // `firstPost.user`, …) as `User` instances that lack the eager-loaded
        // `groups` relation, so the lazy per-author query fires again. Tracked in
        // flarum/framework#4724; expected to land in 2.0.0-rc.4. Skip until then.
        if (version_compare(Application::VERSION, '2.0.0-rc.4', '<')) {
            $this->markTestSkipped('Core N+1 in UserResource editCredentials/isAdmin on the forum render path — #4696 (rc.3) does not cover it; tracked in flarum/framework#4724, expected in 2.0.0-rc.4');
        }

        $this->extension('flarum-likes');
        $this->setting('seo_post_crawler', '1');

        $this->seedQnaWithAnswers(1, 'small', 2);
        $this->seedQnaWithAnswers(2, 'large', 8);

        // Warm both discussion routes, not just the index. A discussion page
        // touches caches the index does not, so the first *discussion* measured
        // absorbs a handful of one-time queries — locally 57 on the first pass
        // and 53 on every pass after. That swing is larger than the tolerance
        // below, so whichever page happened to go first decided whether this
        // test passed, which made it flaky.
        $this->fetchForumHtml('/');
        $this->countQueriesFor('/d/1-small');
        $this->countQueriesFor('/d/2-large');

        $small = $this->countQueriesFor('/d/1-small');
        $large = $this->countQueriesFor('/d/2-large');

        // Measured warm, the count is flat in the number of answers: 2, 4, 8 and
        // 16 answers all render in the same number of queries, so the expected
        // delta is 0. The tolerance is slack for incidental variance, well under
        // the 6 extra queries an N+1 over the 6 extra answers would cost.
        $this->assertLessThanOrEqual(
            3,
            $large - $small,
            'Query count grew by '.($large - $small).' between a 2-answer and an 8-answer Q&A page — looks like an N+1 over answer posts.'
        );
    }

    /**
     * Optional fof/discussion-views integration on the Q&A path.
     */
    #[Test]
    public function qa_page_includes_view_count_when_discussion_views_enabled(): void
    {
        $this->extension('fof-discussion-views');
        $this->setting('seo_post_crawler', '1');

        $this->seedQnaDiscussion(['view_count' => 999]);

        $qaPage = $this->findSchemaEntry($this->fetchForumHtml('/d/1-how-do-i-bake-bread'), 'QAPage');

        $view = null;
        foreach ((array) ($qaPage['interactionStatistic'] ?? []) as $stat) {
            if (($stat['interactionType'] ?? null) === 'https://schema.org/ViewAction') {
                $view = $stat;
            }
        }

        $this->assertNotNull($view, 'Expected a ViewAction InteractionCounter on the QAPage.');
        // 999 seeded + 1: rendering the page is itself a view, which
        // fof/discussion-views counts before we read the (current) count.
        $this->assertSame(1000, $view['userInteractionCount'] ?? null);
    }

    /**
     * Optional fof/discussion-language integration on the Q&A path.
     *
     * @TODO Disabled until fof/discussion-language is released for Flarum 2.0.
     */
    /*
    #[Test]
    public function qa_page_in_language_reflects_discussion_language_when_enabled(): void
    {
        $this->extension('fof-discussion-language');
        $this->setting('seo_post_crawler', '1');

        $this->prepareDatabase([
            'discussion_languages' => [
                ['id' => 1, 'code' => 'fr'],
            ],
        ]);
        $this->seedQnaDiscussion(['language_id' => 1]);

        $qaPage = $this->findSchemaEntry($this->fetchForumHtml('/d/1-how-do-i-bake-bread'), 'QAPage');

        $this->assertSame('fr', $qaPage['inLanguage'] ?? null);
    }
    */

    #[Test]
    public function qa_question_upvote_count_reflects_first_post_likes(): void
    {
        $this->extension('flarum-likes');
        $this->setting('seo_post_crawler', '1');

        $this->seedQnaDiscussion();

        // A like on the question itself (the first post).
        $this->prepareDatabase([
            'post_likes' => [
                ['post_id' => 1, 'user_id' => 1],
            ],
        ]);

        $question = $this->findSchemaEntry($this->fetchForumHtml('/d/1-how-do-i-bake-bread'), 'QAPage')['mainEntity'] ?? [];

        $this->assertSame(1, $question['upvoteCount'] ?? null);
    }

    /**
     * Even with the post crawler OFF, a Q&A discussion that has an accepted
     * best answer should emit a QAPage with that answer as `acceptedAnswer`.
     * The best-answer post is already loaded, so this is cheap and gives Google
     * the richest result for Q&A threads without enabling full post crawling.
     */
    #[Test]
    public function qa_discussion_with_accepted_answer_emits_qapage_even_with_crawler_off(): void
    {
        // seo_post_crawler defaults to off.
        $this->seedQnaDiscussion(); // best_answer_post_id => 2

        $html = $this->fetchForumHtml('/d/1-how-do-i-bake-bread');

        $qaPage = $this->findSchemaEntry($html, 'QAPage');
        $this->assertNotNull($qaPage, 'A Q&A discussion with an accepted answer should emit QAPage even with the crawler off.');
        $this->assertNull(
            $this->findSchemaEntry($html, 'DiscussionForumPosting'),
            'QAPage and DiscussionForumPosting must not both be emitted.'
        );

        $accepted = $qaPage['mainEntity']['acceptedAnswer'] ?? null;
        $this->assertSame('Answer', $accepted['@type'] ?? null);
        $this->assertStringContainsString('Use flour, water and yeast.', $accepted['text'] ?? '');

        // With the crawler off we only surface the accepted answer, not the
        // full thread of suggested answers (that is the crawler-on behaviour).
        $this->assertSame([], $qaPage['mainEntity']['suggestedAnswer'] ?? null);
    }

    /**
     * A Q&A discussion with NO accepted answer stays a plain
     * DiscussionForumPosting when the crawler is off (decided behaviour: we do
     * not emit a QAPage without an answer).
     */
    #[Test]
    public function qa_discussion_without_accepted_answer_falls_back_to_forum_posting_when_crawler_off(): void
    {
        // Q&A discussion, crawler off, but no best answer set.
        $this->seedQnaDiscussion(['best_answer_post_id' => null]);

        $html = $this->fetchForumHtml('/d/1-how-do-i-bake-bread');

        $this->assertNull(
            $this->findSchemaEntry($html, 'QAPage'),
            'No QAPage should be emitted for a Q&A discussion without an accepted answer.'
        );
        $this->assertNotNull(
            $this->findSchemaEntry($html, 'DiscussionForumPosting'),
            'Without an accepted answer the discussion falls back to DiscussionForumPosting.'
        );
    }

    #[Test]
    public function discussion_outside_a_qna_tag_falls_back_to_forum_posting(): void
    {
        $this->setting('seo_post_crawler', '1');

        $now = Carbon::now();

        // A discussion in a non-Q&A tag, even with the crawler enabled.
        $this->prepareDatabase([
            Tag::class => [
                ['id' => self::PLAIN_TAG_ID, 'name' => 'Chatter', 'slug' => 'chatter', 'description' => null, 'color' => '#000', 'position' => 0, 'is_restricted' => false, 'is_hidden' => false, 'is_qna' => false],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'Just chatting', 'slug' => 'just-chatting', 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1, 'created_at' => $now, 'last_posted_at' => $now],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Hello.</p></t>', 'created_at' => $now],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => self::PLAIN_TAG_ID],
            ],
        ]);

        $html = $this->fetchForumHtml('/d/1-just-chatting');

        $this->assertNull(
            $this->findSchemaEntry($html, 'QAPage'),
            'Non-Q&A discussions must not become a QAPage.'
        );
        $this->assertNotNull(
            $this->findSchemaEntry($html, 'DiscussionForumPosting'),
            'Non-Q&A discussions fall back to DiscussionForumPosting.'
        );
    }
}
