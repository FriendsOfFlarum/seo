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
use Flarum\Post\Post;
use Flarum\User\User;
use FoF\Seo\Tests\integration\ForumHtmlTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * When "crawl all posts" is enabled, a discussion's replies are exposed as
 * schema.org `Comment` nodes on the `DiscussionForumPosting`, each carrying a
 * `LikeAction` upvote count. The count is sourced from flarum/likes or, when
 * present, fof/gamification's votes — restoring the per-reply approval signal
 * lost when the old QAPage-everywhere behaviour was removed (GH #130).
 */
class DiscussionCommentsTest extends ForumHtmlTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-seo');

        $this->setting('forum_title', 'Example Forum');
        $this->setting('seo_post_crawler', '1');
    }

    private function seedThread(): void
    {
        $this->prepareDatabase([
            User::class => [
                ['id' => 2, 'username' => 'alice', 'email' => 'a@example.com', 'password' => '$2y$10$LO59tiT7uggl6Oe23o/O6.utnF6ipngYjvMvaxo1TciKqBttDNKim', 'is_email_confirmed' => 1],
                ['id' => 3, 'username' => 'bob', 'email' => 'b@example.com', 'password' => '$2y$10$LO59tiT7uggl6Oe23o/O6.utnF6ipngYjvMvaxo1TciKqBttDNKim', 'is_email_confirmed' => 1],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'How do I bake bread', 'slug' => 'bake-bread', 'user_id' => 2, 'first_post_id' => 1, 'comment_count' => 3, 'created_at' => Carbon::now()],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>The question.</p></t>', 'created_at' => Carbon::now()],
                ['id' => 2, 'discussion_id' => 1, 'number' => 2, 'user_id' => 3, 'type' => 'comment', 'content' => '<t><p>The popular reply.</p></t>', 'created_at' => Carbon::now()],
                ['id' => 3, 'discussion_id' => 1, 'number' => 3, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Another reply.</p></t>', 'created_at' => Carbon::now()],
            ],
        ]);
    }

    #[Test]
    public function replies_are_not_exposed_when_post_crawler_is_disabled(): void
    {
        $this->setting('seo_post_crawler', '0');
        $this->seedThread();

        $entry = $this->findSchemaEntry($this->fetchForumHtml('/d/1-bake-bread'), 'DiscussionForumPosting');

        $this->assertNotNull($entry);
        $this->assertArrayNotHasKey('comment', $entry);
    }

    #[Test]
    public function replies_are_emitted_as_comment_nodes_when_crawler_enabled(): void
    {
        $this->extension('flarum-likes');
        $this->seedThread();

        $entry = $this->findSchemaEntry($this->fetchForumHtml('/d/1-bake-bread'), 'DiscussionForumPosting');

        $comments = $entry['comment'] ?? null;
        $this->assertIsArray($comments);
        $this->assertCount(2, $comments, 'Expected both replies as Comment nodes (the first post is the posting itself).');

        $first = $comments[0];
        $this->assertSame('Comment', $first['@type'] ?? null);
        $this->assertSame('Person', $first['author']['@type'] ?? null);
        $this->assertSame('bob', $first['author']['name'] ?? null);
        $this->assertStringContainsString('The popular reply.', $first['text'] ?? '');
        $this->assertStringContainsString('/d/1-bake-bread', $first['url'] ?? '');
        // Google requires `datePublished` on Comment nodes (ISO 8601).
        $this->assertNotEmpty($first['datePublished'] ?? null);
        $this->assertSame(Carbon::parse($first['datePublished'])->toIso8601String(), $first['datePublished']);
    }

    /**
     * The number of emitted Comment nodes is capped by the
     * `seo_post_crawler_limit` setting, keeping the lowest-numbered replies
     * (the order they appear on the page). Rendering every reply is the
     * dominant page-load cost, so the cap bounds it.
     */
    /**
     * Google's forum guidance says `author.url` should link to a page that
     * identifies the author (a profile page). A deleted user has no such page,
     * so emitting an `author` Person without a `url` trips Search Console's
     * "Missing field 'url' (in 'author')". `author` is recommended, not
     * required, so we omit it entirely for a deleted user rather than emit an
     * incomplete Person (GH #140).
     */
    #[Test]
    public function comment_by_deleted_user_omits_the_author(): void
    {
        $this->prepareDatabase([
            User::class => [
                ['id' => 2, 'username' => 'alice', 'email' => 'a@example.com', 'password' => '$2y$10$LO59tiT7uggl6Oe23o/O6.utnF6ipngYjvMvaxo1TciKqBttDNKim', 'is_email_confirmed' => 1],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'How do I bake bread', 'slug' => 'bake-bread', 'user_id' => 2, 'first_post_id' => 1, 'comment_count' => 2, 'created_at' => Carbon::now()],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>The question.</p></t>', 'created_at' => Carbon::now()],
                // Reply by a user that no longer exists (user_id 99 has no row).
                ['id' => 2, 'discussion_id' => 1, 'number' => 2, 'user_id' => 99, 'type' => 'comment', 'content' => '<t><p>Orphaned reply.</p></t>', 'created_at' => Carbon::now()],
            ],
        ]);

        $entry = $this->findSchemaEntry($this->fetchForumHtml('/d/1-bake-bread'), 'DiscussionForumPosting');

        $comment = $entry['comment'][0] ?? null;
        $this->assertIsArray($comment);
        // The comment itself is still emitted...
        $this->assertSame('Comment', $comment['@type'] ?? null);
        // ...but with no `author`, since a deleted user has no profile to link.
        $this->assertArrayNotHasKey('author', $comment);
    }

    #[Test]
    public function comment_count_is_capped_by_the_limit_setting(): void
    {
        // The seeded thread has two replies; a limit of 1 should emit only the
        // first (lowest-numbered) reply, in the order it appears on the page.
        $this->setting('seo_post_crawler_limit', '1');
        $this->seedThread();

        $entry = $this->findSchemaEntry($this->fetchForumHtml('/d/1-bake-bread'), 'DiscussionForumPosting');

        $comments = $entry['comment'] ?? null;
        $this->assertIsArray($comments);
        $this->assertCount(1, $comments);
        $this->assertStringContainsString('The popular reply.', $comments[0]['text'] ?? '');
    }

    /**
     * Comment `text` must be plain text rendered from the stored markup — not
     * the raw TextFormatter representation. A reply containing bold, a link and
     * an HTML entity should come through with the formatting stripped, the link
     * reduced to its visible label, and the entity decoded.
     */
    #[Test]
    public function comment_text_is_rendered_to_plain_text(): void
    {
        $this->prepareDatabase([
            User::class => [
                ['id' => 2, 'username' => 'alice', 'email' => 'a@example.com', 'password' => '$2y$10$LO59tiT7uggl6Oe23o/O6.utnF6ipngYjvMvaxo1TciKqBttDNKim', 'is_email_confirmed' => 1],
                ['id' => 3, 'username' => 'bob', 'email' => 'b@example.com', 'password' => '$2y$10$LO59tiT7uggl6Oe23o/O6.utnF6ipngYjvMvaxo1TciKqBttDNKim', 'is_email_confirmed' => 1],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'How do I bake bread', 'slug' => 'bake-bread', 'user_id' => 2, 'first_post_id' => 1, 'comment_count' => 2, 'created_at' => Carbon::now()],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>The question.</p></t>', 'created_at' => Carbon::now()],
                ['id' => 2, 'discussion_id' => 1, 'number' => 2, 'user_id' => 3, 'type' => 'comment', 'content' => '<r><p><STRONG>Bold</STRONG> and Tom &amp; Jerry visit <URL url="https://example.com">example.com</URL></p></r>', 'created_at' => Carbon::now()],
            ],
        ]);

        $entry = $this->findSchemaEntry($this->fetchForumHtml('/d/1-bake-bread'), 'DiscussionForumPosting');

        $text = $entry['comment'][0]['text'] ?? '';

        // Plain text: markup stripped, link as its label, entity decoded.
        $this->assertSame('Bold and Tom & Jerry visit example.com', $text);
        // No raw TextFormatter markup or undecoded entities leaked through.
        $this->assertStringNotContainsString('STRONG', $text);
        $this->assertStringNotContainsString('URL', $text);
        $this->assertStringNotContainsString('https://example.com', $text);
        $this->assertStringNotContainsString('&amp;', $text);
    }

    #[Test]
    public function comment_like_count_is_exposed_from_flarum_likes(): void
    {
        $this->extension('flarum-likes');
        $this->seedThread();

        // Two users like the first reply (post 2).
        $this->database()->table('post_likes')->insert([
            ['post_id' => 2, 'user_id' => 2],
            ['post_id' => 2, 'user_id' => 3],
        ]);

        $entry = $this->findSchemaEntry($this->fetchForumHtml('/d/1-bake-bread'), 'DiscussionForumPosting');

        $stat = $entry['comment'][0]['interactionStatistic'] ?? null;
        $this->assertSame('InteractionCounter', $stat['@type'] ?? null);
        $this->assertSame('https://schema.org/LikeAction', $stat['interactionType'] ?? null);
        $this->assertSame(2, $stat['userInteractionCount'] ?? null);
    }

    /**
     * With only fof/gamification enabled, the count comes from its votes and
     * only positive votes count (downvotes are excluded).
     */
    #[Test]
    public function comment_upvote_count_is_exposed_from_gamification_votes(): void
    {
        $this->extension('fof-gamification');
        $this->seedThread();

        // Post 2: two upvotes (+1) and one downvote (-1) => upvoteCount 2.
        $this->database()->table('post_votes')->insert([
            ['post_id' => 2, 'user_id' => 1, 'value' => 1],
            ['post_id' => 2, 'user_id' => 3, 'value' => 1],
            ['post_id' => 2, 'user_id' => 2, 'value' => -1],
        ]);

        $entry = $this->findSchemaEntry($this->fetchForumHtml('/d/1-bake-bread'), 'DiscussionForumPosting');

        $stat = $entry['comment'][0]['interactionStatistic'] ?? null;
        $this->assertSame('https://schema.org/LikeAction', $stat['interactionType'] ?? null);
        $this->assertSame(2, $stat['userInteractionCount'] ?? null, 'Only positive gamification votes should count.');
    }

    /**
     * When BOTH flarum/likes and fof/gamification are enabled, the counts are
     * combined (they are independent signals stored in separate tables).
     */
    #[Test]
    public function comment_count_combines_likes_and_gamification_when_both_enabled(): void
    {
        $this->extension('flarum-likes');
        $this->extension('fof-gamification');
        $this->seedThread();

        // Post 2: one like + two upvotes (one downvote ignored) => 1 + 2 = 3.
        $this->database()->table('post_likes')->insert([
            ['post_id' => 2, 'user_id' => 1],
        ]);
        $this->database()->table('post_votes')->insert([
            ['post_id' => 2, 'user_id' => 2, 'value' => 1],
            ['post_id' => 2, 'user_id' => 3, 'value' => 1],
            ['post_id' => 2, 'user_id' => 1, 'value' => -1],
        ]);

        $entry = $this->findSchemaEntry($this->fetchForumHtml('/d/1-bake-bread'), 'DiscussionForumPosting');

        $stat = $entry['comment'][0]['interactionStatistic'] ?? null;
        $this->assertSame(3, $stat['userInteractionCount'] ?? null, 'Likes and gamification upvotes should be summed.');
    }
}
