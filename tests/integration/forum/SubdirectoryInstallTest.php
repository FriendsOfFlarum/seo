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
 * In a subdirectory install the bare forum URL (`/forum`) is a 301 to
 * `/forum/`, so the home's canonical must carry the trailing slash (#193).
 */
class SubdirectoryInstallTest extends ForumHtmlTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->config('url', 'http://localhost/forum');

        $this->extension('flarum-tags');
        $this->extension('fof-seo');
    }

    #[Test]
    public function home_canonical_and_og_url_keep_the_trailing_slash(): void
    {
        $html = $this->fetchForumHtml('/');

        $this->assertSame('http://localhost/forum/', $this->findCanonicalUrl($html));
        $this->assertSame('http://localhost/forum/', $this->findMetaByProperty($html, 'og:url'));
    }

    #[Test]
    public function tags_page_as_home_keeps_the_trailing_slash(): void
    {
        $this->setting('default_route', '/tags');

        $html = $this->fetchForumHtml('/tags');

        $this->assertSame('http://localhost/forum/', $this->findCanonicalUrl($html));
    }

    #[Test]
    public function other_pages_get_no_slash_added(): void
    {
        $html = $this->fetchForumHtml('/tags');

        $this->assertSame('http://localhost/forum/tags', $this->findCanonicalUrl($html));
    }
}
