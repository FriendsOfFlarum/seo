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
    public function forum_home_is_not_canonicalised_to_the_discussion_list(): void
    {
        $html = $this->fetchForumHtml('/');

        $this->assertSame('http://localhost', $this->findCanonicalUrl($html));
        $this->assertNotSame('http://localhost/all', $this->findMetaByProperty($html, 'og:url'));
    }
}
