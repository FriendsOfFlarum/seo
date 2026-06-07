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
use PHPUnit\Framework\Attributes\Test;

/**
 * Asserts what crawlers receive when hitting the forum index (`GET /`).
 */
class IndexPageTest extends ForumHtmlTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-seo');

        $this->setting('forum_title', 'Example Forum');
        $this->setting('forum_description', 'A place for examples.');
    }

    #[Test]
    public function index_page_renders_successfully_for_a_guest(): void
    {
        $html = $this->fetchForumHtml('/');

        $this->assertNotEmpty($html);
    }

    #[Test]
    public function index_page_sets_application_name_and_description_meta_from_settings(): void
    {
        $html = $this->fetchForumHtml('/');

        $this->assertSame('Example Forum', $this->findMetaByName($html, 'application-name'));
        $this->assertSame('A place for examples.', $this->findMetaByName($html, 'description'));
    }

    #[Test]
    public function index_page_emits_open_graph_tags(): void
    {
        $html = $this->fetchForumHtml('/');

        $this->assertSame('Example Forum', $this->findMetaByProperty($html, 'og:site_name'));
        $this->assertSame('website', $this->findMetaByProperty($html, 'og:type'));
        $this->assertSame('Example Forum', $this->findMetaByProperty($html, 'og:title'));
        $this->assertSame('A place for examples.', $this->findMetaByProperty($html, 'og:description'));
        $this->assertStringStartsWith('http://', $this->findMetaByProperty($html, 'og:url'));
    }

    #[Test]
    public function index_page_emits_twitter_card_tags(): void
    {
        $html = $this->fetchForumHtml('/');

        $this->assertSame('summary_large_image', $this->findMetaByName($html, 'twitter:card'));
        $this->assertSame('Example Forum', $this->findMetaByName($html, 'twitter:title'));
        $this->assertSame('A place for examples.', $this->findMetaByName($html, 'twitter:description'));
    }

    #[Test]
    public function twitter_card_type_respects_the_seo_twitter_card_size_setting(): void
    {
        $this->setting('seo_twitter_card_size', 'summary');

        $html = $this->fetchForumHtml('/');

        $this->assertSame('summary', $this->findMetaByName($html, 'twitter:card'));
    }

    #[Test]
    public function default_robots_meta_allows_indexing(): void
    {
        $html = $this->fetchForumHtml('/');

        $this->assertSame('index, follow', $this->findMetaByName($html, 'robots'));
    }

    #[Test]
    public function index_page_emits_canonical_url(): void
    {
        $html = $this->fetchForumHtml('/');

        $this->assertNotEmpty($this->findCanonicalUrl($html));
    }

    #[Test]
    public function index_page_emits_schema_org_webpage_and_website_entries(): void
    {
        $html = $this->fetchForumHtml('/');

        $webPage = $this->findSchemaEntry($html, 'WebPage');
        $this->assertNotNull($webPage, 'Expected a WebPage JSON-LD entry.');
        $this->assertSame('Example Forum', $webPage['publisher']['name'] ?? null);
        $this->assertSame('A place for examples.', $webPage['description'] ?? null);

        $webSite = $this->findSchemaEntry($html, 'WebSite');
        $this->assertNotNull($webSite, 'Expected a WebSite JSON-LD entry with SearchAction.');
        $this->assertSame('SearchAction', $webSite['potentialAction']['@type'] ?? null);
        $this->assertStringContainsString('{search_term_string}', $webSite['potentialAction']['target'] ?? '');
    }

    /**
     * HTML escaping is applied to the forum_title coming from settings so
     * that crawler-visible meta tag values cannot contain raw HTML. The HTML
     * entities should appear escaped, never as live markup.
     */
    #[Test]
    public function forum_title_containing_html_is_escaped_in_meta_tags(): void
    {
        $this->setting('forum_title', 'Example <script>alert(1)</script> Forum');

        $html = $this->fetchForumHtml('/');

        $this->assertStringNotContainsString(
            '<script>alert(1)</script>',
            $html,
            'Unescaped script tag from forum_title leaked into the response.'
        );

        // The entity-decoded meta content should round-trip to the original.
        $this->assertSame(
            'Example <script>alert(1)</script> Forum',
            $this->findMetaByName($html, 'application-name')
        );
    }
}
