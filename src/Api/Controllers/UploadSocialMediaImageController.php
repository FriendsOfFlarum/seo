<?php

namespace FoF\Seo\Api\Controllers;

use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Tobscure\JsonApi\Document;
use Flarum\Api\Controller\ShowForumController;

class UploadSocialMediaImageController extends ShowForumController
{
    protected SettingsRepositoryInterface $settings;
    protected Cloud $disk;

    public function __construct(SettingsRepositoryInterface $settings, Container $container)
    {
        $this->settings = $settings;
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

        $uploadName = 'site-image-' . Str::lower(Str::random(8)) . '.png';

        $this->disk->put($uploadName, $file->getStream()->getContents());

        $this->settings->set('seo_social_media_image_path', $uploadName);
        $this->settings->set('seo_social_media_image_url', $this->disk->url($uploadName));
        return parent::data($request, $document);
    }
}
