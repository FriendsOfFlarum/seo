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
use Flarum\Tags\Tag;
use FoF\Seo\Tests\integration\ForumHtmlTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Admins can keep every discussion in chosen tags (and those tags' own listing
 * pages) out of the search index via the `seo_noindex_tags` setting (GH #117).
 */
class NoindexTagsTest extends ForumHtmlTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-seo');
        $this->extension('flarum-tags');

        $this->setting('forum_title', 'Example Forum');

        $this->prepareDatabase([
            Tag::class => [
                ['id' => 1, 'name' => 'Parent', 'slug' => 'parent', 'description' => null, 'color' => '#000', 'position' => 0, 'is_restricted' => false, 'is_hidden' => false],
                ['id' => 2, 'name' => 'Child', 'slug' => 'child', 'description' => null, 'color' => '#000', 'position' => 1, 'is_restricted' => false, 'is_hidden' => false, 'parent_id' => 1],
                ['id' => 3, 'name' => 'Unrelated', 'slug' => 'unrelated', 'description' => null, 'color' => '#000', 'position' => 2, 'is_restricted' => false, 'is_hidden' => false],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'In unrelated tag', 'slug' => 'in-unrelated', 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1, 'created_at' => Carbon::now()],
                ['id' => 2, 'title' => 'In parent tag', 'slug' => 'in-parent', 'user_id' => 1, 'first_post_id' => 2, 'comment_count' => 1, 'created_at' => Carbon::now()],
                ['id' => 3, 'title' => 'In child tag', 'slug' => 'in-child', 'user_id' => 1, 'first_post_id' => 3, 'comment_count' => 1, 'created_at' => Carbon::now()],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Body.</p></t>', 'created_at' => Carbon::now()],
                ['id' => 2, 'discussion_id' => 2, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Body.</p></t>', 'created_at' => Carbon::now()],
                ['id' => 3, 'discussion_id' => 3, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Body.</p></t>', 'created_at' => Carbon::now()],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 3],
                ['discussion_id' => 2, 'tag_id' => 1],
                ['discussion_id' => 3, 'tag_id' => 2],
            ],
        ]);
    }

    #[Test]
    public function discussions_are_indexable_when_no_tags_are_excluded(): void
    {
        $html = $this->fetchForumHtml('/d/1-in-unrelated');

        $this->assertSame('index, follow', $this->findMetaByName($html, 'robots'));
    }

    /**
     * A discussion tagged with an excluded tag is de-indexed.
     */
    #[Test]
    public function discussion_in_an_excluded_tag_is_noindexed(): void
    {
        $this->setting('seo_noindex_tags', json_encode([3]));

        $html = $this->fetchForumHtml('/d/1-in-unrelated');

        $this->assertSame('noindex, follow', $this->findMetaByName($html, 'robots'));
    }

    /**
     * A discussion in a tag that is *not* excluded stays indexable even when
     * other tags are excluded.
     */
    #[Test]
    public function discussion_outside_excluded_tags_stays_indexable(): void
    {
        $this->setting('seo_noindex_tags', json_encode([3]));

        $html = $this->fetchForumHtml('/d/2-in-parent');

        $this->assertSame('index, follow', $this->findMetaByName($html, 'robots'));
    }

    /**
     * Excluding a parent tag also de-indexes discussions in its child tags
     * (flarum-tags nests one level).
     */
    #[Test]
    public function discussion_in_a_child_of_an_excluded_tag_is_noindexed(): void
    {
        $this->setting('seo_noindex_tags', json_encode([1]));

        $html = $this->fetchForumHtml('/d/3-in-child');

        $this->assertSame('noindex, follow', $this->findMetaByName($html, 'robots'));
    }

    /**
     * The excluded tag's own listing page is de-indexed too.
     */
    #[Test]
    public function the_excluded_tag_listing_page_is_noindexed(): void
    {
        $this->setting('seo_noindex_tags', json_encode([3]));

        $html = $this->fetchForumHtml('/t/unrelated');

        $this->assertSame('noindex, follow', $this->findMetaByName($html, 'robots'));
    }

    /**
     * A child tag whose parent is excluded has its listing page de-indexed.
     */
    #[Test]
    public function child_tag_listing_page_of_excluded_parent_is_noindexed(): void
    {
        $this->setting('seo_noindex_tags', json_encode([1]));

        $html = $this->fetchForumHtml('/t/child');

        $this->assertSame('noindex, follow', $this->findMetaByName($html, 'robots'));
    }

    /**
     * A tag that is not excluded keeps its listing page indexable.
     */
    #[Test]
    public function non_excluded_tag_listing_page_stays_indexable(): void
    {
        $this->setting('seo_noindex_tags', json_encode([3]));

        $html = $this->fetchForumHtml('/t/parent');

        $this->assertSame('index, follow', $this->findMetaByName($html, 'robots'));
    }
}
