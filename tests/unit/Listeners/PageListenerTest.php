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

use Flarum\Http\RouteCollectionUrlGenerator;
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
        // UrlGenerator::to() declares a RouteCollectionUrlGenerator return type,
        // so the mock it returns must satisfy that type.
        $forumUrl = m::mock(RouteCollectionUrlGenerator::class);
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
}
