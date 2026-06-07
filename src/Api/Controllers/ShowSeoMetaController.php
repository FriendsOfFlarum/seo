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
 * Serves `GET /api/seo_meta/{id}` by delegating to the SeoMetaResource Show
 * endpoint. The `{id}` segment may be a numeric primary key or an
 * `{object_type}-{id}` pair; the resource's find() override handles both.
 */
class ShowSeoMetaController implements RequestHandlerInterface
{
    public function __construct(
        protected readonly JsonApi $api,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->api
            ->forResource(SeoMetaResource::class)
            ->forEndpoint('show')
            ->handle($request);
    }
}
