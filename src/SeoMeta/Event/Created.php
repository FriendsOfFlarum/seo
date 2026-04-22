<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\SeoMeta\Event;

use FoF\Seo\SeoMeta\SeoMeta;

class Created
{
    public string $objectType;
    public int $objectId;

    public SeoMeta $seoMeta;

    public function __construct(SeoMeta $seoMeta)
    {
        $this->seoMeta = $seoMeta;
        $this->objectType = $seoMeta->object_type;
        $this->objectId = $seoMeta->object_id;
    }
}
