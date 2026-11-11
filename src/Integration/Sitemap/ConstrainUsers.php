<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Integration\Sitemap;

use FoF\Seo\Indexability\IndexabilityPolicy;
use Illuminate\Database\Eloquent\Builder;

/**
 * fof/sitemap query modifier: leaves out every profile while fof/seo noindexes profiles.
 */
class ConstrainUsers
{
    public function __construct(
        protected readonly IndexabilityPolicy $policy,
    ) {
    }

    public function __invoke(Builder $query): void
    {
        $this->policy->constrainUsers($query);
    }
}
