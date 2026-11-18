<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Provider;

use Flarum\Foundation\AbstractServiceProvider;
use FoF\Seo\Page\DiscussionContext;

class DiscussionContextProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(DiscussionContext::class);
    }
}
