<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Breadcrumb;

/**
 * A single step in a breadcrumb trail.
 *
 * `url` is optional: a crumb with no url represents the current page (it should
 * be the last in the trail), for which Google uses the page's own URL.
 */
class Crumb
{
    /**
     * @param array<string, mixed> $extra extra properties merged into the
     *                                     schema.org `item` object (e.g. an
     *                                     explicit `@id` or `image`)
     */
    public function __construct(
        public string $name,
        public ?string $url = null,
        public string $type = 'WebPage',
        public array $extra = [],
    ) {
    }
}
