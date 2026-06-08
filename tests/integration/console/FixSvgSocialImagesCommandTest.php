<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Tests\integration\console;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Testing\integration\ConsoleTestCase;
use Flarum\User\User;
use FoF\Seo\SeoMeta\SeoMeta;
use PHPUnit\Framework\Attributes\Test;

/**
 * The fof:seo:fix-svg-images command re-derives og:image for discussions whose
 * auto-selected image is an SVG (GH #149): re-pointing to the first raster
 * image in the post, or clearing it when none exists. Manual / other-extension
 * managed images are left untouched.
 *
 * Note: the bare test formatter renders no `<img>` tags (image support comes
 * from a markdown extension absent here), so re-derivation always yields null
 * — i.e. the "clear" path. The first-raster *selection* itself is covered by
 * the unit tests for getImageFromContent. These tests pin down the command's
 * own behaviour: which rows it touches, clearing, dry-run, and source guarding.
 */
class FixSvgSocialImagesCommandTest extends ConsoleTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-seo');

        $this->prepareDatabase([
            User::class => [
                ['id' => 1, 'username' => 'admin', 'email' => 'admin@machine.local', 'is_email_confirmed' => 1],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'Has a raster image', 'slug' => 'one', 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1, 'created_at' => Carbon::now()],
                ['id' => 2, 'title' => 'Only an SVG badge', 'slug' => 'two', 'user_id' => 1, 'first_post_id' => 2, 'comment_count' => 1, 'created_at' => Carbon::now()],
                ['id' => 3, 'title' => 'Manually set image', 'slug' => 'three', 'user_id' => 1, 'first_post_id' => 3, 'comment_count' => 1, 'created_at' => Carbon::now()],
            ],
            Post::class => [
                // Leads with an SVG badge, then a real raster screenshot.
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<r><p><IMG src="https://img.shields.io/badge/x.svg">badge</IMG> <IMG src="https://i.imgur.com/shot.png">shot</IMG></p></r>', 'created_at' => Carbon::now()],
                // Only an SVG badge — nothing usable.
                ['id' => 2, 'discussion_id' => 2, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<r><p><IMG src="https://img.shields.io/badge/y.svg">badge</IMG></p></r>', 'created_at' => Carbon::now()],
                ['id' => 3, 'discussion_id' => 3, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>no image</p></t>', 'created_at' => Carbon::now()],
            ],
            'seo_meta' => [
                ['id' => 1, 'object_type' => 'discussions', 'object_id' => 1, 'auto_update_data' => 1, 'open_graph_image' => 'https://img.shields.io/badge/x.svg', 'open_graph_image_source' => 'auto', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
                ['id' => 2, 'object_type' => 'discussions', 'object_id' => 2, 'auto_update_data' => 1, 'open_graph_image' => 'https://img.shields.io/badge/y.svg', 'open_graph_image_source' => 'auto', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
                // Manually-set SVG — must NOT be touched.
                ['id' => 3, 'object_type' => 'discussions', 'object_id' => 3, 'auto_update_data' => 1, 'open_graph_image' => 'https://example.com/manual.svg', 'open_graph_image_source' => 'manual', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ],
        ]);
    }

    #[Test]
    public function it_clears_auto_svg_images_and_leaves_manual_ones(): void
    {
        $this->runCommand(['command' => 'fof:seo:fix-svg-images']);

        // Discussions 1 & 2: auto SVGs cleared (no raster derivable here), so
        // rendering falls back to the forum social image.
        $this->assertNull(SeoMeta::find(1)->open_graph_image);
        $this->assertNull(SeoMeta::find(2)->open_graph_image);

        // Discussion 3: manual source untouched.
        $this->assertSame('https://example.com/manual.svg', SeoMeta::find(3)->open_graph_image);
        $this->assertSame('manual', SeoMeta::find(3)->open_graph_image_source);
    }

    #[Test]
    public function dry_run_writes_nothing(): void
    {
        $this->runCommand(['command' => 'fof:seo:fix-svg-images', '--dry-run' => true]);

        // Unchanged: still the original SVGs.
        $this->assertSame('https://img.shields.io/badge/x.svg', SeoMeta::find(1)->open_graph_image);
        $this->assertSame('https://img.shields.io/badge/y.svg', SeoMeta::find(2)->open_graph_image);
    }
}
