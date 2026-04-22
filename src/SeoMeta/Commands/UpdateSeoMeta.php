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
    public User $actor;

    public int|string $id;

    /**
     * @var array<string, mixed>
     */
    public array $data;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(User $actor, int|string $id, array $data)
    {
        $this->actor = $actor;
        $this->id = $id;
        $this->data = $data;
    }
}
