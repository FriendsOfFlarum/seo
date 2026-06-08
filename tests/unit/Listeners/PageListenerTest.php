<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Tests\unit\Listeners;

use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\unit\TestCase;
use FoF\Seo\Listeners\PageListener;
use FoF\Seo\Page\PageManager;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Filesystem\Cloud;
use Mockery as m;

class PageListenerTest extends TestCase
{
    public function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    private function makeListener(): PageListener
    {
        $forumUrl = m::mock();
        $forumUrl->shouldReceive('base')->andReturn('https://forum.example.com');

        $url = m::mock(UrlGenerator::class);
        $url->shouldReceive('to')->with('forum')->andReturn($forumUrl);

        $filesystem = m::mock();
        $filesystem->shouldReceive('disk')->with('flarum-assets')->andReturn(m::mock(Cloud::class));

        $container = m::mock(Container::class);
        $container->shouldReceive('make')->with('filesystem')->andReturn($filesystem);

        return new PageListener(
            m::mock(SettingsRepositoryInterface::class),
            $url,
            m::mock(PageManager::class),
            m::mock(Dispatcher::class),
            $container,
        );
    }

    public function test_getImageFromContent_extracts_an_absolute_image_url(): void
    {
        $result = $this->makeListener()->getImageFromContent('<p><img src="https://i.imgur.com/abc.png"></p>');

        $this->assertSame('https://i.imgur.com/abc.png', $result);
    }

    /**
     * GH #72 — protocol-relative image URLs (`//host/img.png`) must be picked
     * up and normalised to an absolute https URL for og:image.
     */
    public function test_getImageFromContent_handles_protocol_relative_url(): void
    {
        $result = $this->makeListener()->getImageFromContent('<p><img src="//i.imgur.com/abc.png"></p>');

        $this->assertSame('https://i.imgur.com/abc.png', $result);
    }

    public function test_getImageFromContent_returns_null_when_there_is_no_image(): void
    {
        $result = $this->makeListener()->getImageFromContent('<p>No images here, just text.</p>');

        $this->assertNull($result);
    }

    /**
     * GH #149 — SVG images (e.g. shields.io badges) are rejected by Slack/X as
     * og:image, so they must not be auto-selected.
     */
    public function test_getImageFromContent_skips_svg_images(): void
    {
        $result = $this->makeListener()->getImageFromContent(
            '<p><img src="https://img.shields.io/badge/license-MIT-blue.svg"></p>'
        );

        $this->assertNull($result);
    }

    /**
     * When a post leads with an SVG badge but also contains a raster image,
     * the first raster image should be chosen (not the SVG, not null).
     */
    public function test_getImageFromContent_prefers_first_raster_image_over_svg(): void
    {
        $result = $this->makeListener()->getImageFromContent(
            '<p><img src="https://img.shields.io/badge/license-MIT-blue.svg">'
            .'<img src="https://i.imgur.com/screenshot.png"></p>'
        );

        $this->assertSame('https://i.imgur.com/screenshot.png', $result);
    }

    public function test_getImageFromContent_skips_svg_and_normalises_protocol_relative_raster(): void
    {
        $result = $this->makeListener()->getImageFromContent(
            '<p><img src="//cdn.example.com/badge.svg"><img src="//i.imgur.com/photo.jpg"></p>'
        );

        $this->assertSame('https://i.imgur.com/photo.jpg', $result);
    }
}
