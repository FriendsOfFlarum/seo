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

use Flarum\Api\Controller\ShowForumController;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Tobscure\JsonApi\Document;

class UploadSocialMediaImageController extends ShowForumController
{
    protected Cloud $disk;

    public function __construct(
        protected readonly SettingsRepositoryInterface $settings,
        Container $container,
    ) {
        $this->disk = $container->make('filesystem')->disk('flarum-assets');
    }

    public function data(ServerRequestInterface $request, Document $document)
    {
        $request->getAttribute('actor')->assertAdmin();

        /** @var UploadedFileInterface $file */
        $file = Arr::get($request->getUploadedFiles(), 'seo_social_media_image');

        if (($path = $this->settings->get('seo_social_media_image_path')) && $this->disk->exists($path)) {
            $this->disk->delete($path);
        }

        $uploadName = 'site-image-'.Str::lower(Str::random(8)).'.png';

        $this->disk->put($uploadName, $file->getStream()->getContents());

        $this->settings->set('seo_social_media_image_path', $uploadName);
        $this->settings->set('seo_social_media_image_url', $this->disk->url($uploadName));

        return parent::data($request, $document);
    }
}
