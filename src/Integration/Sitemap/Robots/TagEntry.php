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
 * use the longest matching rule, so both beat `Disallow: /t/`; they also
 * come first, for crawlers that take the first match. The `$` and `?` keep
 * `/t/<slug>` from also opening `/t/<slug>-other`.
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
        $allows = [];

        foreach ($this->indexability->noindexedTagsGuestsCanOpen() as $tag) {
            $url = $this->generateRouteUrl('tag', ['slug' => static::encodeForRobots($slugger->toSlug($tag))]);
            $path = parse_url($url, PHP_URL_PATH);

            if (!is_string($path) || $path === '') {
                continue;
            }

            $allows[] = $this->allowForAll($path.'$');
            $allows[] = $this->allowForAll($path.'?');
        }

        // Allow lines first, for crawlers that take the first matching rule
        // rather than the longest.
        return array_merge($allows, $rules);
    }

    /**
     * Percent-encode what a crawler would not request literally, and nothing
     * else: non-ASCII bytes, control characters and whitespace (which also
     * keeps a slug from starting a new robots.txt line), `%`, the URL
     * delimiters `?` and `#`, and the robots.txt wildcards `*` and `$`
     * (RFC 9309 section 2.2.3 matches a literal `*` with `%2A`). Other ASCII,
     * such as `&` or `+`, stays as Flarum's links and crawlers write it.
     */
    protected static function encodeForRobots(string $slug): string
    {
        return (string) preg_replace_callback(
            '/[^\x21-\x7E]|[%*$?#]/',
            fn (array $match) => sprintf('%%%02X', ord($match[0])),
            $slug
        );
    }
}
