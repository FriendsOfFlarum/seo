<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\SeoMeta\Commands;

use Flarum\User\User;

class UpdateSeoMeta
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly User $actor,
        public readonly int|string $id,
        public readonly array $data,
    ) {
    }
}
