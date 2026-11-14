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
use PHPUnit\Framework\Attributes\Test;

/**
 * fof/seo's sitemap constraints are pure SQL: a build runs the same number of
 * queries for 10 discussions as for 200, whatever share of them is noindexed.
 */
class SitemapQueryCountTest extends TestCase
{
    use BuildsSitemap;

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-tags', 'fof-pages', 'fof-sitemap', 'fof-seo');

        $this->setting('fof-sitemap.model.tags.discussion.minimum_item_threshold', 0);
        $this->setting('fof-sitemap.model.user.comments.minimum_item_threshold', 0);
        $this->setting('seo_noindex_tags', json_encode([1]));

        $this->prepareDatabase([
            'tags' => [
                ['id' => 1, 'name' => 'Excluded', 'slug' => 'excluded', 'position' => 0, 'discussion_count' => 1],
                ['id' => 2, 'name' => 'Child', 'slug' => 'child', 'position' => 1, 'parent_id' => 1, 'discussion_count' => 1],
                ['id' => 3, 'name' => 'Open', 'slug' => 'open', 'position' => 2, 'discussion_count' => 1],
            ],
        ]);
    }

    /**
     * Add discussions (with posts, tags and a mix of per-item noindex rows),
     * users and pages until there are $total of each.
     */
    private function seedUpTo(int $from, int $total): void
    {
        $db = $this->database();
        $now = Carbon::parse('2026-01-01 12:00:00');

        for ($id = $from; $id <= $total; $id++) {
            $db->table('users')->insert([
                'id'       => $id + 10, 'username' => "user$id", 'email' => "user$id@example.com", 'is_email_confirmed' => 1,
                'password' => '', 'comment_count' => 1, 'joined_at' => $now,
            ]);
            $db->table('discussions')->insert([
                'id'            => $id, 'title' => "Discussion $id", 'slug' => "d$id", 'user_id' => $id + 10,
                'comment_count' => 1, 'created_at' => $now, 'last_posted_at' => $now, 'is_private' => false,
            ]);
            // Foreign keys are enforced here (unlike in prepareDatabase()):
            // the discussion must exist before its post, and the post before
            // the discussion points at it.
            $db->table('posts')->insert([
                'id'      => $id, 'discussion_id' => $id, 'number' => 1, 'user_id' => $id + 10, 'type' => 'comment',
                'content' => '<t><p>Body.</p></t>', 'created_at' => $now,
            ]);
            $db->table('discussions')->where('id', $id)->update(['first_post_id' => $id, 'last_post_id' => $id]);
            $db->table('discussion_tag')->insert(['discussion_id' => $id, 'tag_id' => [1, 2, 3][$id % 3]]);
            $db->table('pages')->insert([
                'id'        => $id, 'title' => "Page $id", 'slug' => "page-$id", 'content' => '<p>x</p>', 'is_html' => 1,
                'is_hidden' => 0, 'is_restricted' => 0, 'created_at' => $now, 'updated_at' => $now,
            ]);

            if ($id % 4 === 0) {
                foreach (['discussions', 'pages', 'users'] as $type) {
                    $db->table('seo_meta')->insert([
                        'object_type' => $type, 'object_id' => $id, 'robots_noindex' => true,
                        'created_at'  => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    #[Test]
    public function query_count_does_not_grow_with_the_number_of_discussions(): void
    {
        $this->app();

        $this->seedUpTo(1, 10);
        // The first build also loads things once per process (settings,
        // extension state); count from the second build on.
        $this->countSitemapBuildQueries();
        $small = $this->countSitemapBuildQueries();
        $smallUrls = count($this->buildSitemapUrls());

        $this->seedUpTo(11, 200);
        $large = $this->countSitemapBuildQueries();
        $largeUrls = count($this->buildSitemapUrls());

        $this->assertGreaterThan($smallUrls + 300, $largeUrls, 'The larger forum must really be built (sanity check)');
        $this->assertSame($small, $large, "A build ran $small queries for 10 discussions and $large for 200");
    }

    #[Test]
    public function query_count_does_not_depend_on_profile_noindex(): void
    {
        $this->app();
        $this->seedUpTo(1, 50);
        $this->countSitemapBuildQueries();

        $profilesListed = $this->countSitemapBuildQueries();

        $this->app()->getContainer()->make(\Flarum\Settings\SettingsRepositoryInterface::class)->set('seo_noindex_profiles', '1');

        $this->assertLessThanOrEqual($profilesListed, $this->countSitemapBuildQueries());
    }
}
