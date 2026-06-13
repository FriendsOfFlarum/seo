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

use FoF\Seo\Breadcrumb\BreadcrumbTrail;
use FoF\Seo\Breadcrumb\Crumb;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BreadcrumbTrailTest extends TestCase
{
    private function trail(Crumb ...$crumbs): BreadcrumbTrail
    {
        $trail = new BreadcrumbTrail();

        foreach ($crumbs as $crumb) {
            $trail->push($crumb);
        }

        return $trail;
    }

    #[Test]
    public function a_trail_with_fewer_than_two_crumbs_renders_to_null(): void
    {
        $this->assertNull((new BreadcrumbTrail())->toSchema());
        $this->assertNull($this->trail(new Crumb('Home', 'https://f.tld/'))->toSchema());
    }

    #[Test]
    public function it_renders_a_breadcrumb_list_with_sequential_positions(): void
    {
        $schema = $this->trail(
            new Crumb('Home', 'https://f.tld/'),
            new Crumb('Support', 'https://f.tld/t/support'),
            new Crumb('How do I?')
        )->toSchema();

        $this->assertSame('https://schema.org', $schema['@context']);
        $this->assertSame('BreadcrumbList', $schema['@type']);

        $items = $schema['itemListElement'];
        $this->assertCount(3, $items);
        $this->assertSame([1, 2, 3], array_column($items, 'position'));
        $this->assertSame(['Home', 'Support', 'How do I?'], array_column($items, 'name'));
    }

    #[Test]
    public function the_last_crumb_omits_item_so_google_uses_the_page_url(): void
    {
        $items = $this->trail(
            new Crumb('Home', 'https://f.tld/'),
            new Crumb('Current page', 'https://f.tld/current')
        )->toSchema()['itemListElement'];

        // First crumb carries a full item...
        $this->assertArrayHasKey('item', $items[0]);
        $this->assertSame('https://f.tld/', $items[0]['item']['@id']);
        $this->assertSame('https://f.tld/', $items[0]['item']['url']);

        // ...the last omits it even though a url was provided.
        $this->assertArrayNotHasKey('item', $items[1]);
        $this->assertSame('Current page', $items[1]['name']);
    }

    #[Test]
    public function a_non_last_crumb_without_a_url_also_omits_item(): void
    {
        $items = $this->trail(
            new Crumb('Home', 'https://f.tld/'),
            new Crumb('Unlinked section'),       // no url, not last
            new Crumb('Leaf', 'https://f.tld/x')
        )->toSchema()['itemListElement'];

        $this->assertArrayNotHasKey('item', $items[1]);
        $this->assertSame('Unlinked section', $items[1]['name']);
    }

    #[Test]
    public function it_does_not_emit_the_legacy_unordered_order_or_count_fields(): void
    {
        $schema = $this->trail(
            new Crumb('Home', 'https://f.tld/'),
            new Crumb('Leaf', 'https://f.tld/x')
        )->toSchema();

        $this->assertArrayNotHasKey('itemListOrder', $schema);
        $this->assertArrayNotHasKey('numberOfItems', $schema);
    }

    #[Test]
    public function item_type_defaults_to_webpage_and_is_overridable(): void
    {
        $items = $this->trail(
            new Crumb('Home', 'https://f.tld/'),
            new Crumb('Cat', 'https://f.tld/c', 'CollectionPage'),
            new Crumb('Leaf')
        )->toSchema()['itemListElement'];

        $this->assertSame('WebPage', $items[0]['item']['@type']);
        $this->assertSame('CollectionPage', $items[1]['item']['@type']);
    }

    #[Test]
    public function extra_properties_are_merged_into_the_item(): void
    {
        $items = $this->trail(
            new Crumb('Home', 'https://f.tld/', 'WebPage', ['image' => 'https://f.tld/logo.png']),
            new Crumb('Leaf', 'https://f.tld/x')
        )->toSchema()['itemListElement'];

        $this->assertSame('https://f.tld/logo.png', $items[0]['item']['image']);
    }

    #[Test]
    public function prepend_adds_to_the_front(): void
    {
        $names = array_column(
            $this->trail(new Crumb('Leaf', 'https://f.tld/x'), new Crumb('End'))
                ->prepend(new Crumb('Home', 'https://f.tld/'))
                ->toSchema()['itemListElement'],
            'name'
        );

        $this->assertSame(['Home', 'Leaf', 'End'], $names);
    }

    #[Test]
    public function insert_after_places_a_crumb_after_the_match(): void
    {
        $names = array_column(
            $this->trail(
                new Crumb('Home', 'https://f.tld/'),
                new Crumb('Leaf', 'https://f.tld/x'),
                new Crumb('End')
            )
                ->insertAfter(fn (Crumb $c) => $c->name === 'Home', new Crumb('Mid', 'https://f.tld/m'))
                ->toSchema()['itemListElement'],
            'name'
        );

        $this->assertSame(['Home', 'Mid', 'Leaf', 'End'], $names);
    }

    #[Test]
    public function insert_after_appends_when_no_match(): void
    {
        $trail = $this->trail(new Crumb('Home', 'https://f.tld/'), new Crumb('Leaf'))
            ->insertAfter(fn (Crumb $c) => $c->name === 'Nope', new Crumb('Added', 'https://f.tld/a'));

        $this->assertSame(['Home', 'Leaf', 'Added'], array_map(fn (Crumb $c) => $c->name, $trail->all()));
    }

    #[Test]
    public function remove_drops_matching_crumbs(): void
    {
        $names = array_column(
            $this->trail(
                new Crumb('Home', 'https://f.tld/'),
                new Crumb('Q&A', 'https://f.tld/t/qa'),
                new Crumb('Leaf')
            )
                ->remove(fn (Crumb $c) => $c->name === 'Q&A')
                ->toSchema()['itemListElement'],
            'name'
        );

        $this->assertSame(['Home', 'Leaf'], $names);
    }

    #[Test]
    public function map_transforms_crumbs(): void
    {
        $items = $this->trail(
            new Crumb('home', 'https://f.tld/'),
            new Crumb('leaf', 'https://f.tld/x')
        )
            ->map(fn (Crumb $c) => new Crumb(ucfirst($c->name), $c->url))
            ->toSchema()['itemListElement'];

        $this->assertSame('Home', $items[0]['name']);
    }

    #[Test]
    public function count_and_is_empty_reflect_the_trail(): void
    {
        $trail = new BreadcrumbTrail();
        $this->assertTrue($trail->isEmpty());
        $this->assertSame(0, $trail->count());

        $trail->push(new Crumb('Home', 'https://f.tld/'));
        $this->assertFalse($trail->isEmpty());
        $this->assertSame(1, $trail->count());
    }
}
