<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Tags\Tag;

/**
 * Decides whether a page should be kept out of the search index because it
 * belongs to a tag the admin has excluded (GH #117). Centralises the logic so
 * the discussion, best-answer and tag page drivers stay in agreement.
 */
class TagIndexingPolicy
{
    public function __construct(
        protected readonly SettingsRepositoryInterface $settings,
    ) {
    }

    /**
     * The tag IDs the admin has excluded from indexing.
     *
     * @return array<int, int>
     */
    public function excludedTagIds(): array
    {
        $raw = $this->settings->get('seo_noindex_tags');

        if (empty($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $decoded)));
    }

    /**
     * Whether any of the given tags (or their parent) is excluded. A child of
     * an excluded tag counts as excluded too — flarum-tags nests one level.
     *
     * @param iterable<Tag> $tags
     */
    public function shouldNoindex(iterable $tags): bool
    {
        $excluded = $this->excludedTagIds();

        if (empty($excluded)) {
            return false;
        }

        foreach ($tags as $tag) {
            if (in_array((int) $tag->id, $excluded, true)) {
                return true;
            }

            $parentId = $tag->parent_id ?? $tag->parent?->id;

            if ($parentId !== null && in_array((int) $parentId, $excluded, true)) {
                return true;
            }
        }

        return false;
    }
}
