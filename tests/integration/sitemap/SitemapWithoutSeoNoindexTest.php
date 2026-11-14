<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Tests\integration\sitemap;

use Carbon\Carbon;
use Flarum\Testing\integration\TestCase;
use FoF\Seo\Tests\integration\BuildsSitemap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * With fof/seo's noindex settings off and no per-item noindex, enabling
 * fof/seo next to fof/sitemap changes nothing in the sitemap.
 */
class SitemapWithoutSeoNoindexTest extends TestCase
{
    use BuildsSitemap;

    /**
     * Built by fof/sitemap alone from the fixtures below.
     */
    private const EXPECTED = [
        '/all',
        '/d/1-first',
        '/d/2-second',
        '/d/3-third',
        '/p/1-about',
        '/t/child',
        '/t/general',
        '/tags',
        '/u/alice',
    ];

    public static function installs(): array
    {
        return [
            'fof/sitemap alone'         => [['flarum-tags', 'fof-pages', 'fof-sitemap']],
            'fof/sitemap with fof/seo'  => [['flarum-tags', 'fof-pages', 'fof-sitemap', 'fof-seo']],
        ];
    }

    #[Test]
    #[DataProvider('installs')]
    public function sitemap_is_the_same_as_fof_sitemap_alone(array $extensions): void
    {
        $this->extension(...$extensions);

        $this->setting('fof-sitemap.model.tags.discussion.minimum_item_threshold', 0);
        $this->setting('fof-sitemap.model.user.comments.minimum_item_threshold', 0);
        $this->setting('seo_noindex_tags', '[]');
        $this->setting('seo_noindex_profiles', '0');

        $now = Carbon::parse('2026-01-01 12:00:00');

        $fixtures = [
            'tags' => [
                ['id' => 1, 'name' => 'General', 'slug' => 'general', 'position' => 0, 'discussion_count' => 2],
                ['id' => 2, 'name' => 'Child', 'slug' => 'child', 'position' => 1, 'parent_id' => 1, 'discussion_count' => 1],
            ],
            'users' => [
                ['id' => 2, 'username' => 'alice', 'email' => 'alice@example.com', 'is_email_confirmed' => 1, 'password' => '', 'comment_count' => 3, 'joined_at' => $now],
            ],
            'discussions' => [
                ['id' => 1, 'title' => 'First', 'slug' => 'first', 'user_id' => 2, 'first_post_id' => 1, 'comment_count' => 1, 'created_at' => $now, 'last_posted_at' => $now],
                ['id' => 2, 'title' => 'Second', 'slug' => 'second', 'user_id' => 2, 'first_post_id' => 2, 'comment_count' => 1, 'created_at' => $now, 'last_posted_at' => $now],
                ['id' => 3, 'title' => 'Third', 'slug' => 'third', 'user_id' => 2, 'first_post_id' => 3, 'comment_count' => 1, 'created_at' => $now, 'last_posted_at' => $now],
            ],
            'posts' => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>x</p></t>', 'created_at' => $now],
                ['id' => 2, 'discussion_id' => 2, 'number' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>x</p></t>', 'created_at' => $now],
                ['id' => 3, 'discussion_id' => 3, 'number' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>x</p></t>', 'created_at' => $now],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 1],
                ['discussion_id' => 2, 'tag_id' => 1],
                ['discussion_id' => 2, 'tag_id' => 2],
            ],
            'pages' => [
                ['id' => 1, 'title' => 'About', 'slug' => 'about', 'content' => '<p>x</p>', 'is_html' => 1, 'is_hidden' => 0, 'is_restricted' => 0, 'created_at' => $now, 'updated_at' => $now],
            ],
        ];

        if (in_array('fof-seo', $extensions, true)) {
            // Rows without noindex (as page views create them) change nothing.
            $fixtures['seo_meta'] = [
                ['object_type' => 'discussions', 'object_id' => 1, 'robots_nofollow' => true, 'created_at' => $now, 'updated_at' => $now],
                ['object_type' => 'tags', 'object_id' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['object_type' => 'pages', 'object_id' => 1, 'robots_nosnippet' => true, 'created_at' => $now, 'updated_at' => $now],
            ];
        }

        $this->prepareDatabase($fixtures);

        $this->assertSame(self::EXPECTED, $this->pathsOf($this->buildSitemapUrls()));
    }
}
