<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Tests\integration;

/**
 * Decides whether a crawler may fetch a path under a robots.txt's
 * `User-agent: *` group, the way Google documents it
 * (https://developers.google.com/search/docs/crawling-indexing/robots/robots_txt):
 * rules match from the start of the path (including the query string), `*`
 * matches any characters, a trailing `$` anchors the end, the longest
 * matching rule wins, and on a tie the less restrictive `Allow` wins.
 */
trait MatchesRobotsRules
{
    protected function crawlable(string $robots, string $path): bool
    {
        $best = null;
        $inStarGroup = false;

        foreach (preg_split('/\R/', $robots) as $line) {
            if (!preg_match('/^\s*([A-Za-z-]+)\s*:\s*(.*?)\s*$/', $line, $m)) {
                continue;
            }

            $field = strtolower($m[1]);

            if ($field === 'user-agent') {
                $inStarGroup = $m[2] === '*';

                continue;
            }

            if (!$inStarGroup || !in_array($field, ['allow', 'disallow'], true) || $m[2] === '') {
                continue;
            }

            $pattern = $m[2];
            $anchored = str_ends_with($pattern, '$');
            $regex = '#^'.str_replace('\*', '.*', preg_quote($anchored ? substr($pattern, 0, -1) : $pattern, '#')).($anchored ? '$' : '').'#';

            if (!preg_match($regex, $path)) {
                continue;
            }

            $allow = $field === 'allow';
            $length = strlen($pattern);

            if ($best === null || $length > $best[0] || ($length === $best[0] && $allow)) {
                $best = [$length, $allow];
            }
        }

        return $best === null || $best[1];
    }
}
