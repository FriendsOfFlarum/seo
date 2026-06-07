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

use Flarum\Api\JsonApi;
use FoF\Seo\Api\Resource\SeoMetaResource;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Serves `GET /api/seo_meta` by delegating to the SeoMetaResource Index
 * endpoint. The dedicated route lets us keep the established `/seo_meta` URL
 * while the resource (type `seoMeta`) owns all of the logic.
 */
class ListSeoMetaController implements RequestHandlerInterface
{
    public function __construct(
        protected readonly JsonApi $api,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->api
            ->forResource(SeoMetaResource::class)
            ->forEndpoint('index')
            ->handle($request);
    }
}
