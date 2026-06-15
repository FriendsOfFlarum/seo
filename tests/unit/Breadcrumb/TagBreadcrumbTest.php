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
use FoF\Seo\Breadcrumb\Crumb;
use FoF\Seo\Breadcrumb\TagBreadcrumb;
use Illuminate\Support\Collection;
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
     */
    private function tag(int $id, string $name, bool $primary, ?Tag $parent = null): Tag
    {
        $tag = new Tag();
        $tag->id = $id;
        $tag->name = $name;
        $tag->slug = strtolower($name);
        $tag->is_primary = $primary;
        $tag->parent_id = $parent?->id;
        $tag->setRelation('parent', $parent);

        return $tag;
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
            $this->tag(1, 'Announcements', false),
            $this->tag(2, 'Off-topic', false),
        ];

        $this->assertSame([], $this->tagBreadcrumb($tags)->primaryLineages(new Collection($tags)));
    }

    #[Test]
    public function a_single_primary_tag_yields_one_lineage(): void
    {
        $tags = [$this->tag(1, 'Support', true)];

        $lineages = $this->tagBreadcrumb($tags)->primaryLineages(new Collection($tags));

        $this->assertCount(1, $lineages);
        $this->assertSame(['Tags', 'Support'], $this->names($lineages[0]));
    }

    #[Test]
    public function a_primary_parent_and_child_fold_into_one_lineage(): void
    {
        $support = $this->tag(1, 'Support', true);
        $install = $this->tag(2, 'Installation', true, $support);
        $tags = [$support, $install];

        // Both attached; order shouldn't matter.
        $lineages = $this->tagBreadcrumb($tags)->primaryLineages(new Collection($tags));

        $this->assertCount(1, $lineages);
        $this->assertSame(['Tags', 'Support', 'Installation'], $this->names($lineages[0]));
    }

    #[Test]
    public function a_child_attached_without_its_parent_still_lists_ancestors(): void
    {
        $support = $this->tag(1, 'Support', true);
        $install = $this->tag(2, 'Installation', true, $support);

        // Only the child is attached; its ancestor (present in the full tag
        // set) must still appear.
        $lineages = $this->tagBreadcrumb([$support, $install])->primaryLineages(new Collection([$install]));

        $this->assertCount(1, $lineages);
        $this->assertSame(['Tags', 'Support', 'Installation'], $this->names($lineages[0]));
    }

    #[Test]
    public function two_unrelated_primary_tags_yield_two_lineages(): void
    {
        $tags = [
            $this->tag(1, 'Support', true),
            $this->tag(2, 'Bugs', true),
        ];

        $lineages = $this->tagBreadcrumb($tags)->primaryLineages(new Collection($tags));

        $this->assertCount(2, $lineages);
        $this->assertSame(['Tags', 'Support'], $this->names($lineages[0]));
        $this->assertSame(['Tags', 'Bugs'], $this->names($lineages[1]));
    }

    #[Test]
    public function secondary_tags_are_excluded_when_mixed_with_a_primary(): void
    {
        $tags = [
            $this->tag(1, 'Chatter', false),
            $this->tag(2, 'Support', true),
        ];

        $lineages = $this->tagBreadcrumb($tags)->primaryLineages(new Collection($tags));

        $this->assertCount(1, $lineages);
        $this->assertSame(['Tags', 'Support'], $this->names($lineages[0]));
    }
}
