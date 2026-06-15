<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Breadcrumb;

use Flarum\Http\UrlGenerator;
use Flarum\Tags\Tag;
use FoF\Seo\SeoProperties;
use Illuminate\Support\Collection;

/**
 * Builds the tag-hierarchy portion of breadcrumb trails.
 *
 * Only *primary* tags form a breadcrumb path — secondary tags are flat labels,
 * not a category hierarchy, so they are ignored. Each distinct primary lineage
 * (a deepest primary tag plus its ancestors) yields one trail; a discussion in
 * two unrelated primary tags therefore produces two BreadcrumbLists.
 */
class TagBreadcrumb
{
    /**
     * All tags keyed by id, loaded once so the ancestor walk never lazy-loads
     * a parent per level. Tags are a small, bounded set.
     *
     * @var Collection<int, Tag>|null
     */
    private ?Collection $tagsById = null;

    public function __construct(
        private readonly UrlGenerator $url,
    ) {
    }

    /**
     * The distinct primary lineages among a discussion's tags. Each lineage is
     * a list of Crumbs beginning with the "Tags" root and continuing root →
     * leaf through the primary ancestry. Secondary tags are excluded.
     *
     * Returns an empty array when there are no primary tags (e.g. a discussion
     * tagged only with secondary tags), so the caller emits just Home › title.
     *
     * @param Collection<int, Tag> $tags
     *
     * @return list<list<Crumb>>
     */
    public function primaryLineages(Collection $tags): array
    {
        $primary = $tags->filter(fn (Tag $tag) => (bool) $tag->is_primary);

        if ($primary->isEmpty()) {
            return [];
        }

        $attachedIds = $primary->map(fn (Tag $tag) => $tag->id)->all();

        // A "leaf" is a primary tag that is not an ancestor of another attached
        // primary tag — i.e. the most specific tag on each branch. This folds a
        // primary parent + its attached primary child into a single lineage.
        $parentIds = $primary
            ->map(fn (Tag $tag) => $tag->parent_id)
            ->filter(fn (?int $id) => $id !== null && in_array($id, $attachedIds, true))
            ->all();

        $leaves = $primary->reject(fn (Tag $tag) => in_array($tag->id, $parentIds, true));

        $lineages = [];

        foreach ($leaves as $leaf) {
            $crumbs = [new Crumb('Tags', $this->url->to('forum')->route('tags'), 'CollectionPage')];

            foreach ($this->lineage($leaf) as $tag) {
                $crumbs[] = $this->crumbFor($tag);
            }

            $lineages[] = $crumbs;
        }

        return $lineages;
    }

    /**
     * Compose the breadcrumb trail(s) for a discussion: Home › {primary
     * lineage} › {title}, one trail per primary lineage. The first lineage
     * fills the pre-seeded primary trail; any further lineages are added as
     * extra trails. With no primary lineages the discussion gets a single
     * Home › title trail.
     *
     * @param Collection<int, Tag> $tags
     */
    public function emitDiscussionTrails(SeoProperties $properties, Collection $tags, string $title): void
    {
        $lineages = $this->primaryLineages($tags);

        // First (or only) trail uses the pre-seeded primary trail.
        $first = $properties->breadcrumb();

        foreach ($lineages[0] ?? [] as $crumb) {
            $first->push($crumb);
        }

        $first->push(new Crumb($title));

        // Additional primary lineages each become their own trail.
        foreach (array_slice($lineages, 1) as $lineage) {
            $trail = $properties->newBreadcrumb();

            foreach ($lineage as $crumb) {
                $trail->push($crumb);
            }

            $trail->push(new Crumb($title));

            $properties->addBreadcrumb($trail);
        }
    }

    /**
     * Push "Tags" then the lineage *down to but not including* the given tag —
     * used on a tag page, where the tag itself is the current (last) crumb. A
     * tag page is about that tag, so it is shown even for a secondary tag.
     */
    public function pushAncestorsOf(BreadcrumbTrail $trail, Tag $tag): void
    {
        $trail->push(new Crumb('Tags', $this->url->to('forum')->route('tags'), 'CollectionPage'));

        $lineage = $this->lineage($tag);
        array_pop($lineage); // drop the tag itself; the caller adds it as the leaf

        foreach ($lineage as $ancestor) {
            $trail->push($this->crumbFor($ancestor));
        }
    }

    public function crumbFor(Tag $tag): Crumb
    {
        return new Crumb(
            $tag->name,
            $this->url->to('forum')->route('tag', ['slug' => $tag->slug]),
            'CollectionPage',
        );
    }

    /**
     * The tag's ancestor chain, ordered root → … → tag. Walks `parent_id`
     * against an in-memory map of all tags so a deep chain costs no extra
     * queries (core only eager-loads one level of `parent`). Guards against
     * cycles.
     *
     * @return list<Tag>
     */
    private function lineage(Tag $tag): array
    {
        $byId = $this->allTagsById();

        $chain = [];
        $seen = [];
        $current = $tag;

        while ($current !== null && !isset($seen[$current->id])) {
            $seen[$current->id] = true;
            array_unshift($chain, $current);
            $current = $current->parent_id !== null ? $byId->get($current->parent_id) : null;
        }

        return $chain;
    }

    /**
     * @return Collection<int, Tag>
     */
    private function allTagsById(): Collection
    {
        return $this->tagsById ??= $this->loadAllTags()->keyBy('id');
    }

    /**
     * Load every tag once for the in-memory ancestor walk. Overridable so the
     * lineage logic can be unit-tested without a database.
     *
     * @return Collection<int, Tag>
     */
    protected function loadAllTags(): Collection
    {
        return Tag::all();
    }
}
