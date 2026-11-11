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
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Tags\Tag;
use Flarum\User\User;
use FoF\Seo\Tests\integration\BuildsSitemap;
use FoF\Seo\Tests\integration\ForumHtmlTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * With fof/seo and fof/sitemap both enabled, fof/seo decides what is
 * indexable and the sitemap never lists a URL that fof/seo marks `noindex`.
 */
class SitemapIndexabilityTest extends ForumHtmlTestCase
{
    use BuildsSitemap;

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-tags', 'fof-sitemap', 'fof-seo');

        $this->setting('forum_title', 'Example Forum');
        // fof/sitemap lists tags with more than N discussions and users with
        // more than N comments; 0 lets every fixture through the thresholds.
        $this->setting('fof-sitemap.model.tags.discussion.minimum_item_threshold', 0);
        $this->setting('fof-sitemap.model.user.comments.minimum_item_threshold', 0);

        $now = Carbon::parse('2026-01-01 12:00:00');
        $tag = fn (int $id, string $slug, ?int $parentId = null, array $extra = []) => array_merge([
            'id' => $id, 'name' => ucfirst($slug), 'slug' => $slug, 'description' => null, 'color' => '#000',
            'position' => $id, 'is_restricted' => false, 'is_hidden' => false, 'parent_id' => $parentId,
            'discussion_count' => 1,
        ], $extra);
        $discussion = fn (int $id, array $extra = []) => array_merge([
            'id' => $id, 'title' => "Discussion $id", 'slug' => "d$id", 'user_id' => 2, 'first_post_id' => $id,
            'comment_count' => 1, 'created_at' => $now, 'last_posted_at' => $now, 'is_private' => false,
        ], $extra);
        $post = fn (int $id) => [
            'id' => $id, 'discussion_id' => $id, 'number' => 1, 'user_id' => 2, 'type' => 'comment',
            'content' => '<t><p>Body.</p></t>', 'created_at' => $now,
        ];
        $meta = fn (string $type, int $id, bool $noindex, array $extra = []) => array_merge([
            'object_type' => $type, 'object_id' => $id, 'robots_noindex' => $noindex,
            'created_at' => $now, 'updated_at' => $now,
        ], $extra);

        $this->prepareDatabase([
            Tag::class => [
                $tag(1, 'parent'),                        // selected in seo_noindex_tags
                $tag(2, 'child', 1),                      // child of a selected tag
                $tag(3, 'unrelated'),
                $tag(4, 'own-noindex'),                   // per-item noindex on the tag itself
                $tag(5, 'unrelated-child', 3),            // child of an indexable tag
                $tag(6, 'restricted', null, ['is_restricted' => true]),
                $tag(7, 'empty', null, ['discussion_count' => 0]),
            ],
            User::class => [
                ['id' => 2, 'username' => 'alice', 'email' => 'alice@example.com', 'is_email_confirmed' => 1, 'comment_count' => 3, 'joined_at' => $now],
                ['id' => 3, 'username' => 'bob', 'email' => 'bob@example.com', 'is_email_confirmed' => 1, 'comment_count' => 3, 'joined_at' => $now],
            ],
            Discussion::class => [
                $discussion(1),                           // tag 3
                $discussion(2),                           // tag 1 (selected)
                $discussion(3),                           // tag 2 (child of selected)
                $discussion(4),                           // tags 3 + 1 (any excluded tag wins)
                $discussion(5),                           // tag 3, per-item noindex
                $discussion(6),                           // tag 3, per-item row without noindex
                $discussion(7),                           // untagged
                $discussion(8),                           // tag 4 (tag's own noindex does not inherit)
                $discussion(9),                           // tag 5 (child of indexable tag)
                $discussion(10),                          // tag 6 (restricted: not guest-visible)
                $discussion(11, ['hidden_at' => $now]),   // hidden
                $discussion(12, ['is_private' => true]),  // private
            ],
            Post::class => array_map($post, range(1, 12)),
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 3],
                ['discussion_id' => 2, 'tag_id' => 1],
                ['discussion_id' => 3, 'tag_id' => 1],
                ['discussion_id' => 3, 'tag_id' => 2],
                ['discussion_id' => 4, 'tag_id' => 3],
                ['discussion_id' => 4, 'tag_id' => 1],
                ['discussion_id' => 5, 'tag_id' => 3],
                ['discussion_id' => 6, 'tag_id' => 3],
                ['discussion_id' => 8, 'tag_id' => 4],
                ['discussion_id' => 9, 'tag_id' => 3],
                ['discussion_id' => 9, 'tag_id' => 5],
                ['discussion_id' => 10, 'tag_id' => 6],
                ['discussion_id' => 11, 'tag_id' => 3],
                ['discussion_id' => 12, 'tag_id' => 3],
            ],
            'seo_meta' => [
                $meta('discussions', 5, true),
                $meta('discussions', 6, false, ['robots_nofollow' => true, 'robots_nosnippet' => true]),
                $meta('tags', 4, true),
                // Rows of another object type must not leak onto the same ID.
                $meta('pages', 1, true),
                $meta('users', 7, true),
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    private function sitemapPaths(): array
    {
        return $this->pathsOf($this->buildSitemapUrls());
    }

    #[Test]
    public function discussions_in_noindexed_tags_or_with_item_noindex_are_left_out(): void
    {
        $this->setting('seo_noindex_tags', json_encode([1]));

        $paths = $this->sitemapPaths();

        foreach (['/d/1-d1', '/d/6-d6', '/d/7-d7', '/d/8-d8', '/d/9-d9'] as $listed) {
            $this->assertContains($listed, $paths);
        }

        foreach (['/d/2-d2', '/d/3-d3', '/d/4-d4', '/d/5-d5'] as $excluded) {
            $this->assertNotContains($excluded, $paths, "$excluded is noindexed by fof/seo");
        }

        // Guest visibility still applies.
        foreach (['/d/10-d10', '/d/11-d11', '/d/12-d12'] as $invisible) {
            $this->assertNotContains($invisible, $paths, "$invisible is not visible to guests");
        }
    }

    /**
     * fof/sitemap's riskyPerformanceImprovements narrows the selected columns
     * of the discussion and user queries; the constraints must still hold.
     */
    #[Test]
    public function exclusions_hold_with_sitemap_risky_performance_improvements(): void
    {
        $this->setting('fof-sitemap.riskyPerformanceImprovements', true);
        $this->setting('seo_noindex_tags', json_encode([1]));

        $paths = $this->sitemapPaths();

        foreach (['/d/1-d1', '/d/6-d6', '/d/7-d7', '/d/8-d8', '/d/9-d9', '/u/alice', '/u/bob'] as $listed) {
            $this->assertContains($listed, $paths);
        }

        foreach (['/d/2-d2', '/d/3-d3', '/d/4-d4', '/d/5-d5'] as $excluded) {
            $this->assertNotContains($excluded, $paths);
        }

        $this->setting('seo_noindex_profiles', '1');
        $this->app()->getContainer()->make(\Flarum\Settings\SettingsRepositoryInterface::class)->set('seo_noindex_profiles', '1');

        $this->assertEmpty(array_filter($this->sitemapPaths(), fn (string $path) => str_starts_with($path, '/u/')));
    }

    #[Test]
    public function noindexed_tags_their_children_and_item_noindexed_tags_are_left_out(): void
    {
        $this->setting('seo_noindex_tags', json_encode([1]));

        $paths = $this->sitemapPaths();

        $this->assertContains('/t/unrelated', $paths);
        $this->assertContains('/t/unrelated-child', $paths, 'A child of an indexable tag stays listed');

        $this->assertNotContains('/t/parent', $paths);
        $this->assertNotContains('/t/child', $paths);
        $this->assertNotContains('/t/own-noindex', $paths);

        // fof/sitemap's own rules still apply: guest visibility and thresholds.
        $this->assertNotContains('/t/restricted', $paths);
        $this->assertNotContains('/t/empty', $paths);
    }

    #[Test]
    public function only_item_noindex_applies_when_no_tags_are_selected(): void
    {
        $paths = $this->sitemapPaths();

        foreach (['/d/1-d1', '/d/2-d2', '/d/3-d3', '/d/4-d4', '/d/6-d6', '/d/7-d7', '/d/8-d8', '/d/9-d9'] as $listed) {
            $this->assertContains($listed, $paths);
        }
        $this->assertNotContains('/d/5-d5', $paths);

        foreach (['/t/parent', '/t/child', '/t/unrelated', '/t/unrelated-child'] as $listed) {
            $this->assertContains($listed, $paths);
        }
        $this->assertNotContains('/t/own-noindex', $paths);
    }

    #[Test]
    public function profiles_are_listed_while_profile_noindex_is_off(): void
    {
        $paths = $this->sitemapPaths();

        $this->assertContains('/u/alice', $paths);
        $this->assertContains('/u/bob', $paths);
    }

    #[Test]
    public function no_profile_is_listed_while_profile_noindex_is_on(): void
    {
        $this->setting('seo_noindex_profiles', '1');

        $paths = $this->sitemapPaths();

        $this->assertEmpty(array_filter($paths, fn (string $path) => str_starts_with($path, '/u/')), 'No profile URL may be listed');
        $this->assertContains('/d/1-d1', $paths, 'Other resources are unaffected');
        $this->assertContains('/all', $paths, 'Static URLs are unaffected');
        $this->assertContains('/tags', $paths, 'Static URLs are unaffected');
    }

    #[Test]
    public function building_the_sitemap_creates_no_seo_meta_rows(): void
    {
        $this->setting('seo_noindex_tags', json_encode([1]));
        $this->setting('seo_noindex_profiles', '1');

        $before = $this->database()->table('seo_meta')->count();

        $this->buildSitemapUrls();

        $this->assertSame($before, $this->database()->table('seo_meta')->count());
    }

    /**
     * The same exclusions reach crawlers through the served routes.
     */
    #[Test]
    public function served_sitemap_routes_leave_out_noindexed_urls(): void
    {
        $this->setting('seo_noindex_tags', json_encode([1]));
        $this->setting('seo_noindex_profiles', '1');

        $index = $this->send($this->request('GET', '/sitemap.xml'));
        $this->assertSame(200, $index->getStatusCode());

        $paths = [];

        foreach ($this->locsIn((string) $index->getBody()) as $setUrl) {
            $set = $this->send($this->request('GET', parse_url($setUrl, PHP_URL_PATH)));
            $this->assertSame(200, $set->getStatusCode());
            $paths = array_merge($paths, $this->pathsOf($this->locsIn((string) $set->getBody())));
        }

        $this->assertContains('/d/1-d1', $paths);
        $this->assertContains('/t/unrelated', $paths);

        foreach (['/d/2-d2', '/d/3-d3', '/d/4-d4', '/d/5-d5', '/t/parent', '/t/child', '/t/own-noindex', '/u/alice', '/u/bob'] as $excluded) {
            $this->assertNotContains($excluded, $paths);
        }
    }

    /**
     * One source of truth: for every guest-visible discussion, tag and
     * profile, the page's robots meta says `noindex` exactly when the sitemap
     * leaves the URL out.
     */
    #[Test]
    public function sitemap_agrees_with_rendered_robots_meta(): void
    {
        $this->setting('seo_noindex_tags', json_encode([1]));
        $this->setting('seo_noindex_profiles', '1');

        $paths = $this->sitemapPaths();

        $pages = [
            '/d/1-d1', '/d/2-d2', '/d/3-d3', '/d/4-d4', '/d/5-d5', '/d/6-d6', '/d/7-d7', '/d/8-d8', '/d/9-d9',
            '/t/parent', '/t/child', '/t/unrelated', '/t/own-noindex', '/t/unrelated-child',
            '/u/alice', '/u/bob',
        ];

        foreach ($pages as $path) {
            $robots = (string) $this->findMetaByName($this->fetchForumHtml($path), 'robots');
            $noindex = str_contains($robots, 'noindex');

            $this->assertSame(
                $noindex,
                !in_array($path, $paths, true),
                sprintf('%s renders robots "%s" but is %s the sitemap', $path, $robots, $noindex ? 'in' : 'missing from')
            );
        }
    }
}
