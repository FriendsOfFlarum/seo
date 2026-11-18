<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Support;

use Flarum\Post\Post;

/**
 * A post's own schema.org dates: `datePublished` is when it was made,
 * `dateModified` when it was last edited (Google's forum and Q&A guides).
 *
 * @internal
 */
final class PostDates
{
    public static function published(Post $post): string
    {
        return $post->created_at->toIso8601String();
    }

    /**
     * Null when the post was never edited. An edit time that isn't after the
     * creation time is treated as no edit (a local sanity rule, not Google's).
     */
    public static function modified(Post $post): ?string
    {
        $editedAt = $post->edited_at;

        if ($editedAt === null || !$editedAt->greaterThan($post->created_at)) {
            return null;
        }

        return $editedAt->toIso8601String();
    }
}
