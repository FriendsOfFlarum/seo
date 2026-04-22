<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo;

use FoF\Seo\Page\PageDriverInterface;
use Illuminate\Support\Collection;

interface SeoExtenderManagerInterface
{
    public function addExtender(string $name, PageDriverInterface $extender): void;

    public function getExtenders(?string $routeName = null): array;

    public function getActiveExtenders(): Collection;
}
