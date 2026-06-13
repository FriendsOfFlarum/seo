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

/**
 * Pull media URLs out of rendered post HTML. s9e/TextFormatter renders every
 * media source to a standard HTML tag, so a single `src` match per tag type
 * covers every plugin/extension:
 *
 *  - images:  markdown / Autoimage / fof/upload image-preview → `<img src>`
 *  - videos:  Autovideo (mp4/webm/…)                          → `<video src>`
 *
 * MediaEmbed `<iframe>` embeds (YouTube, Vimeo, …) are deliberately ignored:
 * a schema.org VideoObject built from an embed URL alone lacks the name,
 * thumbnail and uploadDate Google expects and would invite its own warnings.
 */
class PostMedia
{
    /**
     * Plain text rendered from post HTML: decode entities and strip tags so
     * mentions/links reduce to their visible label. The schema `text` field.
     */
    public static function text(string $html): string
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5));
    }

    /**
     * Build the schema.org content fields for a post's rendered HTML — `text`,
     * `image` and/or `video` — so a Comment/Question/Answer node always carries
     * at least one of the three Google requires (when the post has any content
     * at all). `image` is a string for a single image or a list for several;
     * `video` is a VideoObject (or a list of them) carrying the `contentUrl`.
     *
     * Returns only the keys that are present, so callers can merge the result
     * straight into their node and check `=== []` to detect an empty post.
     *
     * @return array{text?: string, image?: string|list<string>, video?: array<string, string>|list<array<string, string>>}
     */
    public static function schemaFields(string $html): array
    {
        $fields = [];

        if (($text = self::text($html)) !== '') {
            $fields['text'] = $text;
        }

        $images = self::images($html);

        if ($images !== []) {
            // A single image as a string, multiple as a list — both valid.
            $fields['image'] = count($images) === 1 ? $images[0] : $images;
        }

        $videos = self::videos($html);

        if ($videos !== []) {
            // schema.org `video` expects a VideoObject; a bare `<video src>`
            // gives us the contentUrl.
            $objects = array_map(fn (string $url) => [
                '@type'      => 'VideoObject',
                'contentUrl' => $url,
            ], $videos);

            $fields['video'] = count($objects) === 1 ? $objects[0] : $objects;
        }

        return $fields;
    }

    /**
     * Image URLs from `<img src="…">`, in document order, de-duplicated, with
     * HTML entities decoded.
     *
     * @return list<string>
     */
    public static function images(string $html): array
    {
        return self::srcUrls('img', $html);
    }

    /**
     * Video file URLs from `<video src="…">`, in document order, de-duplicated,
     * with HTML entities decoded.
     *
     * @return list<string>
     */
    public static function videos(string $html): array
    {
        return self::srcUrls('video', $html);
    }

    /**
     * @return list<string>
     */
    private static function srcUrls(string $tag, string $html): array
    {
        if (!str_contains($html, '<'.$tag)) {
            return [];
        }

        preg_match_all('/<'.$tag.'\b[^>]*?\bsrc=("|\')(.*?)\1/i', $html, $matches);

        $urls = [];

        foreach ($matches[2] as $url) {
            $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5);

            if ($url !== '' && !in_array($url, $urls, true)) {
                $urls[] = $url;
            }
        }

        return $urls;
    }
}
