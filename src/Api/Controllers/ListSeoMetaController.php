<?php

namespace FoF\Seo\Api\Controllers;

use Flarum\Api\Controller\AbstractListController;
use Flarum\Http\UrlGenerator;
use Flarum\Http\RequestUtil;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;
use FoF\Seo\Api\Serializers\SeoMetaSerializer;
use FoF\Seo\SeoMeta\SeoMeta;

class ListSeoMetaController extends AbstractListController
{
    /**
     * {@inheritdoc}
     */
    public $serializer = SeoMetaSerializer::class;

    public $include = [];

    public $sortFields = ['id'];

    public $limit = 50;

    /**
     * @var UrlGenerator
     */
    protected $url;

    /**
     * @param UrlGenerator $url
     */
    public function __construct(UrlGenerator $url)
    {
        $this->url = $url;
    }

    /**
     * {@inheritdoc}
     */
    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);

        // Make sure the person can access the agents
        $actor->assertCan('fof-seo.canConfigure');

        // Params
        $limit = $this->extractLimit($request);
        $offset = $this->extractOffset($request);

        $results = SeoMeta::select()
            ->with($this->extractInclude($request))
            ->latest('seo_meta.created_at')
            ->skip($offset)
            ->take($limit + 1)
            ->get();

        // Check for more results
        $hasMoreResults = $limit > 0 && $results->count() > $limit;

        // Pop
        if ($hasMoreResults) {
            $results->pop();
        }

        // Add pagination to the request
        $document->addPaginationLinks(
            $this->url->to('api')->route('seo_meta.overview'),
            $request->getQueryParams(),
            $offset,
            $limit,
            $hasMoreResults ? null : 0
        );

        return $results;
    }
}
