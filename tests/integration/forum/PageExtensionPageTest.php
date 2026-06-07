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
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests the optional fof/pages integration.
 *
 * When fof/pages is enabled, the PageExtensionPage driver describes a custom
 * page (`/p/{id}-{slug}`) with a schema.org `WebPage`, a title/description
 * derived from the page content, and a canonical URL.
 */
class PageExtensionPageTest extends ForumHtmlTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-pages', 'fof-seo');

        $this->setting('forum_title', 'Example Forum');
        $this->setting('forum_description', 'An example forum.');

        $this->prepareDatabase([
            'pages' => [
                [
                    'id'            => 1,
                    'title'         => 'About us',
                    'slug'          => 'about-us',
                    'content'       => '<p>About our lovely community.</p>',
                    'is_html'       => 1,
                    'is_hidden'     => 0,
                    'is_restricted' => 0,
                    'created_at'    => Carbon::now(),
                ],
            ],
        ]);
    }

    #[Test]
    public function page_emits_webpage_schema_and_title(): void
    {
        $html = $this->fetchForumHtml('/p/1-about-us');

        $this->assertSame('About us', $this->findMetaByProperty($html, 'og:title'));

        $webPage = $this->findSchemaEntry($html, 'WebPage');
        $this->assertNotNull($webPage, 'Expected a WebPage entry for a fof/pages page.');
        $this->assertStringContainsString('About our lovely community.', $webPage['text'] ?? '');
    }

    #[Test]
    public function page_description_is_derived_from_content(): void
    {
        $html = $this->fetchForumHtml('/p/1-about-us');

        $this->assertSame('About our lovely community.', $this->findMetaByName($html, 'description'));
    }

    #[Test]
    public function page_sets_canonical_url(): void
    {
        $html = $this->fetchForumHtml('/p/1-about-us');

        $this->assertSame('http://localhost/p/1', $this->findCanonicalUrl($html));
    }
}
