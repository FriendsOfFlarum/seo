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
use Flarum\Foundation\DispatchEventsTrait;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use FoF\Seo\Api\Serializers\SeoMetaSerializer;
use FoF\Seo\SeoMeta\SeoMeta;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ShowSeoMetaController extends AbstractShowController
{
    use DispatchEventsTrait;

    /**
     * {@inheritdoc}
     */
    public $serializer = SeoMetaSerializer::class;

    public function __construct(Dispatcher $events)
    {
        $this->events = $events;
    }

    /**
     * {@inheritdoc}
     */
    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);

        // Make sure the person can access the agents
        $actor->assertCan('fof-seo.canConfigure');

        $id = Arr::get($request->getQueryParams(), 'id');
        $objectType = Arr::get($request->getQueryParams(), 'object_type');

        // Make sure the ID part is numeric
        if ($id === null || !is_numeric($id)) {
            throw new ValidationException([
                'message' => 'Invalid slug/id combination',
            ]);
        }

        // Find SeoMeta by it's unique ID
        if ($objectType === null) {
            return SeoMeta::findOrFail($id);
        }

        // Find SeoMeta by a object-type combination
        $seoMeta = SeoMeta::findByObjectTypeOrCreate(
            $objectType,
            $id
        );

        $this->dispatchEventsFor($seoMeta, $actor);

        return $seoMeta;
    }
}
