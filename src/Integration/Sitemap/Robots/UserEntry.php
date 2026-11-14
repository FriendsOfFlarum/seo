<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Integration\Sitemap\Robots;

use FoF\Seo\Indexability\IndexabilityPolicy;
use FoF\Sitemap\Robots\Entries\UserEntry as SitemapUserEntry;

/**
 * fof/sitemap's profile Disallow, dropped while fof/seo noindexes profiles:
 * crawlers can only read a noindex on a page they may fetch. Otherwise it
 * behaves exactly like fof/sitemap's own entry.
 */
class UserEntry extends SitemapUserEntry
{
    public function __construct(
        protected readonly IndexabilityPolicy $indexability,
    ) {
    }

    public function getRules(): array
    {
        if ($this->indexability->profilesNoindexed()) {
            return [];
        }

        return parent::getRules();
    }
}
