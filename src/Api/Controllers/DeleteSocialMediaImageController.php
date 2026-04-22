<?php

namespace FoF\Seo\Api\Controllers;

use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Filesystem\Cloud;
use Psr\Http\Message\ServerRequestInterface;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Flarum\Api\Controller\AbstractDeleteController;

class DeleteSocialMediaImageController extends AbstractDeleteController
{
    protected SettingsRepositoryInterface $settings;
    protected Cloud $disk;

    public function __construct(SettingsRepositoryInterface $settings, Container $container)
    {
        $this->settings = $settings;
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
