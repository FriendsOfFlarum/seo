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
use Flarum\Tags\Tag;
use FoF\Seo\Tests\integration\ForumHtmlTestCase;
use PHPUnit\Framework\Attributes\Test;

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
            Tag::class => [
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
                [
                    'id'            => 3,
                    'name'          => '動力',
                    'slug'          => '動力',
                    'description'   => 'A tag whose slug is not ASCII.',
                    'color'         => '#00FF00',
                    'position'      => 2,
                    'is_restricted' => false,
                    'is_hidden'     => false,
                ],
            ],
        ]);
    }

    #[Test]
    public function tag_page_is_website_type_without_article_date_tags(): void
    {
        $html = $this->fetchForumHtml('/t/announcements');

        // A tag listing is og:type "website" (the listing semantics live in the
        // schema.org CollectionPage); the article:* date tags belong to
        // article-type pages and must not leak onto it.
        $this->assertSame('website', $this->findMetaByProperty($html, 'og:type'));
        $this->assertNull($this->findMetaByProperty($html, 'article:published_time'));
        $this->assertNull($this->findMetaByProperty($html, 'article:modified_time'));
        $this->assertNull($this->findMetaByName($html, 'article:published_time'));
    }

    #[Test]
    public function tag_page_emits_og_title_from_tag_name(): void
    {
        $html = $this->fetchForumHtml('/t/announcements');

        $this->assertSame('Announcements', $this->findMetaByProperty($html, 'og:title'));
        $this->assertSame('Announcements', $this->findMetaByName($html, 'twitter:title'));
    }

    #[Test]
    public function tag_collection_page_lists_its_discussions_as_an_item_list(): void
    {
        $now = Carbon::now();

        $this->prepareDatabase([
            Discussion::class => [
                ['id' => 1, 'title' => 'First topic', 'slug' => 'first-topic', 'user_id' => 1, 'comment_count' => 1, 'created_at' => $now, 'last_posted_at' => $now],
                ['id' => 2, 'title' => 'Second topic', 'slug' => 'second-topic', 'user_id' => 1, 'comment_count' => 1, 'created_at' => $now, 'last_posted_at' => $now->copy()->addMinute()],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 1],
                ['discussion_id' => 2, 'tag_id' => 1],
            ],
        ]);

        $collectionPage = $this->findSchemaEntry($this->fetchForumHtml('/t/announcements'), 'CollectionPage');

        $this->assertSame('Announcements', $collectionPage['name'] ?? null);

        $list = $collectionPage['mainEntity'] ?? [];
        $this->assertSame('ItemList', $list['@type'] ?? null);
        $this->assertSame(2, $list['numberOfItems'] ?? null);
        $this->assertCount(2, $list['itemListElement'] ?? []);

        $first = $list['itemListElement'][0];
        $this->assertSame('ListItem', $first['@type'] ?? null);
        $this->assertSame(1, $first['position'] ?? null);
        $this->assertStringContainsString('/d/', $first['url'] ?? '');
        $this->assertNotEmpty($first['name'] ?? null);

        // Positions must be sequential, not all 1.
        $this->assertSame(2, $list['itemListElement'][1]['position'] ?? null);
        $this->assertSame(
            [1, 2],
            array_column($list['itemListElement'], 'position')
        );
    }

    #[Test]
    public function tag_page_emits_description_from_tag_description(): void
    {
        $html = $this->fetchForumHtml('/t/announcements');

        $this->assertSame('Important site announcements.', $this->findMetaByName($html, 'description'));
        $this->assertSame('Important site announcements.', $this->findMetaByProperty($html, 'og:description'));
        $this->assertSame('Important site announcements.', $this->findMetaByName($html, 'twitter:description'));
    }

    #[Test]
    public function tag_page_canonical_url_points_to_tag_slug(): void
    {
        $html = $this->fetchForumHtml('/t/announcements');

        $this->assertSame(
            'http://localhost/t/announcements',
            $this->findMetaByProperty($html, 'og:url')
        );
    }

    #[Test]
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
     */
    #[Test]
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

    #[Test]
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
        // The description is reduced to plain text — inline markup is stripped, the
        // remaining content is attribute-escaped (the regex above asserts safety).
        $this->assertSame('Description with "quotes" & markup.', $decoded);
    }

    #[Test]
    public function missing_tag_does_not_crash_the_seo_extension(): void
    {
        $response = $this->send($this->request('GET', '/t/nonexistent'));

        $this->assertContains($response->getStatusCode(), [200, 404]);
    }

    /**
     * A non-ASCII slug reaches the route percent-encoded, which the raw
     * getIdForSlug() lookup never matched — the null then reached
     * findOrFail(int) and the page returned a 500.
     */
    #[Test]
    public function tag_page_resolves_a_non_ascii_slug(): void
    {
        $html = $this->fetchForumHtml('/t/'.rawurlencode('動力'));

        $this->assertSame('動力', $this->findMetaByProperty($html, 'og:title'));

        $entry = $this->findSchemaEntry($html, 'CollectionPage');
        $this->assertNotNull($entry, 'Expected a CollectionPage entry for the non-ASCII tag.');
        $this->assertSame('動力', $entry['name'] ?? null);
    }

    /**
     * Under flarum/tags' `id_with_slug` driver the route param is `{id}-{slug}`,
     * which must resolve through the SlugManager (as flarum/tags itself does) and
     * be written back the same way in the page's URLs.
     */
    #[Test]
    public function tag_page_resolves_under_the_id_with_slug_driver(): void
    {
        $this->setting('slug_driver_Flarum\\Tags\\Tag', 'id_with_slug');

        $html = $this->fetchForumHtml('/t/1-announcements');

        $entry = $this->findSchemaEntry($html, 'CollectionPage');
        $this->assertNotNull($entry, 'Expected a CollectionPage entry under the id_with_slug driver.');
        $this->assertSame('Announcements', $entry['name'] ?? null);
        $this->assertSame('http://localhost/t/1-announcements', $this->findMetaByProperty($html, 'og:url'));
        $this->assertSame('http://localhost/t/1-announcements', $this->findCanonicalUrl($html));
    }
}
