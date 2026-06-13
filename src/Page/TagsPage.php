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
use FoF\Seo\Breadcrumb\Crumb;
use FoF\Seo\SeoProperties;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The all-tags listing page registered by flarum/tags at `/tags`.
 */
class TagsPage implements PageDriverInterface
{
    public function __construct(
        protected readonly TranslatorInterface $translator,
        protected readonly SettingsRepositoryInterface $settings,
    ) {
    }

    public function extensionDependencies(): array
    {
        return ['flarum-tags'];
    }

    public function handleRoutes(): array
    {
        return ['tags'];
    }

    public function handle(
        ServerRequestInterface $request,
        SeoProperties $properties
    ): void {
        $title = $this->translator->trans('flarum-tags.forum.all_tags.meta_title_text');

        $properties
            ->setSchemaJson('@type', 'CollectionPage')
            ->setSchemaJson('name', $title)
            ->setTitle($title)
            ->setUrl('/tags')
            ->setCanonicalUrl('/tags');

        // Breadcrumb: Home › Tags. "Tags" is the current page (no url).
        $properties->breadcrumb()->push(new Crumb($title));
    }
}
