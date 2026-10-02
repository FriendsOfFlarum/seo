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

use Flarum\Settings\SettingsRepositoryInterface;
use FoF\Seo\SeoProperties;
use Psr\Http\Message\ServerRequestInterface;

class IndexPage implements PageDriverInterface
{
    public function __construct(
        protected readonly SettingsRepositoryInterface $settings,
    ) {
    }

    public function extensionDependencies(): array
    {
        return [];
    }

    public function handleRoutes(): array
    {
        return ['default', 'index'];
    }

    public function handle(
        ServerRequestInterface $request,
        SeoProperties $properties
    ): void {
        $routeName = $request->getAttribute('routeName');

        $properties->setDescription($this->settings->get('forum_description'));
        $properties->setKeywords($this->settings->get('forum_keywords') ?? []);

        // The discussion list only lives at the root when it is the forum home;
        // otherwise it is at /all, and the root belongs to whichever page is home.
        $isSecondaryIndex = $routeName === 'index' && $this->settings->get('default_route') !== '/all';

        // Off the home page, keep core's "All Discussions" page title rather
        // than replacing it with the forum name (which then appears twice).
        $properties->setTitle($this->settings->get('forum_title'), !$isSecondaryIndex);
        $properties->setUrl('');
        $properties->setCanonicalUrl('');

        if ($isSecondaryIndex) {
            $properties->setUrl('/all');
            $properties->setCanonicalUrl('/all');
        }
    }
}
