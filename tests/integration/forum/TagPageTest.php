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

use FoF\Seo\Tests\integration\ForumHtmlTestCase;

/**
 * What crawlers see on a tag page (`GET /t/{slug}`).
 *
 * Requires flarum/tags.
 */
class TagPageTest extends ForumHtmlTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-tags');
        $this->extension('fof-seo');

        $this->prepareDatabase([
            'tags' => [
                [
                    'id'            => 1,
                    'name'          => 'Announcements',
                    'slug'          => 'announcements',
                    'description'   => 'Important site announcements.',
                    'color'         => '#FF0000',
                    'position'      => 0,
                    'is_restricted' => false,
                    'is_hidden'     => false,
                ],
                [
                    'id'            => 2,
                    'name'          => 'Hostile <script>alert(1)</script>',
                    'slug'          => 'hostile',
                    'description'   => 'Description with "quotes" & <em>markup</em>.',
                    'color'         => '#000000',
                    'position'      => 1,
                    'is_restricted' => false,
                    'is_hidden'     => false,
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function tag_page_emits_og_title_from_tag_name(): void
    {
        $html = $this->fetchForumHtml('/t/announcements');

        $this->assertSame('Announcements', $this->findMetaByProperty($html, 'og:title'));
        $this->assertSame('Announcements', $this->findMetaByName($html, 'twitter:title'));
    }

    /**
     * @test
     */
    public function tag_page_emits_description_from_tag_description(): void
    {
        $html = $this->fetchForumHtml('/t/announcements');

        $this->assertSame('Important site announcements.', $this->findMetaByName($html, 'description'));
        $this->assertSame('Important site announcements.', $this->findMetaByProperty($html, 'og:description'));
        $this->assertSame('Important site announcements.', $this->findMetaByName($html, 'twitter:description'));
    }

    /**
     * @test
     */
    public function tag_page_canonical_url_points_to_tag_slug(): void
    {
        $html = $this->fetchForumHtml('/t/announcements');

        $this->assertSame(
            'http://localhost/t/announcements',
            $this->findMetaByProperty($html, 'og:url')
        );
    }

    /**
     * @test
     */
    public function tag_page_emits_schema_org_collection_page(): void
    {
        $html = $this->fetchForumHtml('/t/announcements');

        $entry = $this->findSchemaEntry($html, 'CollectionPage');

        $this->assertNotNull($entry, 'Expected a CollectionPage JSON-LD entry.');
        $this->assertSame('http://localhost/t/announcements', $entry['url'] ?? null);
        $this->assertSame('Important site announcements.', $entry['about'] ?? null);
    }

    /**
     * Tag names or descriptions containing UGC-style HTML must be escaped
     * in meta tags; the raw `<script>` must never reach the rendered page.
     *
     * @test
     */
    public function tag_with_html_in_name_is_escaped_in_meta_tags(): void
    {
        $html = $this->fetchForumHtml('/t/hostile');

        $this->assertStringNotContainsString(
            '<script>alert(1)</script>',
            $html,
            'Unescaped script tag from tag name leaked into rendered HTML.'
        );

        $this->assertSame(
            'Hostile <script>alert(1)</script>',
            $this->findMetaByProperty($html, 'og:title')
        );
    }

    /**
     * @test
     */
    public function tag_with_html_in_description_is_escaped_in_meta_tags(): void
    {
        $html = $this->fetchForumHtml('/t/hostile');

        // <em> from the description must not render live in the meta value.
        $this->assertMatchesRegularExpression(
            '/<meta\s+name="description"\s+content="[^"]*"/',
            $html,
            'description meta tag malformed around UGC content.'
        );

        $decoded = $this->findMetaByName($html, 'description');
        $this->assertSame('Description with "quotes" & <em>markup</em>.', $decoded);
    }

    /**
     * @test
     */
    public function missing_tag_does_not_crash_the_seo_extension(): void
    {
        $response = $this->send($this->request('GET', '/t/nonexistent'));

        $this->assertContains($response->getStatusCode(), [200, 404]);
    }
}
