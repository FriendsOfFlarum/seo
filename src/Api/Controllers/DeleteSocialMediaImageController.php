<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Api\Controllers;

use Flarum\Api\Controller\AbstractDeleteController;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Filesystem\Cloud;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DeleteSocialMediaImageController extends AbstractDeleteController
{
    protected Cloud $disk;

    public function __construct(
        protected readonly SettingsRepositoryInterface $settings,
        Container $container,
    ) {
        $this->disk = $container->make('filesystem')->disk('flarum-assets');
    }

    protected function delete(ServerRequestInterface $request): ResponseInterface
    {
        $request->getAttribute('actor')->assertAdmin();

        $path = $this->settings->get('seo_social_media_image_path');
        $this->settings->set('seo_social_media_image_path', null);
        $this->settings->set('seo_social_media_image_url', null);

        if ($this->disk->exists($path)) {
            $this->disk->delete($path);
        }

        return new EmptyResponse(204);
    }
}
