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
use FoF\Seo\Tests\integration\ForumHtmlTestCase;

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
            'users' => [
                ['id' => 2, 'username' => 'alice', 'email' => 'a@example.com', 'password' => '$2y$10$LO59tiT7uggl6Oe23o/O6.utnF6ipngYjvMvaxo1TciKqBttDNKim', 'is_email_confirmed' => 1],
                ['id' => 3, 'username' => 'bob', 'email' => 'b@example.com', 'password' => '$2y$10$LO59tiT7uggl6Oe23o/O6.utnF6ipngYjvMvaxo1TciKqBttDNKim', 'is_email_confirmed' => 1],
            ],
            'discussions' => [
                ['id' => 1, 'title' => 'How do I bake bread', 'slug' => 'bake-bread', 'user_id' => 2, 'first_post_id' => 1, 'comment_count' => 3, 'created_at' => Carbon::now()],
            ],
            'posts' => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>The question.</p></t>', 'created_at' => Carbon::now()],
                ['id' => 2, 'discussion_id' => 1, 'number' => 2, 'user_id' => 3, 'type' => 'comment', 'content' => '<t><p>The popular reply.</p></t>', 'created_at' => Carbon::now()],
                ['id' => 3, 'discussion_id' => 1, 'number' => 3, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Another reply.</p></t>', 'created_at' => Carbon::now()],
            ],
        ]);
    }

    /**
     * @test
     */
    public function replies_are_not_exposed_when_post_crawler_is_disabled(): void
    {
        $this->setting('seo_post_crawler', '0');
        $this->seedThread();

        $entry = $this->findSchemaEntry($this->fetchForumHtml('/d/1-bake-bread'), 'DiscussionForumPosting');

        $this->assertNotNull($entry);
        $this->assertArrayNotHasKey('comment', $entry);
    }

    /**
     * @test
     */
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
    }

    /**
     * @test
     */
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
     *
     * @test
     */
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
     *
     * @test
     */
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
