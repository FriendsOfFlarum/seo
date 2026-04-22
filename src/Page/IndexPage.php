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
        $properties->setTitle($this->settings->get('forum_title'));
        $properties->setUrl('');
        $properties->setCanonicalUrl('');

        // Update meta tag URL when it's the discussion overview page
        if ($routeName === 'default' && $this->settings->get('default_route') !== '/all') {
            $properties->setUrl('/all');
            $properties->setCanonicalUrl('/all');
        }
    }
}
