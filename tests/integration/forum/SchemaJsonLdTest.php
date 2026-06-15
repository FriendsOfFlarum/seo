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
use FoF\Seo\Tests\integration\ForumHtmlTestCase;

/**
 * Structural tests for the JSON-LD schema.org block that the extension
 * emits on every forum route. Crawlers (Google in particular) consume
 * this for rich snippets, so validity and required fields matter.
 */
class SchemaJsonLdTest extends ForumHtmlTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-seo');

        $this->setting('forum_title', 'Example Forum');
        $this->setting('forum_description', 'An example forum.');
    }

    /**
     * The WebSite entry must carry the forum name so search engines display it
     * (rather than the bare domain). Regression lock for GH #115.
     *
     * @test
     */
    public function website_entry_includes_the_forum_name(): void
    {
        $website = $this->findSchemaEntry($this->fetchForumHtml('/'), 'WebSite');

        $this->assertNotNull($website);
        $this->assertSame('Example Forum', $website['name'] ?? null);
    }

    /**
     * @test
     */
    public function every_forum_page_emits_the_website_search_action_entry(): void
    {
        $html = $this->fetchForumHtml('/');

        $website = $this->findSchemaEntry($html, 'WebSite');

        $this->assertNotNull($website, 'Expected a WebSite entry on every page.');
        $this->assertSame('http://localhost/', $website['url'] ?? null);

        $this->assertSame('SearchAction', $website['potentialAction']['@type'] ?? null);
        $this->assertSame(
            'http://localhost/?q={search_term_string}',
            $website['potentialAction']['target'] ?? null
        );
        $this->assertSame(
            'required name=search_term_string',
            $website['potentialAction']['query-input'] ?? null
        );
    }

    /**
     * @test
     */
    public function web_page_declares_the_document_language(): void
    {
        $html = $this->fetchForumHtml('/');

        $webPage = $this->findSchemaEntry($html, 'WebPage');

        $this->assertNotNull($webPage);
        $this->assertSame('en', $webPage['inLanguage'] ?? null);
    }

    /**
     * @test
     */
    public function publisher_block_is_populated_from_forum_settings(): void
    {
        $html = $this->fetchForumHtml('/');

        $webPage = $this->findSchemaEntry($html, 'WebPage');

        $this->assertNotNull($webPage);

        $publisher = $webPage['publisher'] ?? null;
        $this->assertSame('Organization', $publisher['@type'] ?? null);
        $this->assertSame('Example Forum', $publisher['name'] ?? null);
        $this->assertSame('An example forum.', $publisher['description'] ?? null);
        $this->assertSame('http://localhost', $publisher['url'] ?? null);
    }

    /**
     * @test
     */
    public function discussion_page_json_ld_breadcrumb_is_emitted_when_tags_present(): void
    {
        $this->extension('flarum-tags');

        $this->prepareDatabase([
            'discussions' => [
                ['id' => 1, 'title' => 'Help with bread', 'slug' => 'help-bread', 'user_id' => 1, 'created_at' => Carbon::now(), 'comment_count' => 1],
            ],
            'posts' => [
                ['id' => 1, 'discussion_id' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Body.</p></t>', 'created_at' => Carbon::now()],
            ],
            'tags' => [
                ['id' => 1, 'name' => 'Baking', 'slug' => 'baking', 'description' => null, 'color' => '#000', 'position' => 0, 'is_restricted' => false, 'is_hidden' => false],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 1],
            ],
        ]);

        $html = $this->fetchForumHtml('/d/1-help-bread');

        $breadcrumb = $this->findSchemaEntry($html, 'BreadcrumbList');

        $this->assertNotNull($breadcrumb, 'Expected a BreadcrumbList entry when tags are present.');
        $this->assertNotEmpty($breadcrumb['itemListElement'] ?? []);

        $first = $breadcrumb['itemListElement'][0];
        $this->assertSame('ListItem', $first['@type']);
        $this->assertSame(1, $first['position']);
        $this->assertSame('Baking', $first['item']['name'] ?? null);
        $this->assertSame('http://localhost/t/baking', $first['item']['url'] ?? null);
    }

    /**
     * The block's root must be a single object carrying a string `@context`
     * and an `@graph` list — never a bare array. A bare-array root makes
     * `root["@context"]` undefined, crashing consumers that call
     * `root["@context"].toLowerCase()` (e.g. Safari's parser). Regression lock.
     *
     * @test
     */
    public function json_ld_root_is_a_graph_object_not_a_bare_array(): void
    {
        $html = $this->fetchForumHtml('/');

        $this->assertSame(1, preg_match('/<script\s+type="application\/ld\+json"[^>]*>(.*?)<\/script>/is', $html, $matches));

        $root = json_decode($matches[1], true);

        $this->assertIsArray($root);
        $this->assertArrayHasKey('@context', $root, 'Root must carry a top-level @context.');
        $this->assertIsString($root['@context']);
        $this->assertArrayHasKey('@graph', $root, 'Root must bundle nodes under @graph.');
        $this->assertIsArray($root['@graph']);
        $this->assertArrayNotHasKey(0, $root, 'Root must not be a bare list of nodes.');
    }

    /**
     * @test
     */
    public function json_ld_block_is_always_valid_json(): void
    {
        // Sample across several pages to confirm JSON validity universally.
        $paths = ['/', '/u/admin'];

        foreach ($paths as $path) {
            $html = $this->fetchForumHtml($path);

            $entries = $this->findSchemaJsonLd($html);

            $this->assertIsArray($entries, "JSON-LD on {$path} failed to parse as JSON.");
            $this->assertGreaterThanOrEqual(
                1,
                count($entries),
                "Expected at least one JSON-LD entry on {$path}."
            );
        }
    }
}
