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

use FoF\Seo\Breadcrumb\Crumb;
use FoF\Seo\SeoProperties;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The members listing page registered by fof/user-directory at `/users`.
 */
class UserDirectoryPage implements PageDriverInterface
{
    public function __construct(
        protected readonly TranslatorInterface $translator,
    ) {
    }

    public function extensionDependencies(): array
    {
        return ['fof-user-directory'];
    }

    public function handleRoutes(): array
    {
        return ['fof_user_directory'];
    }

    public function handle(
        ServerRequestInterface $request,
        SeoProperties $properties
    ): void {
        $title = $this->translator->trans('fof-user-directory.forum.page.nav');

        $properties
            ->setSchemaJson('@type', 'CollectionPage')
            ->setSchemaJson('name', $title)
            ->setTitle($title)
            ->setUrl('/users')
            ->setCanonicalUrl('/users');

        // Breadcrumb: Home › User Directory. It is the current page (no url).
        $properties->breadcrumb()->push(new Crumb($title));
    }
}
