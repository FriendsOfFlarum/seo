<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Tests\unit;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\unit\TestCase;
use FoF\Seo\TagIndexingPolicy;
use Mockery as m;

class TagIndexingPolicyTest extends TestCase
{
    public function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    private function policy(mixed $settingValue): TagIndexingPolicy
    {
        $settings = m::mock(SettingsRepositoryInterface::class);
        $settings->shouldReceive('get')->with('seo_noindex_tags')->andReturn($settingValue);

        return new TagIndexingPolicy($settings);
    }

    private function tag(int $id, ?int $parentId = null, ?object $parent = null): object
    {
        return (object) ['id' => $id, 'parent_id' => $parentId, 'parent' => $parent];
    }

    public function test_excludedTagIds_is_empty_when_unset(): void
    {
        $this->assertSame([], $this->policy(null)->excludedTagIds());
        $this->assertSame([], $this->policy('')->excludedTagIds());
    }

    public function test_excludedTagIds_is_empty_for_invalid_json(): void
    {
        $this->assertSame([], $this->policy('not json')->excludedTagIds());
    }

    public function test_excludedTagIds_is_empty_when_json_is_not_an_array(): void
    {
        $this->assertSame([], $this->policy('5')->excludedTagIds());
    }

    public function test_excludedTagIds_parses_and_normalises_ids(): void
    {
        // Strings are cast to int and falsy/zero values dropped.
        $this->assertSame([1, 2, 3], $this->policy(json_encode([1, 2, 3]))->excludedTagIds());
        $this->assertSame([1, 2], $this->policy(json_encode(['1', '2']))->excludedTagIds());
        $this->assertSame([4], $this->policy(json_encode([0, 4]))->excludedTagIds());
    }

    public function test_shouldNoindex_is_false_when_nothing_excluded(): void
    {
        $this->assertFalse($this->policy(null)->shouldNoindex([$this->tag(1)]));
    }

    public function test_shouldNoindex_matches_a_tag_id_directly(): void
    {
        $policy = $this->policy(json_encode([3]));

        $this->assertTrue($policy->shouldNoindex([$this->tag(3)]));
        $this->assertFalse($policy->shouldNoindex([$this->tag(9)]));
    }

    public function test_shouldNoindex_matches_via_parent_id_column(): void
    {
        $policy = $this->policy(json_encode([1]));

        // Child tag with parent_id = 1 (excluded).
        $this->assertTrue($policy->shouldNoindex([$this->tag(2, 1)]));
    }

    public function test_shouldNoindex_matches_via_loaded_parent_relation(): void
    {
        $policy = $this->policy(json_encode([1]));

        // parent_id null, but the loaded parent relation has the excluded id.
        $this->assertTrue($policy->shouldNoindex([$this->tag(2, null, (object) ['id' => 1])]));
    }

    public function test_shouldNoindex_is_false_when_no_tag_or_parent_matches(): void
    {
        $policy = $this->policy(json_encode([99]));

        $this->assertFalse($policy->shouldNoindex([$this->tag(2, 1)]));
    }
}
