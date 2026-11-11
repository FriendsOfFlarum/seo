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
use Illuminate\Support\Collection;

/**
 * fof/sitemap query modifier: leaves out the tags fof/seo marks noindex.
 */
class ConstrainTags
{
    public function __construct(
        protected readonly IndexabilityPolicy $policy,
    ) {
    }

    /**
     * fof/sitemap lets an earlier modifier replace the query with a
     * Collection; that can't be constrained in SQL, so it is left as is.
     *
     * @param Builder<\Illuminate\Database\Eloquent\Model>|Collection<array-key, mixed> $query
     */
    public function __invoke(Builder|Collection $query): void
    {
        if ($query instanceof Builder) {
            $this->policy->constrainTags($query);
        }
    }
}
