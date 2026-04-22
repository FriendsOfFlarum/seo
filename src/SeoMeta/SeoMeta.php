<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\SeoMeta;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\Foundation\EventGeneratorTrait;
use FoF\Seo\SeoMeta\Event\Created;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $id
 * @property int         $object_id
 * @property string      $object_type
 * @property bool        $auto_update_data
 * @property ?string     $title
 * @property ?string     $description
 * @property ?string     $keywords
 * @property bool        $robots_noindex
 * @property bool        $robots_nofollow
 * @property bool        $robots_noarchive
 * @property bool        $robots_noimageindex
 * @property bool        $robots_nosnippet
 * @property ?string     $twitter_title
 * @property ?string     $twitter_description
 * @property ?string     $twitter_image
 * @property ?string     $twitter_image_source
 * @property ?string     $open_graph_title
 * @property ?string     $open_graph_description
 * @property ?string     $open_graph_image
 * @property ?string     $open_graph_image_source
 * @property ?int        $estimated_reading_time
 * @property Carbon      $created_at
 * @property Carbon|null $updated_at
 */
class SeoMeta extends AbstractModel
{
    use EventGeneratorTrait;

    protected $table = 'seo_meta';

    protected $fillable = [
        'object_id',
        'object_type',

        // Other data
        'title',
        'description',
        'keywords',
        'created_at',
        'updated_at',
    ];

    /**
     * {@inheritdoc}
     */
    protected $dates = ['created_at', 'updated_at'];

    public static function build(string $objectType, int $objectId, bool $autoUpdate = true): self
    {
        $seoMeta = new static();
        $seoMeta->object_id = $objectId;
        $seoMeta->object_type = $objectType;
        $seoMeta->auto_update_data = $autoUpdate;
        $seoMeta->created_at = Carbon::now();

        return $seoMeta;
    }

    /**
     * Boot the model.
     *
     * @return void
     */
    public static function boot()
    {
        parent::boot();

        static::created(function (self $seoMeta) {
            $seoMeta->raise(new Created($seoMeta));
        });
    }

    /**
     * Find the SEO meta by object type.
     *
     * @param string $objectType Name of the object
     * @param int    $objectId   ID of the object
     */
    public static function findByObjectType(string $objectType, int $objectId): self
    {
        return self::firstOrCreate([
            'object_type' => $objectType,
            'object_id'   => $objectId,
        ]);
    }

    /**
     * Find the SEO meta by object type.
     *
     * @param string $objectType Name of the object
     * @param int    $objectId   ID of the object
     */
    public static function findByObjectTypeOrFail(string $objectType, int $objectId): self
    {
        return self::where([
            ['object_type', '=', $objectType],
            ['object_id', '=', $objectId],
        ])->firstOrFail();
    }

    /**
     * Find the SEO meta by object type.
     *
     * @param string        $objectType Name of the object
     * @param int           $objectId   ID of the object
     * @param callable|null $fillables  Optional callable to populate defaults on create
     */
    public static function findByObjectTypeOrCreate(string $objectType, int $objectId, ?callable $fillables = null): self
    {
        $query = self::where([
            ['object_type', '=', $objectType],
            ['object_id', '=', $objectId],
        ]);

        // No fillables
        if ($fillables === null) {
            return $query->firstOr(function () use ($objectType, $objectId): self {
                $data = SeoMeta::build($objectType, $objectId);

                $data->save();

                return $data;
            });
        }

        return $query->firstOr(function () use ($objectType, $objectId, $fillables): self {
            $data = SeoMeta::build($objectType, $objectId);

            $fillables($data);

            $data->save();

            return $data;
        });
    }

    /**
     * Find by slug.
     *
     * Could be used to add dynamic tags to pages that do not have a database row
     * For example: a blog home/overview page, knowledge base page, tags overview page etc.
     *
     * @param string        $pageSlug  Page slug used as object type
     * @param callable|null $fillables Optional callable to populate defaults on create
     */
    public static function findOrCreateBySlug(string $pageSlug, ?callable $fillables = null): self
    {
        return self::findByObjectTypeOrCreate(str_replace('-', '_', $pageSlug), -1, $fillables);
    }

    /**
     * Find the SEO meta of an object from a model.
     *
     * @param Model $model The model
     */
    public static function findOneByModel(Model $model): ?self
    {
        return self::where([
            'object_type' => $model->getTable(),
            'object_id'   => $model->getKey(),
        ])->first();
    }

    /**
     * Find the SEO meta of an object from a model.
     *
     * @param Model $model The model
     */
    public static function buildByModel(Model $model): self
    {
        return self::build($model->getTable(), $model->getKey());
    }

    /**
     * Find or create the SEO meta of an object from a model.
     *
     * @param Model          $model     The model
     * @param array|callable $fillables Defaults to fill when creating
     */
    public static function findByModelOrCreate(Model $model, array|callable $fillables = []): self
    {
        // Is an array with defaults
        if (!is_callable($fillables)) {
            return self::firstOrCreate([
                'object_type' => $model->getTable(),
                'object_id'   => $model->getKey(),
            ], $fillables);
        }

        return self::where([
            'object_type' => $model->getTable(),
            'object_id'   => $model->getKey(),
        ])->firstOr(function () use ($model, $fillables): self {
            $data = SeoMeta::build($model->getTable(), $model->getKey());

            $fillables($data);

            $data->save();

            return $data;
        });
    }
}
