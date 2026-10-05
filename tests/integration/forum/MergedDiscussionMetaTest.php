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
use FoF\Seo\Tests\integration\ForumHtmlTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Merging discussions with fof/merge-discussions changes the target's content
 * without any of the events its stored meta is refreshed on, and a merge by
 * date can give it a new first post, which the description is taken from.
 */
class MergedDiscussionMetaTest extends ForumHtmlTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-seo', 'fof-merge-discussions');

        $this->prepareDatabase([
            Discussion::class => [
                ['id' => 1, 'title' => 'Target', 'slug' => 'target', 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1, 'created_at' => $this->at(1), 'last_posted_at' => $this->at(1)],
                ['id' => 2, 'title' => 'Source', 'slug' => 'source', 'user_id' => 1, 'first_post_id' => 2, 'comment_count' => 1, 'created_at' => $this->at(0), 'last_posted_at' => $this->at(0)],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>The target discussion opens here.</p></t>', 'created_at' => $this->at(1)],
                ['id' => 2, 'discussion_id' => 2, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>The older source discussion opens here.</p></t>', 'created_at' => $this->at(0)],
            ],
        ]);
    }

    #[Test]
    public function merged_discussion_is_described_by_its_new_first_post()
    {
        $this->storedMeta(true, 'The target discussion opens here.');

        $this->merge(1, [2]);

        $html = $this->fetchForumHtml('/d/1-target');

        $this->assertSame('The older source discussion opens here.', $this->findMetaByName($html, 'description'));
    }

    /**
     * With auto-update off, an admin has written the meta by hand.
     */
    #[Test]
    public function hand_written_meta_survives_a_merge()
    {
        $this->storedMeta(false, 'Written by hand for search results.');

        $this->merge(1, [2]);

        $html = $this->fetchForumHtml('/d/1-target');

        $this->assertSame('Written by hand for search results.', $this->findMetaByName($html, 'description'));
    }

    /**
     * The target's meta as stored before the merge.
     */
    private function storedMeta(bool $autoUpdate, string $description): void
    {
        $this->prepareDatabase([
            'seo_meta' => [
                ['id' => 1, 'object_type' => 'discussions', 'object_id' => 1, 'auto_update_data' => $autoUpdate, 'title' => 'Target', 'description' => $description, 'created_at' => $this->at(1), 'updated_at' => $this->at(1)],
            ],
        ]);
    }

    private function at(int $days): Carbon
    {
        return Carbon::parse('2024-01-01 00:00:00')->addDays($days);
    }

    private function merge(int $target, array $sources): void
    {
        $response = $this->send($this->request('POST', "/api/discussions/$target/merge", [
            'authenticatedAs' => 1,
            'json'            => ['ids' => $sources, 'ordering' => 'date'],
        ]));

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
    }
}
