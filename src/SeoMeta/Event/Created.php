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
    public readonly string $objectType;
    public readonly int $objectId;

    public function __construct(
        public readonly SeoMeta $seoMeta,
    ) {
        $this->objectType = $seoMeta->object_type;
        $this->objectId = $seoMeta->object_id;
    }
}
