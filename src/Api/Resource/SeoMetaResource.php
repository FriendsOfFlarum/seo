<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Api\Resource;

use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Flarum\Foundation\ValidationException;
use FoF\Seo\SeoMeta\Commands\UpdateSeoMeta;
use FoF\Seo\SeoMeta\SeoMeta;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * @extends Resource\AbstractDatabaseResource<SeoMeta>
 */
class SeoMetaResource extends Resource\AbstractDatabaseResource
{
    public function __construct(
        protected readonly BusDispatcher $bus,
    ) {
        // Note: the events dispatcher ($this->events) is provided by the parent
        // resource's boot() lifecycle, so it must not be injected here.
    }

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
        // Access is gated entirely through the `fof-seo.canConfigure` permission
        // (see the endpoints below and the find() override), so no per-row
        // visibility scoping is required here.
    }

    /**
     * Resolve a model for the Show/Update endpoints.
     *
     * In addition to a numeric primary key, the frontend looks SEO meta up by an
     * `{object_type}-{id}` pair (e.g. `discussions-123`), auto-creating the row
     * if it doesn't exist yet. The permission is asserted here because find()
     * runs before the endpoint's own authorization, and the lookup has a
     * create-on-read side effect we must not expose to unauthorised actors.
     */
    public function find(string $id, OriginalContext $context): ?object
    {
        $context->getActor()->assertCan('fof-seo.canConfigure');

        if (! is_numeric($id) && ($pos = strrpos($id, '-')) !== false) {
            $objectType = substr($id, 0, $pos);
            $objectId = substr($id, $pos + 1);

            if (! is_numeric($objectId)) {
                throw new ValidationException(['message' => 'Invalid slug/id combination']);
            }

            $seoMeta = SeoMeta::findByObjectTypeOrCreate($objectType, (int) $objectId);

            // Release any events raised while creating the row (e.g. the Created
            // event the discussion/tag subscribers use to auto-fill meta data).
            foreach ($seoMeta->releaseEvents() as $event) {
                $this->events->dispatch($event);
            }

            return $seoMeta;
        }

        return parent::find($id, $context);
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Show::make()
                ->can('fof-seo.canConfigure'),
            Endpoint\Update::make()
                ->can('fof-seo.canConfigure')
                // The update preserves "omitted attribute clears the value"
                // semantics that the frontend relies on, so it is delegated to
                // the existing command/handler rather than per-field setters.
                ->action(function (Context $context) {
                    $data = Arr::get($context->body(), 'data', []);

                    return $this->bus->dispatch(
                        new UpdateSeoMeta($context->getActor(), (int) $context->modelId, $data)
                    );
                }),
            Endpoint\Index::make()
                ->can('fof-seo.canConfigure')
                ->defaultSort('-createdAt')
                ->paginate(),
        ];
    }

    public function fields(): array
    {
        return [
            // Object info
            Schema\Str::make('objectType'),
            Schema\Integer::make('objectId'),

            // Auto update data
            Schema\Boolean::make('autoUpdateData'),

            // Default HTML tags
            Schema\Str::make('title')->nullable(),
            Schema\Str::make('description')->nullable(),
            Schema\Str::make('keywords')->nullable(),

            // Robots
            Schema\Boolean::make('robotsNoindex'),
            Schema\Boolean::make('robotsNofollow'),
            Schema\Boolean::make('robotsNoarchive'),
            Schema\Boolean::make('robotsNoimageindex'),
            Schema\Boolean::make('robotsNosnippet'),

            // Twitter tags
            Schema\Str::make('twitterTitle')->nullable(),
            Schema\Str::make('twitterDescription')->nullable(),
            Schema\Str::make('twitterImage')->nullable(),
            Schema\Str::make('twitterImageSource')
                ->get(fn (SeoMeta $seoMeta) => $seoMeta->twitter_image_source ?? 'auto'),

            // Open Graph tags
            Schema\Str::make('openGraphTitle')->nullable(),
            Schema\Str::make('openGraphDescription')->nullable(),
            Schema\Str::make('openGraphImage')->nullable(),
            Schema\Str::make('openGraphImageSource')
                ->get(fn (SeoMeta $seoMeta) => $seoMeta->open_graph_image_source ?? 'auto'),

            // Extra
            Schema\Integer::make('estimatedReadingTime')
                ->get(fn (SeoMeta $seoMeta) => (int) $seoMeta->estimated_reading_time),

            // Row info
            Schema\DateTime::make('createdAt'),
            Schema\DateTime::make('updatedAt')->nullable(),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('createdAt'),
        ];
    }
}
