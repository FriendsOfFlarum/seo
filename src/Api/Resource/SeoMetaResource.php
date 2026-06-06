<?php

namespace FoF\Seo\Api\Resource;

use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use FoF\Seo\SeoMeta\SeoMeta;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * @extends Resource\AbstractDatabaseResource<SeoMeta>
 */
class SeoMetaResource extends Resource\AbstractDatabaseResource
{
    public function type(): string
    {
        return 'seoMeta';
    }

    public function model(): string
    {
        return SeoMeta::class;
    }

    public function scope(Builder $query, OriginalContext $context): void
    {
        $query->whereVisibleTo($context->getActor());
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Update::make()
                ->can('update'),
            Endpoint\Show::make()
                ->authenticated(),
            Endpoint\Index::make()
                ->paginate(),
        ];
    }

    public function fields(): array
    {
        return [

            /**
             * @todo migrate logic from old serializer and controllers to this API Resource.
             * @see https://docs.flarum.org/2.x/extend/api#api-resources
             */

            // Example:
            Schema\Str::make('name')
                ->requiredOnCreate()
                ->minLength(3)
                ->maxLength(255)
                ->writable(),


        ];
    }

    public function sorts(): array
    {
        return [
            // SortColumn::make('createdAt'),
        ];
    }
}
