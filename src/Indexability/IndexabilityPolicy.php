<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Indexability;

use Flarum\Extension\ExtensionManager;
use Flarum\Settings\SettingsRepositoryInterface;
use FoF\Seo\SeoMeta\SeoMeta;
use FoF\Seo\TagIndexingPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * fof/seo's single answer to "may search engines index this page?".
 *
 * The page drivers ask it when they render the robots meta tag, and the
 * fof/sitemap integration turns the same rules into SQL constraints, so a
 * page that renders `noindex` is never submitted in the sitemap:
 *
 * - a discussion is noindexed when any of its tags is selected in
 *   `seo_noindex_tags` or is a direct child of one, or when its own SEO meta
 *   has `robots_noindex`;
 * - a tag is noindexed when it is selected, is a direct child of a selected
 *   tag, or its own SEO meta has `robots_noindex` (a tag's own flag does not
 *   pass down to its discussions);
 * - a fof/pages Page is noindexed when its own SEO meta has `robots_noindex`;
 * - every profile is noindexed while `seo_noindex_profiles` is on.
 *
 * The SQL constraints only add `NOT EXISTS`/`NOT IN` conditions to the query
 * they're given; they never read or create SEO meta rows per item.
 */
class IndexabilityPolicy
{
    public function __construct(
        protected readonly TagIndexingPolicy $tags,
        protected readonly SettingsRepositoryInterface $settings,
        protected readonly ExtensionManager $extensions,
    ) {
    }

    /**
     * Whether a page belonging to these tags (a discussion's tags, or a tag
     * page's own tag) is kept out of the index by the tag setting.
     *
     * @param iterable<\Flarum\Tags\Tag> $tags Tags with `parent_id` loaded.
     */
    public function tagsNoindexed(iterable $tags): bool
    {
        return $this->tags->shouldNoindex($tags);
    }

    /**
     * Whether user profile pages are kept out of the index.
     */
    public function profilesNoindexed(): bool
    {
        return (bool) $this->settings->get('seo_noindex_profiles');
    }

    /**
     * Restrict a discussions query to the discussions that may be indexed.
     */
    public function constrainDiscussions(Builder $query): void
    {
        if ($this->extensions->isEnabled('flarum-tags')) {
            $tagIds = $this->tags->excludedTagIdsWithChildren();

            if (!empty($tagIds)) {
                $discussionKey = $query->getModel()->getQualifiedKeyName();

                // NOT EXISTS rather than NOT IN (subquery): every driver plans
                // it as an anti-join probing the discussion_tag primary key.
                $query->whereNotExists(function (QueryBuilder $pivot) use ($discussionKey, $tagIds) {
                    $pivot->selectRaw('1')
                        ->from('discussion_tag')
                        ->whereColumn('discussion_tag.discussion_id', $discussionKey)
                        ->whereIn('discussion_tag.tag_id', $tagIds);
                });
            }
        }

        $this->excludeItemNoindex($query);
    }

    /**
     * Restrict a tags query to the tags whose pages may be indexed.
     */
    public function constrainTags(Builder $query): void
    {
        $tagIds = $this->tags->excludedTagIdsWithChildren();

        if (!empty($tagIds)) {
            $query->whereNotIn($query->getModel()->getQualifiedKeyName(), $tagIds);
        }

        $this->excludeItemNoindex($query);
    }

    /**
     * Restrict a fof/pages query to the Pages that may be indexed.
     */
    public function constrainPages(Builder $query): void
    {
        $this->excludeItemNoindex($query);
    }

    /**
     * Restrict a users query to the profiles that may be indexed.
     */
    public function constrainUsers(Builder $query): void
    {
        if ($this->profilesNoindexed()) {
            $query->whereRaw('1 = 0');
        }
    }

    /**
     * Leave out the rows whose own SEO meta has `robots_noindex`. SEO meta is
     * keyed by the model's table name and ID (see SeoMeta::findOneByModel()),
     * and the unique (object_id, object_type) index serves the probe.
     */
    protected function excludeItemNoindex(Builder $query): void
    {
        $model = $query->getModel();
        $seoMeta = (new SeoMeta())->getTable();

        $query->whereNotExists(function (QueryBuilder $meta) use ($model, $seoMeta) {
            $meta->selectRaw('1')
                ->from($seoMeta)
                ->whereColumn("$seoMeta.object_id", $model->getQualifiedKeyName())
                ->where("$seoMeta.object_type", $model->getTable())
                ->where("$seoMeta.robots_noindex", true);
        });
    }
}
