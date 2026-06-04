<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Page;

use FoF\Seo\SeoProperties;
use Psr\Http\Message\ServerRequestInterface;

interface PageDriverInterface
{
    /**
     * A list of Flarum extension IDs for extensions that should be enabled.
     *
     * @return array<int, string>
     */
    public function extensionDependencies(): array;

    /**
     * A list of route names that will be handled.
     *
     * Empty array if handles for all routes
     *
     * @return array<int, string>
     */
    public function handleRoutes(): array;

    /**
     * Handle page SEO.
     */
    public function handle(ServerRequestInterface $request, SeoProperties $seo): void;
}
