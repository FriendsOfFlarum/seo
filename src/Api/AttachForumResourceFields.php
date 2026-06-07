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

use Flarum\Api\Context;
use Flarum\Api\Schema;
use Flarum\Settings\SettingsRepositoryInterface;

/**
 * Appends the SEO admin fields to the Forum API resource.
 *
 * @see \Flarum\Api\Resource\ForumResource
 */
class AttachForumResourceFields
{
    public function __construct(
        protected readonly SettingsRepositoryInterface $settings,
    ) {
    }

    /**
     * @return array<\Tobyz\JsonApiServer\Schema\Field\Field>
     */
    public function __invoke(): array
    {
        return [
            Schema\Boolean::make('canConfigureSeo')
                ->get(fn ($forum, Context $context) => (bool) $context->getActor()->hasPermissionLike('fof-seo.canConfigure')),

            // Core's UploadImageButton reads the current image from the `<name>Url`
            // forum attribute, so expose the stored URL under the name it expects.
            // Only needed by admins configuring SEO, so it's gated behind the same
            // permission.
            Schema\Str::make('seo_social_media_imageUrl')
                ->nullable()
                ->visible(fn ($forum, Context $context) => (bool) $context->getActor()->hasPermissionLike('fof-seo.canConfigure'))
                ->get(fn ($forum, Context $context) => $this->settings->get('seo_social_media_image_url')),
        ];
    }
}
