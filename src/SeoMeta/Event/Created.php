<?php

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
