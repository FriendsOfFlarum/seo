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
 * When the forum home (`default_route`) is something other than `/all`, the
 * discussion list lives at `/all` and `/` serves a different page. Each must
 * be canonical to itself, or crawlers fold one into the other.
 */
class NonDefaultIndexRouteTest extends ForumHtmlTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-tags');
        $this->extension('fof-seo');

        $this->setting('forum_title', 'Example Forum');
        $this->setting('default_route', '/tags');
    }

    #[Test]
    public function all_discussions_page_is_canonical_to_itself(): void
    {
        $html = $this->fetchForumHtml('/all');

        $this->assertSame('http://localhost/all', $this->findCanonicalUrl($html));
        $this->assertSame('http://localhost/all', $this->findMetaByProperty($html, 'og:url'));
    }

    #[Test]
    public function paginated_all_discussions_page_is_canonical_to_its_own_page(): void
    {
        // Core's Index reads the page from parsed query params, which a bare
        // path string doesn't populate on a test request.
        $response = $this->send($this->request('GET', '/all')->withQueryParams(['page' => '2']));
        $html = (string) $response->getBody();

        $this->assertSame('http://localhost/all?page=2', $this->findCanonicalUrl($html));
    }

    #[Test]
    public function all_discussions_page_keeps_its_own_title(): void
    {
        $html = $this->fetchForumHtml('/all');

        $this->assertSame('All Discussions - Example Forum', $this->findTitle($html));
    }

    #[Test]
    public function tags_page_is_canonical_to_the_forum_home_when_it_is_the_home(): void
    {
        $html = $this->fetchForumHtml('/tags');

        $this->assertSame('http://localhost', $this->findCanonicalUrl($html));
        $this->assertSame('http://localhost', $this->findMetaByProperty($html, 'og:url'));
    }

    #[Test]
    public function forum_home_is_described_by_the_page_it_serves(): void
    {
        $home = $this->fetchForumHtml('/');
        $tags = $this->fetchForumHtml('/tags');

        // Compared against /tags rather than a literal: the test app loads no
        // locale, so the tags page title renders as its translation key.
        $this->assertNotNull($this->findMetaByProperty($tags, 'og:title'), 'precondition: /tags has an og:title');
        $this->assertSame($this->findMetaByProperty($tags, 'og:title'), $this->findMetaByProperty($home, 'og:title'));
        $this->assertNotNull($this->findSchemaEntry($home, 'CollectionPage'), 'Expected the tags page CollectionPage schema on the home.');
    }

    #[Test]
    public function forum_home_has_no_breadcrumb_of_its_own(): void
    {
        $html = $this->fetchForumHtml('/');

        $this->assertNull($this->findSchemaEntry($html, 'BreadcrumbList'));
    }

    #[Test]
    public function forum_home_is_not_canonicalised_to_the_discussion_list(): void
    {
        $html = $this->fetchForumHtml('/');

        $this->assertSame('http://localhost', $this->findCanonicalUrl($html));
        $this->assertNotSame('http://localhost/all', $this->findMetaByProperty($html, 'og:url'));
    }
}
