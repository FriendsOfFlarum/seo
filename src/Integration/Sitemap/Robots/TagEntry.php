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

use Flarum\Http\SlugManager;
use Flarum\Tags\Tag;
use FoF\Seo\Indexability\IndexabilityPolicy;
use FoF\Sitemap\Robots\Entries\TagEntry as SitemapTagEntry;

/**
 * fof/sitemap's tag Disallows (`/t/`, `/tags`), unchanged, plus an `Allow`
 * for each noindexed tag page guests can open, so crawlers may fetch exactly
 * those pages and read their noindex.
 *
 * Each tag gets two rules, `Allow: /t/<slug>$` (the page itself) and
 * `Allow: /t/<slug>?` (the page with a query string). Google and RFC 9309
 * use the longest matching rule, so both beat `Disallow: /t/`; the `$` and
 * `?` keep `/t/<slug>` from also opening `/t/<slug>-other`.
 */
class TagEntry extends SitemapTagEntry
{
    public function __construct(
        protected readonly IndexabilityPolicy $indexability,
        protected readonly SlugManager $slugManager,
    ) {
    }

    public function getRules(): array
    {
        $rules = parent::getRules();

        // fof/sitemap blocks nothing (exclude tags off): nothing to allow.
        if (empty($rules)) {
            return $rules;
        }

        $slugger = $this->slugManager->forResource(Tag::class);

        foreach ($this->indexability->noindexedTagsGuestsCanOpen() as $tag) {
            // RFC 9309 compares percent-encoded paths, and `*`, `$` and `?`
            // are robots.txt syntax: encode the slug so it matches literally.
            $url = $this->generateRouteUrl('tag', ['slug' => rawurlencode($slugger->toSlug($tag))]);
            $path = parse_url($url, PHP_URL_PATH);

            if (!is_string($path) || $path === '') {
                continue;
            }

            $rules[] = $this->allowForAll($path.'$');
            $rules[] = $this->allowForAll($path.'?');
        }

        return $rules;
    }
}
