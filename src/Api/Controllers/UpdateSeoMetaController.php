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
 * Serves `PATCH /api/seo_meta/{id}` by delegating to the SeoMetaResource Update
 * endpoint, which dispatches the UpdateSeoMeta command.
 */
class UpdateSeoMetaController implements RequestHandlerInterface
{
    public function __construct(
        protected readonly JsonApi $api,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->api
            ->forResource(SeoMetaResource::class)
            ->forEndpoint('update')
            ->handle($request);
    }
}
