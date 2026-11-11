<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Tests\unit\Indexability;

use Flarum\Extension\ExtensionManager;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\unit\TestCase;
use FoF\Seo\Indexability\IndexabilityPolicy;
use FoF\Seo\TagIndexingPolicy;
use Illuminate\Database\Eloquent\Builder;
use Mockery as m;

class IndexabilityPolicyTest extends TestCase
{
    public function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    private function policy(array $settings): IndexabilityPolicy
    {
        $repository = m::mock(SettingsRepositoryInterface::class);

        foreach ($settings as $key => $value) {
            $repository->shouldReceive('get')->with($key)->andReturn($value);
        }

        return new IndexabilityPolicy(new TagIndexingPolicy($repository), $repository, m::mock(ExtensionManager::class));
    }

    public function test_profiles_are_indexable_unless_the_setting_is_on(): void
    {
        $this->assertFalse($this->policy(['seo_noindex_profiles' => null])->profilesNoindexed());
        $this->assertFalse($this->policy(['seo_noindex_profiles' => '0'])->profilesNoindexed());
        $this->assertTrue($this->policy(['seo_noindex_profiles' => '1'])->profilesNoindexed());
    }

    public function test_tag_decision_uses_the_tag_indexing_rules(): void
    {
        $policy = $this->policy(['seo_noindex_tags' => json_encode([1])]);

        $this->assertTrue($policy->tagsNoindexed([(object) ['id' => 1, 'parent_id' => null, 'parent' => null]]));
        $this->assertTrue($policy->tagsNoindexed([(object) ['id' => 2, 'parent_id' => 1, 'parent' => null]]));
        $this->assertFalse($policy->tagsNoindexed([(object) ['id' => 3, 'parent_id' => null, 'parent' => null]]));
    }

    public function test_users_query_is_emptied_only_while_profiles_are_noindexed(): void
    {
        $on = m::mock(Builder::class);
        $on->shouldReceive('whereRaw')->once()->with('1 = 0');
        $this->policy(['seo_noindex_profiles' => '1'])->constrainUsers($on);

        $off = m::mock(Builder::class);
        $off->shouldNotReceive('whereRaw');
        $this->policy(['seo_noindex_profiles' => '0'])->constrainUsers($off);

        $this->addToAssertionCount(2);
    }
}
