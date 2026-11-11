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
use Psr\Log\LoggerInterface;

/**
 * fof/sitemap query modifier: leaves out the fof/pages Pages fof/seo marks noindex.
 */
class ConstrainPages
{
    public function __construct(
        protected readonly IndexabilityPolicy $policy,
        protected readonly LoggerInterface $logger,
    ) {
    }

    /**
     * fof/sitemap lets an earlier modifier replace the query with a
     * Collection; that can't be constrained in SQL, so it is left as is and
     * a warning is logged.
     *
     * @param Builder<\Illuminate\Database\Eloquent\Model>|Collection<array-key, mixed> $query
     */
    public function __invoke(Builder|Collection $query): void
    {
        if ($query instanceof Builder) {
            $this->policy->constrainPages($query);

            return;
        }

        $this->logger->warning('[FoF SEO] Another sitemap query modifier replaced the pages query with a collection, so fof/seo could not leave out noindexed pages.');
    }
}
