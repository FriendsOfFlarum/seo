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
use FoF\Seo\Tests\integration\BuildsSitemap;
use FoF\Seo\Tests\integration\ForumHtmlTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * fof/pages Pages with a per-item `noindex` are left out of the sitemap.
 */
class SitemapPagesIndexabilityTest extends ForumHtmlTestCase
{
    use BuildsSitemap;

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-pages', 'fof-sitemap', 'fof-seo');

        $now = Carbon::parse('2026-01-01 12:00:00');
        $page = fn (int $id, string $slug, array $extra = []) => array_merge([
            'id' => $id, 'title' => ucfirst($slug), 'slug' => $slug, 'content' => '<p>Content.</p>',
            'is_html' => 1, 'is_hidden' => 0, 'is_restricted' => 0, 'created_at' => $now, 'updated_at' => $now,
        ], $extra);

        $this->prepareDatabase([
            'pages' => [
                $page(1, 'noindexed'),
                $page(2, 'indexable'),
                $page(3, 'hidden', ['is_hidden' => 1]),
            ],
            'seo_meta' => [
                ['object_type' => 'pages', 'object_id' => 1, 'robots_noindex' => true, 'created_at' => $now, 'updated_at' => $now],
                // A discussion row with the same ID must not affect Page 2.
                ['object_type' => 'discussions', 'object_id' => 2, 'robots_noindex' => true, 'created_at' => $now, 'updated_at' => $now],
            ],
        ]);
    }

    #[Test]
    public function pages_with_item_noindex_are_left_out(): void
    {
        $paths = $this->pathsOf($this->buildSitemapUrls());

        $this->assertContains('/p/2-indexable', $paths);
        $this->assertNotContains('/p/1-noindexed', $paths);
        $this->assertNotContains('/p/3-hidden', $paths, 'Guest visibility still applies');
    }

    #[Test]
    public function sitemap_agrees_with_rendered_robots_meta_for_pages(): void
    {
        $paths = $this->pathsOf($this->buildSitemapUrls());

        foreach (['/p/1-noindexed', '/p/2-indexable'] as $path) {
            $robots = (string) $this->findMetaByName($this->fetchForumHtml($path), 'robots');

            $this->assertSame(str_contains($robots, 'noindex'), !in_array($path, $paths, true), "$path renders robots \"$robots\"");
        }
    }
}
