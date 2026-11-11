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
use FoF\Sitemap\Robots\Entries\TagEntry as SitemapTagEntry;

/**
 * fof/sitemap's tag Disallows (`/t/`, `/tags`), dropped while fof/seo
 * noindexes any tag page: crawlers can only read a noindex on a page they
 * may fetch. Otherwise it behaves exactly like fof/sitemap's own entry.
 */
class TagEntry extends SitemapTagEntry
{
    public function __construct(
        protected readonly IndexabilityPolicy $indexability,
    ) {
    }

    public function getRules(): array
    {
        // Only ask the policy (which may query) when fof/sitemap would block.
        if (!$this->enabled() || $this->indexability->anyTagNoindexed()) {
            return [];
        }

        return parent::getRules();
    }
}
