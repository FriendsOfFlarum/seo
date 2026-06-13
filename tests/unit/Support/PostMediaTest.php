<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Tests\unit\Support;

use FoF\Seo\Support\PostMedia;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PostMediaTest extends TestCase
{
    #[Test]
    public function returns_empty_for_html_without_media(): void
    {
        $this->assertSame([], PostMedia::images('<p>Just some text.</p>'));
        $this->assertSame([], PostMedia::videos('<p>Just some text.</p>'));
        $this->assertSame([], PostMedia::images(''));
    }

    #[Test]
    public function extracts_a_plain_image(): void
    {
        $html = '<p>text and an <img src="https://example.com/a.png" alt=""></p>';

        $this->assertSame(['https://example.com/a.png'], PostMedia::images($html));
    }

    #[Test]
    public function extracts_a_fof_upload_image_preview(): void
    {
        // The shape fof/upload's image-preview template renders to (the <img>
        // is wrapped in a link, and the src is the thumbnail).
        $html = '<a href="https://forum.tld/f/x.png" class="FoFUpload--Upl-Image-Preview-Link"><img class="FoFUpload--Upl-Image-Preview" src="https://forum.tld/f/x-thumb.webp" alt="x.png" loading="lazy" width="800" height="800"/></a>';

        $this->assertSame(['https://forum.tld/f/x-thumb.webp'], PostMedia::images($html));
    }

    #[Test]
    public function decodes_html_entities_in_the_url(): void
    {
        // External images frequently carry `&amp;`-encoded query strings.
        $html = '<img src="https://img.tld/i.png?width=600&amp;height=600&amp;id=1">';

        $this->assertSame(['https://img.tld/i.png?width=600&height=600&id=1'], PostMedia::images($html));
    }

    #[Test]
    public function collects_multiple_images_in_order_and_deduplicates(): void
    {
        $html = '<img src="https://a.tld/1.png"><img src="https://a.tld/2.png"><img src="https://a.tld/1.png">';

        $this->assertSame(
            ['https://a.tld/1.png', 'https://a.tld/2.png'],
            PostMedia::images($html)
        );
    }

    #[Test]
    public function handles_single_quoted_src_attributes(): void
    {
        $html = "<img src='https://a.tld/q.png'>";

        $this->assertSame(['https://a.tld/q.png'], PostMedia::images($html));
    }

    #[Test]
    public function extracts_a_video_source(): void
    {
        // The shape s9e Autovideo renders to.
        $html = '<video controls="" src="https://a.tld/clip.mp4"></video>';

        $this->assertSame(['https://a.tld/clip.mp4'], PostMedia::videos($html));
        // A <video> is not an image.
        $this->assertSame([], PostMedia::images($html));
    }

    #[Test]
    public function schema_fields_are_empty_for_a_blank_post(): void
    {
        $this->assertSame([], PostMedia::schemaFields('<p></p>'));
        $this->assertSame([], PostMedia::schemaFields(''));
    }

    #[Test]
    public function schema_fields_emit_text_only(): void
    {
        $this->assertSame(['text' => 'Hello there'], PostMedia::schemaFields('<p>Hello there</p>'));
    }

    #[Test]
    public function schema_fields_emit_a_single_image_as_a_string(): void
    {
        $fields = PostMedia::schemaFields('<p>look <img src="https://a.tld/1.png"></p>');

        $this->assertSame('look', $fields['text'] ?? null);
        $this->assertSame('https://a.tld/1.png', $fields['image'] ?? null);
    }

    #[Test]
    public function schema_fields_emit_multiple_images_as_a_list(): void
    {
        $fields = PostMedia::schemaFields('<img src="https://a.tld/1.png"><img src="https://a.tld/2.png">');

        $this->assertArrayNotHasKey('text', $fields);
        $this->assertSame(['https://a.tld/1.png', 'https://a.tld/2.png'], $fields['image'] ?? null);
    }

    #[Test]
    public function schema_fields_wrap_a_single_video_as_a_video_object(): void
    {
        $fields = PostMedia::schemaFields('<video controls="" src="https://a.tld/clip.mp4"></video>');

        $this->assertArrayNotHasKey('text', $fields);
        $this->assertSame([
            '@type'      => 'VideoObject',
            'contentUrl' => 'https://a.tld/clip.mp4',
        ], $fields['video'] ?? null);
    }

    #[Test]
    public function schema_fields_wrap_multiple_videos_as_a_list_of_video_objects(): void
    {
        $fields = PostMedia::schemaFields('<video src="https://a.tld/1.mp4"></video><video src="https://a.tld/2.mp4"></video>');

        $this->assertSame([
            ['@type' => 'VideoObject', 'contentUrl' => 'https://a.tld/1.mp4'],
            ['@type' => 'VideoObject', 'contentUrl' => 'https://a.tld/2.mp4'],
        ], $fields['video'] ?? null);
    }

    #[Test]
    public function schema_fields_combine_text_image_and_video(): void
    {
        $fields = PostMedia::schemaFields('<p>caption</p><img src="https://a.tld/i.png"><video src="https://a.tld/v.mp4"></video>');

        $this->assertSame('caption', $fields['text'] ?? null);
        $this->assertSame('https://a.tld/i.png', $fields['image'] ?? null);
        $this->assertSame(['@type' => 'VideoObject', 'contentUrl' => 'https://a.tld/v.mp4'], $fields['video'] ?? null);
    }
}
