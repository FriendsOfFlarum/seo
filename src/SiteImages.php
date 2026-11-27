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

use Flarum\Forum\Branding\Branding;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Contracts\Filesystem\Factory;

/**
 * The forum-wide images fof/seo puts in page metadata. Core's logo and app icon
 * come from Branding, which knows about images set with the Theme extender and
 * the assets disk's URL; settings such as `logo_path` don't.
 */
class SiteImages
{
    protected Cloud $assets;

    public function __construct(
        protected Branding $branding,
        protected SettingsRepositoryInterface $settings,
        Factory $filesystemFactory
    ) {
        $this->assets = $filesystemFactory->disk('flarum-assets');
    }

    public function socialImageUrl(): ?string
    {
        $path = $this->settings->get('seo_social_media_image_path');

        return $path ? $this->assets->url($path) : null;
    }

    /**
     * The 512px square app icon: unlike the logo and favicon, its size is known.
     */
    public function appIconUrl(): ?string
    {
        return $this->branding->appIconUrls()['icon-512.png'] ?? null;
    }

    public function logoUrl(): ?string
    {
        return $this->branding->logoUrl();
    }
}
