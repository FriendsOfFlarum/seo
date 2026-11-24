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

use Flarum\Extend;
use FoF\Seo\Tests\integration\ForumHtmlTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The forum-wide social image and the publisher logo. Social platforms reject
 * images below about 200px and .ico/SVG files, so only the SEO social image and
 * the 512px app icon may be used as og:image; the logo and favicon may not.
 */
class SiteImageTest extends ForumHtmlTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-seo');

        $this->setting('forum_title', 'Example Forum');
    }

    private function uploadAppIcon(): void
    {
        $this->setting('app_icon_paths', json_encode([
            'icon-512.png'          => 'icon-512-abc.png',
            'icon-maskable-512.png' => 'icon-maskable-512-abc.png',
            'apple-touch-icon.png'  => 'apple-touch-icon-abc.png',
            'favicon.png'           => 'favicon-abc.png',
            'favicon.ico'           => 'favicon-abc.ico',
        ]));
    }

    private function publisher(string $html): array
    {
        return $this->findSchemaEntry($html, 'WebPage')['publisher'] ?? [];
    }

    #[Test]
    public function no_images_means_no_og_image_and_no_publisher_logo(): void
    {
        $html = $this->fetchForumHtml('/');

        $this->assertNull($this->findMetaByProperty($html, 'og:image'));
        $this->assertNull($this->findMetaByName($html, 'twitter:image'));
        $this->assertArrayNotHasKey('logo', $this->publisher($html));
    }

    #[Test]
    public function a_png_favicon_is_not_used_as_the_social_image(): void
    {
        $this->setting('favicon_path', 'favicon-abc.png');

        $html = $this->fetchForumHtml('/');

        $this->assertNull($this->findMetaByProperty($html, 'og:image'));
        $this->assertNull($this->findMetaByName($html, 'twitter:image'));
    }

    #[Test]
    public function an_ico_favicon_is_not_used_as_the_social_image(): void
    {
        $this->setting('favicon_path', 'favicon-abc.ico');

        $html = $this->fetchForumHtml('/');

        $this->assertNull($this->findMetaByProperty($html, 'og:image'));
        $this->assertNull($this->findMetaByName($html, 'twitter:image'));
    }

    #[Test]
    public function the_logo_is_the_publisher_logo_but_not_the_social_image(): void
    {
        $this->setting('logo_path', 'logo-abc.webp');

        $html = $this->fetchForumHtml('/');

        $this->assertNull($this->findMetaByProperty($html, 'og:image'));
        $this->assertSame('http://localhost/assets/logo-abc.webp', $this->publisher($html)['logo'] ?? null);
    }

    #[Test]
    public function the_app_icon_is_the_fallback_social_image_with_a_square_card(): void
    {
        $this->uploadAppIcon();
        $this->setting('favicon_path', 'favicon-abc.png');

        $html = $this->fetchForumHtml('/');

        $this->assertSame('http://localhost/assets/icon-512-abc.png', $this->findMetaByProperty($html, 'og:image'));
        $this->assertSame('http://localhost/assets/icon-512-abc.png', $this->findMetaByName($html, 'twitter:image'));
        $this->assertSame('summary', $this->findMetaByName($html, 'twitter:card'));
    }

    #[Test]
    public function the_app_icon_is_the_publisher_logo_when_there_is_no_logo(): void
    {
        $this->uploadAppIcon();

        $html = $this->fetchForumHtml('/');

        $this->assertSame('http://localhost/assets/icon-512-abc.png', $this->publisher($html)['logo'] ?? null);
    }

    #[Test]
    public function the_logo_wins_over_the_app_icon_as_publisher_logo(): void
    {
        $this->uploadAppIcon();
        $this->setting('logo_path', 'logo-abc.webp');

        $html = $this->fetchForumHtml('/');

        $this->assertSame('http://localhost/assets/logo-abc.webp', $this->publisher($html)['logo'] ?? null);
        $this->assertSame('http://localhost/assets/icon-512-abc.png', $this->findMetaByProperty($html, 'og:image'));
    }

    #[Test]
    public function the_seo_social_image_wins_and_keeps_the_large_card(): void
    {
        $this->uploadAppIcon();
        $this->setting('logo_path', 'logo-abc.webp');
        $this->setting('seo_social_media_image_path', 'site-image-abc.png');

        $html = $this->fetchForumHtml('/');

        $this->assertSame('http://localhost/assets/site-image-abc.png', $this->findMetaByProperty($html, 'og:image'));
        $this->assertSame('http://localhost/assets/site-image-abc.png', $this->findMetaByName($html, 'twitter:image'));
        $this->assertSame('summary_large_image', $this->findMetaByName($html, 'twitter:card'));
    }

    /**
     * A logo a theme sets replaces the uploaded one on the forum, so it must
     * replace it in structured data too. An SVG theme logo leaves `logo_path`
     * holding the old upload.
     */
    #[Test]
    public function a_theme_svg_logo_is_the_publisher_logo_and_never_the_social_image(): void
    {
        $this->setting('logo_path', 'logo-uploaded.webp');
        $this->extend((new Extend\Theme())->logo(__DIR__.'/../../fixtures/theme-logo.svg'));

        $html = $this->fetchForumHtml('/');

        $logo = $this->publisher($html)['logo'] ?? '';
        $this->assertMatchesRegularExpression('#^http://localhost/assets/theme-logo-image-[0-9a-f]+\.svg$#', $logo);
        $this->assertNull($this->findMetaByProperty($html, 'og:image'));
    }
}
