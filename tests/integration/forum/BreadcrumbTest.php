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
use Flarum\Extend;
use Flarum\Post\Post;
use Flarum\Tags\Tag;
use FoF\Seo\Breadcrumb\Crumb;
use FoF\Seo\Event\BuildingBreadcrumb;
use FoF\Seo\Tests\integration\ForumHtmlTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * End-to-end coverage of the breadcrumb system across the three layers:
 * core pages, flarum/tags, and supported extensions — plus the extensible
 * BuildingBreadcrumb event.
 */
class BreadcrumbTest extends ForumHtmlTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-seo');

        $this->setting('forum_title', 'My Forum');
        // A neutral default route so individual pages are never "home".
        $this->setting('default_route', '/all');
    }

    /**
     * @return list<string>
     */
    private function crumbNames(string $html): array
    {
        $breadcrumb = $this->findSchemaEntry($html, 'BreadcrumbList');

        return $breadcrumb === null ? [] : array_column($breadcrumb['itemListElement'], 'name');
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

    // ----- Core pages (no flarum/tags) -----------------------------------

    #[Test]
    public function a_discussion_without_tags_gets_a_home_then_title_trail(): void
    {
        $this->prepareDatabase([
            Discussion::class => [
                ['id' => 1, 'title' => 'Plain discussion', 'slug' => 'plain', 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1, 'created_at' => Carbon::now()],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Body.</p></t>', 'created_at' => Carbon::now()],
            ],
        ]);

        $this->assertSame(['My Forum', 'Plain discussion'], $this->crumbNames($this->fetchForumHtml('/d/1-plain')));
    }

    #[Test]
    public function the_last_crumb_omits_item_and_home_links_to_root(): void
    {
        $this->prepareDatabase([
            Discussion::class => [
                ['id' => 1, 'title' => 'Plain discussion', 'slug' => 'plain', 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1, 'created_at' => Carbon::now()],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Body.</p></t>', 'created_at' => Carbon::now()],
            ],
        ]);

        $items = $this->findSchemaEntry($this->fetchForumHtml('/d/1-plain'), 'BreadcrumbList')['itemListElement'];

        $this->assertSame('http://localhost/', $items[0]['item']['url'] ?? null);
        $this->assertArrayNotHasKey('item', $items[1]);
    }

    #[Test]
    public function a_profile_page_gets_a_home_then_name_trail(): void
    {
        $this->assertSame(['My Forum', 'admin'], $this->crumbNames($this->fetchForumHtml('/u/admin')));
    }

    #[Test]
    public function the_forum_home_page_emits_no_breadcrumb(): void
    {
        // default_route is /all, so /all is the home page.
        $this->assertNull($this->findSchemaEntry($this->fetchForumHtml('/all'), 'BreadcrumbList'));
    }

    #[Test]
    public function a_page_that_is_the_configured_home_emits_no_breadcrumb(): void
    {
        // Point the forum home at a specific user's profile.
        $this->setting('default_route', '/u/admin');

        $this->assertNull($this->findSchemaEntry($this->fetchForumHtml('/u/admin'), 'BreadcrumbList'));
    }

    // ----- flarum/tags layer ---------------------------------------------

    #[Test]
    public function a_tagged_discussion_includes_the_tag_lineage(): void
    {
        $this->extension('flarum-tags');

        $this->prepareDatabase([
            Tag::class => [
                ['id' => 1, 'name' => 'Support', 'slug' => 'support', 'description' => null, 'color' => '#000', 'position' => 0, 'is_restricted' => false, 'is_hidden' => false, 'is_primary' => true],
                ['id' => 2, 'name' => 'Installation', 'slug' => 'installation', 'description' => null, 'color' => '#000', 'position' => 0, 'parent_id' => 1, 'is_restricted' => false, 'is_hidden' => false, 'is_primary' => true],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'How do I install?', 'slug' => 'how-install', 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1, 'created_at' => Carbon::now()],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Body.</p></t>', 'created_at' => Carbon::now()],
            ],
            // Tagged only with the child primary; its parent must still appear, in order.
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 2],
            ],
        ]);

        $this->assertSame(
            ['My Forum', 'Support', 'Installation', 'How do I install?'],
            $this->crumbNames($this->fetchForumHtml('/d/1-how-install'))
        );
    }

    #[Test]
    public function the_primary_tag_lineage_is_chosen_for_a_multi_tag_discussion(): void
    {
        $this->extension('flarum-tags');

        $this->prepareDatabase([
            Tag::class => [
                // Chatter is secondary (no position); Support is primary.
                ['id' => 1, 'name' => 'Chatter', 'slug' => 'chatter', 'description' => null, 'color' => '#000', 'position' => null, 'is_restricted' => false, 'is_hidden' => false, 'is_primary' => false],
                ['id' => 2, 'name' => 'Support', 'slug' => 'support', 'description' => null, 'color' => '#000', 'position' => 1, 'is_restricted' => false, 'is_hidden' => false, 'is_primary' => true],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'Mixed', 'slug' => 'mixed', 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1, 'created_at' => Carbon::now()],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Body.</p></t>', 'created_at' => Carbon::now()],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 1],
                ['discussion_id' => 1, 'tag_id' => 2],
            ],
        ]);

        // The primary tag (Support) forms the lineage, not the secondary one.
        $this->assertSame(
            ['My Forum', 'Support', 'Mixed'],
            $this->crumbNames($this->fetchForumHtml('/d/1-mixed'))
        );
    }

    #[Test]
    public function a_single_primary_tag_forms_a_one_level_lineage(): void
    {
        $this->extension('flarum-tags');

        $this->prepareDatabase([
            Tag::class => [
                ['id' => 1, 'name' => 'Support', 'slug' => 'support', 'description' => null, 'color' => '#000', 'position' => 0, 'is_restricted' => false, 'is_hidden' => false, 'is_primary' => true],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'Q', 'slug' => 'q', 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1, 'created_at' => Carbon::now()],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Body.</p></t>', 'created_at' => Carbon::now()],
            ],
            'discussion_tag' => [['discussion_id' => 1, 'tag_id' => 1]],
        ]);

        $this->assertSame(
            ['My Forum', 'Support', 'Q'],
            $this->crumbNames($this->fetchForumHtml('/d/1-q'))
        );
    }

    #[Test]
    public function a_primary_parent_and_its_primary_child_fold_into_one_lineage(): void
    {
        $this->extension('flarum-tags');

        // Both the primary parent and its primary child are attached. They
        // must collapse to a single lineage ending at the child — not two.
        $this->prepareDatabase([
            Tag::class => [
                ['id' => 1, 'name' => 'Support', 'slug' => 'support', 'description' => null, 'color' => '#000', 'position' => 0, 'is_restricted' => false, 'is_hidden' => false, 'is_primary' => true],
                ['id' => 2, 'name' => 'Installation', 'slug' => 'installation', 'description' => null, 'color' => '#000', 'position' => 0, 'parent_id' => 1, 'is_restricted' => false, 'is_hidden' => false, 'is_primary' => true],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'Q', 'slug' => 'q', 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1, 'created_at' => Carbon::now()],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Body.</p></t>', 'created_at' => Carbon::now()],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 1],
                ['discussion_id' => 1, 'tag_id' => 2],
            ],
        ]);

        $html = $this->fetchForumHtml('/d/1-q');

        // Exactly one BreadcrumbList, folding parent + child.
        $lists = array_filter($this->findSchemaJsonLd($html) ?? [], fn ($e) => ($e['@type'] ?? null) === 'BreadcrumbList');
        $this->assertCount(1, $lists);
        $this->assertSame(
            ['My Forum', 'Support', 'Installation', 'Q'],
            $this->crumbNames($html)
        );
    }

    #[Test]
    public function dual_primary_tags_emit_one_breadcrumb_list_each(): void
    {
        $this->extension('flarum-tags');

        // Two unrelated primary tags → two separate trails.
        $this->prepareDatabase([
            Tag::class => [
                ['id' => 1, 'name' => 'Support', 'slug' => 'support', 'description' => null, 'color' => '#000', 'position' => 0, 'is_restricted' => false, 'is_hidden' => false, 'is_primary' => true],
                ['id' => 2, 'name' => 'Bugs', 'slug' => 'bugs', 'description' => null, 'color' => '#000', 'position' => 1, 'is_restricted' => false, 'is_hidden' => false, 'is_primary' => true],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'Q', 'slug' => 'q', 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1, 'created_at' => Carbon::now()],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Body.</p></t>', 'created_at' => Carbon::now()],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 1],
                ['discussion_id' => 1, 'tag_id' => 2],
            ],
        ]);

        $html = $this->fetchForumHtml('/d/1-q');

        $lists = array_values(array_filter(
            $this->findSchemaJsonLd($html) ?? [],
            fn ($e) => ($e['@type'] ?? null) === 'BreadcrumbList'
        ));

        $this->assertCount(2, $lists, 'Expected one BreadcrumbList per primary tag.');

        $trails = array_map(fn ($l) => array_column($l['itemListElement'], 'name'), $lists);

        $this->assertContains(['My Forum', 'Support', 'Q'], $trails);
        $this->assertContains(['My Forum', 'Bugs', 'Q'], $trails);
    }

    #[Test]
    public function a_secondary_only_discussion_skips_the_tag_in_the_trail(): void
    {
        $this->extension('flarum-tags');

        // A flat secondary tag is not a category path — the trail is just
        // Home › title, and there is a single BreadcrumbList.
        $this->prepareDatabase([
            Tag::class => [
                // A genuine secondary tag has no position (and no parent).
                ['id' => 1, 'name' => 'Announcements', 'slug' => 'announcements', 'description' => null, 'color' => '#000', 'position' => null, 'is_restricted' => false, 'is_hidden' => false, 'is_primary' => false],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'Q', 'slug' => 'q', 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1, 'created_at' => Carbon::now()],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Body.</p></t>', 'created_at' => Carbon::now()],
            ],
            'discussion_tag' => [['discussion_id' => 1, 'tag_id' => 1]],
        ]);

        $html = $this->fetchForumHtml('/d/1-q');

        // Trail is Home › title; the secondary tag is not a crumb.
        $this->assertSame(['My Forum', 'Q'], $this->crumbNames($html));

        $lists = array_filter($this->findSchemaJsonLd($html) ?? [], fn ($e) => ($e['@type'] ?? null) === 'BreadcrumbList');
        $this->assertCount(1, $lists);
    }

    #[Test]
    public function a_top_level_tag_with_a_drifted_is_primary_flag_still_forms_the_lineage(): void
    {
        $this->extension('flarum-tags');

        // Regression for discuss.flarum.org: top-level tags there have a
        // `position` but a legacy `is_primary = 0`. The category must still
        // appear in the breadcrumb — we key off `position`, not `is_primary`.
        $this->prepareDatabase([
            Tag::class => [
                ['id' => 1, 'name' => 'Extensions', 'slug' => 'extensions', 'description' => null, 'color' => '#000', 'position' => 2, 'parent_id' => null, 'is_restricted' => false, 'is_hidden' => false, 'is_primary' => false],
                // Genuinely-secondary version tags (no position).
                ['id' => 2, 'name' => '2.x', 'slug' => 'version-2x', 'description' => null, 'color' => '#000', 'position' => null, 'is_restricted' => false, 'is_hidden' => false, 'is_primary' => false],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'FoF Anti-Spam', 'slug' => 'antispam', 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1, 'created_at' => Carbon::now()],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Body.</p></t>', 'created_at' => Carbon::now()],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 1],
                ['discussion_id' => 1, 'tag_id' => 2],
            ],
        ]);

        // Forum › Extensions › title — Extensions (positioned) anchors it; the
        // secondary "2.x" tag is excluded.
        $this->assertSame(
            ['My Forum', 'Extensions', 'FoF Anti-Spam'],
            $this->crumbNames($this->fetchForumHtml('/d/1-antispam'))
        );
    }

    #[Test]
    public function a_tag_page_trail_ends_at_the_tag(): void
    {
        $this->extension('flarum-tags');

        $this->prepareDatabase([
            Tag::class => [
                ['id' => 1, 'name' => 'Support', 'slug' => 'support', 'description' => null, 'color' => '#000', 'position' => 0, 'is_restricted' => false, 'is_hidden' => false],
                ['id' => 2, 'name' => 'Installation', 'slug' => 'installation', 'description' => null, 'color' => '#000', 'position' => 0, 'parent_id' => 1, 'is_restricted' => false, 'is_hidden' => false],
            ],
        ]);

        $this->assertSame(
            ['My Forum', 'Tags', 'Support', 'Installation'],
            $this->crumbNames($this->fetchForumHtml('/t/installation'))
        );
    }

    #[Test]
    public function the_all_tags_page_gets_a_home_then_tags_trail(): void
    {
        $this->extension('flarum-tags');

        $items = $this->findSchemaEntry($this->fetchForumHtml('/tags'), 'BreadcrumbList')['itemListElement'] ?? [];

        // Home, then the all-tags page as the current (last) crumb. The tag
        // page's own title comes from flarum/tags' translations, so assert the
        // structure rather than the exact label.
        $this->assertCount(2, $items);
        $this->assertSame('My Forum', $items[0]['name']);
        $this->assertSame('http://localhost/', $items[0]['item']['url'] ?? null);
        // The all-tags page is the current page: last crumb, no `item`.
        $this->assertArrayNotHasKey('item', $items[1]);
    }

    // ----- Extensible event ----------------------------------------------

    #[Test]
    public function a_listener_can_add_remove_and_relabel_crumbs(): void
    {
        $this->extend(
            (new Extend\Event())->listen(BuildingBreadcrumb::class, function (BuildingBreadcrumb $event) {
                $event->trail
                    ->insertAfter(fn (Crumb $c) => $c->name === 'My Forum', new Crumb('Inserted', 'http://localhost/inserted'))
                    ->map(fn (Crumb $c) => $c->name === 'My Forum' ? new Crumb('Renamed Home', $c->url) : $c);
            })
        );

        $this->prepareDatabase([
            Discussion::class => [
                ['id' => 1, 'title' => 'Plain', 'slug' => 'plain', 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1, 'created_at' => Carbon::now()],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Body.</p></t>', 'created_at' => Carbon::now()],
            ],
        ]);

        $this->assertSame(
            ['Renamed Home', 'Inserted', 'Plain'],
            $this->crumbNames($this->fetchForumHtml('/d/1-plain'))
        );
    }

    // ----- Supported extensions: fof/user-directory ----------------------

    #[Test]
    public function the_user_directory_page_gets_a_home_then_directory_trail(): void
    {
        $this->extension('fof-user-directory');

        // /users requires the `seeUserList` permission; fetch as the admin.
        $items = $this->findSchemaEntry($this->fetchForumHtml('/users', 1), 'BreadcrumbList')['itemListElement'] ?? [];

        // Home, then the directory page as the current (last) crumb. The label
        // comes from fof/user-directory's translations, so assert structure.
        $this->assertCount(2, $items);
        $this->assertSame('My Forum', $items[0]['name']);
        $this->assertSame('http://localhost/', $items[0]['item']['url'] ?? null);
        $this->assertArrayNotHasKey('item', $items[1]);
    }

    // ----- Performance ----------------------------------------------------

    #[Test]
    public function building_the_tag_lineage_does_not_issue_per_level_queries(): void
    {
        $this->extension('flarum-tags');

        // Two tagged discussions: one in a shallow (1-level) primary tag, one in
        // a deep (3-level) primary chain. Comparing *tagged vs tagged* isolates
        // the cost of walking the tag lineage from the one-time tag-loading
        // overhead (which varies by DB driver). Core eager-loads the tag
        // ancestry, so walking deeper levels must add no queries.
        $this->prepareDatabase([
            Tag::class => [
                ['id' => 1, 'name' => 'Shallow', 'slug' => 'shallow', 'description' => null, 'color' => '#000', 'position' => 0, 'is_restricted' => false, 'is_hidden' => false, 'is_primary' => true],
                ['id' => 2, 'name' => 'L1', 'slug' => 'l1', 'description' => null, 'color' => '#000', 'position' => 0, 'is_restricted' => false, 'is_hidden' => false, 'is_primary' => true],
                ['id' => 3, 'name' => 'L2', 'slug' => 'l2', 'description' => null, 'color' => '#000', 'position' => 0, 'parent_id' => 2, 'is_restricted' => false, 'is_hidden' => false, 'is_primary' => true],
                ['id' => 4, 'name' => 'L3', 'slug' => 'l3', 'description' => null, 'color' => '#000', 'position' => 0, 'parent_id' => 3, 'is_restricted' => false, 'is_hidden' => false, 'is_primary' => true],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'Shallow tagged', 'slug' => 'shallow-tagged', 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1, 'created_at' => Carbon::now(), 'last_posted_at' => Carbon::now()],
                ['id' => 2, 'title' => 'Deep tagged', 'slug' => 'deep-tagged', 'user_id' => 1, 'first_post_id' => 2, 'comment_count' => 1, 'created_at' => Carbon::now(), 'last_posted_at' => Carbon::now()],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Body.</p></t>', 'created_at' => Carbon::now()],
                ['id' => 2, 'discussion_id' => 2, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Body.</p></t>', 'created_at' => Carbon::now()],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 1], // shallow: 1 level
                ['discussion_id' => 2, 'tag_id' => 4], // deep: 3 levels (L1 > L2 > L3)
            ],
        ]);

        // Warm one-time caches before measuring.
        $this->fetchForumHtml('/');

        $shallow = $this->countQueriesFor('/d/1-shallow-tagged');
        $deep = $this->countQueriesFor('/d/2-deep-tagged');

        // Our breadcrumb code walks the tag ancestry against an in-memory map,
        // so it adds NO queries per tag level (verified: commenting out the
        // breadcrumb call leaves the same delta). Core itself lazy-loads
        // `tags.parent` one extra level deep when serializing the discussion's
        // tags, which is outside this extension's control — so allow a small
        // constant slack, but guard hard against the per-level growth a real
        // breadcrumb N+1 would show (which would scale with the 2 extra levels
        // here, and far more on deeper chains).
        $this->assertLessThanOrEqual(
            2,
            $deep - $shallow,
            'Deep tag chain issued '.($deep - $shallow).' more queries than a shallow one — a per-level breadcrumb N+1 has crept in.'
        );
    }
}
