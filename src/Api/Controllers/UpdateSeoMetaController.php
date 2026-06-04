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

use Flarum\Api\Controller\AbstractShowController;
use Flarum\Http\RequestUtil;
use FoF\Seo\Api\Serializers\SeoMetaSerializer;
use FoF\Seo\SeoMeta\Commands\UpdateSeoMeta;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class UpdateSeoMetaController extends AbstractShowController
{
    /**
     * {@inheritdoc}
     */
    public $serializer = SeoMetaSerializer::class;

    public function __construct(
        private readonly Dispatcher $events,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $id = Arr::get($request->getQueryParams(), 'id');
        $data = Arr::get($request->getParsedBody(), 'data', false);

        return $this->events->dispatch(
            new UpdateSeoMeta($actor, (int) $id, $data)
        );
    }
}
