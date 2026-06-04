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
use Flarum\Settings\SettingsRepositoryInterface;

class AttachForumSerializerAttributes
{
    public function __construct(
        protected readonly SettingsRepositoryInterface $settings,
    ) {
    }

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

        // Core's UploadImageButton reads the current image from the `<name>Url` forum
        // attribute, so expose the stored URL under the name it expects. Only needed by
        // admins configuring SEO, so it's gated behind the same permission.
        if ($attributes['canConfigureSeo']) {
            $attributes['seo_social_media_imageUrl'] = $this->settings->get('seo_social_media_image_url');
        }

        return $attributes;
    }
}
