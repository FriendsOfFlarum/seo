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

use Carbon\Carbon;
use Flarum\Post\Post;
use FoF\Seo\Support\PostDates;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PostDatesTest extends TestCase
{
    private function post(string $createdAt, ?string $editedAt): Post
    {
        // Raw Carbon attributes: casting them back needs no database connection.
        $post = new Post();
        $post->setRawAttributes([
            'created_at' => Carbon::parse($createdAt),
            'edited_at'  => $editedAt === null ? null : Carbon::parse($editedAt),
        ]);

        return $post;
    }

    #[Test]
    public function published_is_the_creation_time_in_iso_8601(): void
    {
        $this->assertSame('2025-01-01T10:00:00+00:00', PostDates::published($this->post('2025-01-01 10:00:00', null)));
    }

    #[Test]
    public function modified_is_the_edit_time_when_later_than_creation(): void
    {
        $this->assertSame('2025-01-02T09:30:00+00:00', PostDates::modified($this->post('2025-01-01 10:00:00', '2025-01-02 09:30:00')));
    }

    #[Test]
    public function modified_is_null_when_never_edited(): void
    {
        $this->assertNull(PostDates::modified($this->post('2025-01-01 10:00:00', null)));
    }

    #[Test]
    public function modified_is_null_when_the_edit_is_not_after_creation(): void
    {
        $this->assertNull(PostDates::modified($this->post('2025-01-01 10:00:00', '2025-01-01 10:00:00')));
        $this->assertNull(PostDates::modified($this->post('2025-01-01 10:00:00', '2024-12-31 23:00:00')));
    }
}
