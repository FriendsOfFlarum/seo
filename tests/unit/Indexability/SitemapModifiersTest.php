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

use Flarum\Testing\unit\TestCase;
use FoF\Seo\Indexability\IndexabilityPolicy;
use FoF\Seo\Integration\Sitemap\ConstrainDiscussions;
use FoF\Seo\Integration\Sitemap\ConstrainPages;
use FoF\Seo\Integration\Sitemap\ConstrainTags;
use FoF\Seo\Integration\Sitemap\ConstrainUsers;
use Mockery as m;

class SitemapModifiersTest extends TestCase
{
    public function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    /**
     * fof/sitemap lets an earlier modifier swap the query for a Collection;
     * fof/seo's modifiers leave it alone instead of failing the whole build.
     */
    public function test_a_collection_from_an_earlier_modifier_is_left_alone(): void
    {
        $policy = m::mock(IndexabilityPolicy::class);
        $policy->shouldNotReceive('constrainDiscussions', 'constrainTags', 'constrainPages', 'constrainUsers');

        foreach ([ConstrainDiscussions::class, ConstrainTags::class, ConstrainPages::class, ConstrainUsers::class] as $modifier) {
            $this->assertNull((new $modifier($policy))(collect(['index'])));
        }
    }
}
