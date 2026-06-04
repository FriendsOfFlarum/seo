<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

use Illuminate\Database\Schema\Builder;

/*
 * Rename the `seo.canConfigure` permission to `fof-seo.canConfigure` on
 * transfer from v17development/flarum-seo to fof/seo so it matches the FoF
 * extension-id convention.
 */
return [
    'up' => function (Builder $schema) {
        $schema->getConnection()
            ->table('group_permission')
            ->where('permission', 'seo.canConfigure')
            ->update(['permission' => 'fof-seo.canConfigure']);
    },
    'down' => function (Builder $schema) {
        $schema->getConnection()
            ->table('group_permission')
            ->where('permission', 'fof-seo.canConfigure')
            ->update(['permission' => 'seo.canConfigure']);
    },
];
