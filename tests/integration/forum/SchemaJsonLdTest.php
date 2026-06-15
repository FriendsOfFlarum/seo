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
     */
    #[Test]
    public function website_entry_includes_the_forum_name(): void
    {
        $website = $this->findSchemaEntry($this->fetchForumHtml('/'), 'WebSite');

        $this->assertNotNull($website);
        $this->assertSame('Example Forum', $website['name'] ?? null);
    }

    #[Test]
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

    #[Test]
    public function web_page_declares_the_document_language(): void
    {
        $html = $this->fetchForumHtml('/');

        $webPage = $this->findSchemaEntry($html, 'WebPage');

        $this->assertNotNull($webPage);
        $this->assertSame('en', $webPage['inLanguage'] ?? null);
    }

    #[Test]
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

    #[Test]
    public function discussion_page_json_ld_breadcrumb_is_emitted_when_tags_present(): void
    {
        $this->extension('flarum-tags');

        $this->prepareDatabase([
            Discussion::class => [
                ['id' => 1, 'title' => 'Help with bread', 'slug' => 'help-bread', 'user_id' => 1, 'created_at' => Carbon::now(), 'comment_count' => 1],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Body.</p></t>', 'created_at' => Carbon::now()],
            ],
            Tag::class => [
                ['id' => 1, 'name' => 'Baking', 'slug' => 'baking', 'description' => null, 'color' => '#000', 'position' => 0, 'is_restricted' => false, 'is_hidden' => false],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 1],
            ],
        ]);

        $this->setting('forum_title', 'My Forum');

        $html = $this->fetchForumHtml('/d/1-help-bread');

        $breadcrumb = $this->findSchemaEntry($html, 'BreadcrumbList');

        $this->assertNotNull($breadcrumb, 'Expected a BreadcrumbList entry when tags are present.');

        $items = $breadcrumb['itemListElement'] ?? [];
        // Home › Tags › Baking › {discussion title}.
        $names = array_column($items, 'name');
        $this->assertSame(['My Forum', 'Tags', 'Baking', 'Help with bread'], $names);

        // Positions are sequential from 1.
        $this->assertSame([1, 2, 3, 4], array_column($items, 'position'));

        // The tag crumb links to the tag page...
        $this->assertSame('http://localhost/t/baking', $items[2]['item']['url'] ?? null);
        // ...and the last crumb (the discussion) omits `item` so Google uses
        // the page URL.
        $this->assertArrayNotHasKey('item', $items[3]);
    }

    /**
     * The block's root must be a single object carrying a string `@context`
     * and an `@graph` list — never a bare array. A bare-array root makes
     * `root["@context"]` undefined, crashing consumers that call
     * `root["@context"].toLowerCase()` (e.g. Safari's parser). Regression lock
     * for GH #177.
     */
    #[Test]
    public function json_ld_root_is_a_graph_object_not_a_bare_array(): void
    {
        $html = $this->fetchForumHtml('/');

        $this->assertSame(1, preg_match('/<script\s+type="application\/ld\+json"[^>]*>(.*?)<\/script>/is', $html, $matches));

        $root = json_decode($matches[1], true);

        $this->assertIsArray($root);
        $this->assertArrayHasKey('@context', $root, 'Root must carry a top-level @context.');
        // Canonical, current best-practice context: https, not http.
        $this->assertSame('https://schema.org', $root['@context']);
        $this->assertArrayHasKey('@graph', $root, 'Root must bundle nodes under @graph.');
        $this->assertIsArray($root['@graph']);
        $this->assertArrayNotHasKey(0, $root, 'Root must not be a bare list of nodes.');

        // Nodes don't repeat `@context` (declared once on the root).
        foreach ($root['@graph'] as $node) {
            $this->assertArrayNotHasKey('@context', $node);
        }
    }

    #[Test]
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
