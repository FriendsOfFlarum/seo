<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Tests\unit;

use Flarum\Testing\unit\TestCase;
use FoF\Seo\Listeners\PageListener;
use FoF\Seo\SeoProperties;
use Mockery as m;

class SeoPropertiesTest extends TestCase
{
    private SeoProperties $properties;

    public function setUp(): void
    {
        parent::setUp();

        $this->properties = new SeoProperties(m::mock(PageListener::class));
    }

    public function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function test_generateDescriptionFromContent_strips_html_tags(): void
    {
        $result = $this->properties->generateDescriptionFromContent('<p>Hello <strong>world</strong></p>');

        $this->assertSame('Hello world', $result);
    }

    public function test_generateDescriptionFromContent_collapses_whitespace(): void
    {
        $result = $this->properties->generateDescriptionFromContent("one\n\ntwo    three\tfour");

        $this->assertSame('one two three four', $result);
    }

    public function test_generateDescriptionFromContent_truncates_at_157_chars_and_appends_ellipsis(): void
    {
        $long = str_repeat('a', 200);

        $result = $this->properties->generateDescriptionFromContent($long);

        $this->assertSame(str_repeat('a', 157).'...', $result);
    }

    public function test_generateDescriptionFromContent_leaves_short_strings_untouched(): void
    {
        $result = $this->properties->generateDescriptionFromContent('Short description.');

        $this->assertSame('Short description.', $result);
    }

    public function test_generateDescriptionFromContent_returns_exactly_157_chars_without_ellipsis_when_source_is_157_chars(): void
    {
        $source = str_repeat('a', 157);

        $result = $this->properties->generateDescriptionFromContent($source);

        $this->assertSame($source, $result);
    }
}
