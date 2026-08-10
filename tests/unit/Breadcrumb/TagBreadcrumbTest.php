<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Tests\unit\Breadcrumb;

use Flarum\Http\RouteCollectionUrlGenerator;
use Flarum\Http\UrlGenerator;
use Flarum\Tags\Tag;
use FoF\Seo\Breadcrumb\BreadcrumbTrail;
use FoF\Seo\Breadcrumb\Crumb;
use FoF\Seo\Breadcrumb\TagBreadcrumb;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TagBreadcrumbTest extends TestCase
{
    /**
     * @param list<Tag> $allTags the full tag set the ancestor walk resolves
     *                           against (mirrors Tag::all() in production)
     */
    private function tagBreadcrumb(array $allTags = []): TagBreadcrumb
    {
        // A UrlGenerator whose route() just echoes a predictable path; the
        // lineage logic under test cares about which tags/order, not the URLs.
        $routes = $this->createStub(RouteCollectionUrlGenerator::class);
        $routes->method('route')->willReturnCallback(
            fn (string $routeName, array $params = []) => 'https://f.tld/'.$routeName.(isset($params['slug']) ? '/'.$params['slug'] : '')
        );

        $url = $this->createStub(UrlGenerator::class);
        $url->method('to')->willReturn($routes);

        // Override the DB-backed Tag::all() so the lineage walk resolves
        // ancestors from the supplied set, no database needed.
        return new class($url, new Collection($allTags)) extends TagBreadcrumb {
            public function __construct(UrlGenerator $url, private readonly Collection $allTags)
            {
                parent::__construct($url);
            }

            protected function loadAllTags(): Collection
            {
                return $this->allTags;
            }
        };
    }

    /**
     * Build a Tag without touching the database. `parent` is wired as a loaded
     * relation so lineage() walks it without a query.
     *
     * A tag is "primary" (part of the navigable hierarchy) when it has a
     * `position` and no parent — mirroring core (flarum/tags Content/Tags.php).
     * The legacy `is_primary` column is deliberately decoupled here (defaults to
     * the inverse-ish reality on old forums) so the tests prove we key off
     * `position`, not `is_primary`.
     */
    private function tag(int $id, string $name, ?int $position = null, ?Tag $parent = null, ?bool $isPrimary = null): Tag
    {
        $tag = new Tag();
        $tag->id = $id;
        $tag->name = $name;
        $tag->slug = strtolower($name);
        $tag->position = $position;
        $tag->parent_id = $parent?->id;
        $tag->setRelation('parent', $parent);
        // is_primary is intentionally NOT what we filter on; default it to the
        // opposite of reality to catch any accidental reliance on it.
        $tag->is_primary = $isPrimary ?? false;

        return $tag;
    }

    /** A top-level primary tag (has a position, no parent). */
    private function primary(int $id, string $name, int $position = 0): Tag
    {
        return $this->tag($id, $name, $position, null);
    }

    /** A child tag (has a parent); position within the parent is irrelevant here. */
    private function child(int $id, string $name, Tag $parent): Tag
    {
        return $this->tag($id, $name, 0, $parent);
    }

    /** A secondary tag (no position, no parent). */
    private function secondary(int $id, string $name): Tag
    {
        return $this->tag($id, $name, null, null);
    }

    /**
     * @param list<Crumb> $lineage
     *
     * @return list<string>
     */
    private function names(array $lineage): array
    {
        return array_map(fn (Crumb $c) => $c->name, $lineage);
    }

    #[Test]
    public function no_primary_tags_yields_no_lineages(): void
    {
        $tags = [
            $this->secondary(1, 'Announcements'),
            $this->secondary(2, 'Off-topic'),
        ];

        $this->assertSame([], $this->tagBreadcrumb($tags)->primaryLineages(new Collection($tags)));
    }

    #[Test]
    public function a_single_primary_tag_yields_one_lineage(): void
    {
        $tags = [$this->primary(1, 'Support')];

        $lineages = $this->tagBreadcrumb($tags)->primaryLineages(new Collection($tags));

        $this->assertCount(1, $lineages);
        // A lineage is the pure tag chain — no "Tags" root (that belongs to the
        // tag-page trail, not discussion trails).
        $this->assertSame(['Support'], $this->names($lineages[0]));
    }

    #[Test]
    public function it_uses_position_not_the_is_primary_column(): void
    {
        // Regression for discuss.flarum.org: top-level tags there have a
        // `position` but a drifted `is_primary = 0`. They must still anchor the
        // breadcrumb, so we key off position, never is_primary.
        $extensions = $this->tag(48, 'Extensions', position: 2, parent: null, isPrimary: false);

        $lineages = $this->tagBreadcrumb([$extensions])->primaryLineages(new Collection([$extensions]));

        $this->assertCount(1, $lineages);
        $this->assertSame(['Extensions'], $this->names($lineages[0]));
    }

    #[Test]
    public function a_positionless_tag_is_secondary_even_if_is_primary_is_true(): void
    {
        // The inverse drift: is_primary=1 but no position. Core treats this as
        // secondary (no position ⇒ not in the primary nav), so we must too.
        $weird = $this->tag(1, 'Legacy', position: null, parent: null, isPrimary: true);

        $this->assertSame([], $this->tagBreadcrumb([$weird])->primaryLineages(new Collection([$weird])));
    }

    #[Test]
    public function a_primary_parent_and_child_fold_into_one_lineage(): void
    {
        $support = $this->primary(1, 'Support');
        $install = $this->child(2, 'Installation', $support);
        $tags = [$support, $install];

        // Both attached; order shouldn't matter.
        $lineages = $this->tagBreadcrumb($tags)->primaryLineages(new Collection($tags));

        $this->assertCount(1, $lineages);
        $this->assertSame(['Support', 'Installation'], $this->names($lineages[0]));
    }

    #[Test]
    public function a_child_attached_without_its_parent_still_lists_ancestors(): void
    {
        $support = $this->primary(1, 'Support');
        $install = $this->child(2, 'Installation', $support);

        // Only the child is attached; its ancestor (present in the full tag
        // set) must still appear.
        $lineages = $this->tagBreadcrumb([$support, $install])->primaryLineages(new Collection([$install]));

        $this->assertCount(1, $lineages);
        $this->assertSame(['Support', 'Installation'], $this->names($lineages[0]));
    }

    /**
     * The ancestor walk must resolve parents from the in-memory tag map, never
     * by touching `$tag->parent`. A per-level lazy load there is an N+1 that
     * grows with chain depth.
     *
     * This is asserted here rather than by counting queries on a rendered page:
     * core lazy-loads `parent` per ancestor level itself, and by an amount that
     * varies by database driver, so a page-level query count cannot separate our
     * cost from core's. Withholding the relation makes the invariant exact.
     */
    #[Test]
    public function the_ancestor_walk_never_lazy_loads_a_parent(): void
    {
        $chain = [];
        $parentId = null;

        // A 9-level chain whose `parent` relation is deliberately NOT loaded, on
        // models that fail loudly if the relation is touched at all.
        for ($level = 1; $level <= 9; $level++) {
            $tag = new class() extends Tag {
                public function parent(): BelongsTo
                {
                    throw new LogicException('lineage() lazy-loaded a parent; it must resolve ancestors against the in-memory tag map.');
                }
            };

            $tag->id = $level;
            $tag->name = "L{$level}";
            $tag->slug = "l{$level}";
            $tag->position = 0;
            $tag->parent_id = $parentId;
            $tag->is_primary = false;

            $chain[] = $tag;
            $parentId = $level;
        }

        $leaf = $chain[count($chain) - 1];

        $lineages = $this->tagBreadcrumb($chain)->primaryLineages(new Collection([$leaf]));

        $this->assertCount(1, $lineages);
        $this->assertSame(['L1', 'L2', 'L3', 'L4', 'L5', 'L6', 'L7', 'L8', 'L9'], $this->names($lineages[0]));
    }

    #[Test]
    public function two_unrelated_primary_tags_yield_two_lineages(): void
    {
        $tags = [
            $this->primary(1, 'Support', 0),
            $this->primary(2, 'Bugs', 1),
        ];

        $lineages = $this->tagBreadcrumb($tags)->primaryLineages(new Collection($tags));

        $this->assertCount(2, $lineages);
        $this->assertSame(['Support'], $this->names($lineages[0]));
        $this->assertSame(['Bugs'], $this->names($lineages[1]));
    }

    #[Test]
    public function secondary_tags_are_excluded_when_mixed_with_a_primary(): void
    {
        $tags = [
            $this->secondary(1, 'Chatter'),
            $this->primary(2, 'Support'),
        ];

        $lineages = $this->tagBreadcrumb($tags)->primaryLineages(new Collection($tags));

        $this->assertCount(1, $lineages);
        $this->assertSame(['Support'], $this->names($lineages[0]));
    }

    #[Test]
    public function tag_page_ancestor_trail_keeps_the_tags_root(): void
    {
        $support = $this->primary(1, 'Support');
        $install = $this->child(2, 'Installation', $support);

        $trail = new BreadcrumbTrail();
        $this->tagBreadcrumb([$support, $install])->pushAncestorsOf($trail, $install);

        // Tag pages still root at the /tags listing, then the ancestors (the tag
        // itself is added by the caller as the current crumb).
        $this->assertSame(['Tags', 'Support'], $this->names($trail->all()));
    }
}
