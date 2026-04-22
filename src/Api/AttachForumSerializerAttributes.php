<?php

namespace FoF\Seo\Api;

use Flarum\Api\Serializer\ForumSerializer;

class AttachForumSerializerAttributes
{
    /**
     * @param ForumSerializer $serializer
     * @param mixed $model
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function __invoke(ForumSerializer $serializer, mixed $model, array $attributes): array
    {
        $actor = $serializer->getActor();

        $attributes['canConfigureSeo'] = (bool) $actor->hasPermissionLike('fof-seo.canConfigure');

        return $attributes;
    }
}
