<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Api;

use Flarum\Api\Serializer\ForumSerializer;

class AttachForumSerializerAttributes
{
    /**
     * @param ForumSerializer      $serializer
     * @param mixed                $model
     * @param array<string, mixed> $attributes
     *
     * @return array<string, mixed>
     */
    public function __invoke(ForumSerializer $serializer, mixed $model, array $attributes): array
    {
        $actor = $serializer->getActor();

        $attributes['canConfigureSeo'] = (bool) $actor->hasPermissionLike('fof-seo.canConfigure');

        return $attributes;
    }
}
