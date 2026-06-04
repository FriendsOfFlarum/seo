<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Api\Serializers;

use Flarum\Api\Serializer\AbstractSerializer;
use FoF\Seo\SeoMeta\SeoMeta;

class SeoMetaSerializer extends AbstractSerializer
{
    /**
     * {@inheritdoc}
     */
    protected $type = 'seoMeta';

    /**
     * {@inheritdoc}
     *
     * @param SeoMeta $seoMeta
     */
    protected function getDefaultAttributes($seoMeta)
    {
        return [
            // Object info
            'objectType'            => $seoMeta->object_type,
            'objectId'              => $seoMeta->object_id,

            // Auto update data
            'autoUpdateData'        => (bool) $seoMeta->auto_update_data,

            // Default HTML Tags
            'title'                 => $seoMeta->title,
            'description'           => $seoMeta->description,
            'keywords'              => $seoMeta->keywords,

            // Robots
            'robotsNoindex'         => (bool) $seoMeta->robots_noindex,
            'robotsNofollow'        => (bool) $seoMeta->robots_nofollow,
            'robotsNoarchive'       => (bool) $seoMeta->robots_noarchive,
            'robotsNoimageindex'    => (bool) $seoMeta->robots_noimageindex,
            'robotsNosnippet'       => (bool) $seoMeta->robots_nosnippet,

            // Twitter tags
            'twitterTitle'          => $seoMeta->twitter_title,
            'twitterDescription'    => $seoMeta->twitter_description,
            'twitterImage'          => $seoMeta->twitter_image,
            'twitterImageSource'    => $seoMeta->twitter_image_source ?? 'auto',

            // Open Graph tags
            'openGraphTitle'        => $seoMeta->open_graph_title,
            'openGraphDescription'  => $seoMeta->open_graph_description,
            'openGraphImage'        => $seoMeta->open_graph_image,
            'openGraphImageSource'  => $seoMeta->open_graph_image_source ?? 'auto',

            // Extra
            'estimatedReadingTime'  => (int) $seoMeta->estimated_reading_time,

            // Row info
            'createdAt' => $this->formatDate($seoMeta->created_at),
            'updatedAt' => $this->formatDate($seoMeta->updated_at),
        ];
    }
}
