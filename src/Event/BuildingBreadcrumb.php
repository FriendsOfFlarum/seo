<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Event;

use FoF\Seo\Breadcrumb\BreadcrumbTrail;
use FoF\Seo\Breadcrumb\Crumb;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Dispatched after all page drivers have built the breadcrumb trail, but before
 * it is rendered to schema.org JSON-LD.
 *
 * Listeners may add, remove, reorder or relabel crumbs — this is the supported
 * way for third-party extensions to shape the breadcrumb for any page:
 *
 * ```php
 * (new Extend\Event())->listen(BuildingBreadcrumb::class, function (BuildingBreadcrumb $event) {
 *     // Add a crumb…
 *     $event->trail->insertAfter(
 *         fn ($crumb) => $crumb->name === 'Home',
 *         new Crumb('Forums', 'https://example.com/forums')
 *     );
 *     // …or drop one…
 *     $event->trail->remove(fn ($crumb) => $crumb->name === 'Unwanted');
 * });
 * ```
 *
 * A trail with fewer than two crumbs renders to nothing, so removing crumbs can
 * suppress the breadcrumb entirely.
 *
 * @see BreadcrumbTrail
 * @see Crumb
 */
class BuildingBreadcrumb
{
    public function __construct(
        public readonly BreadcrumbTrail $trail,
        public readonly ServerRequestInterface $request,
    ) {
    }
}
