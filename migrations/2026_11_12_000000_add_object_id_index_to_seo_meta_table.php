<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/*
 * Installs that ran an early version of the create migration have no index on
 * seo_meta: object_type was TEXT, MySQL refused the unique index, and the
 * hasTable() guard skipped every retry. Fresh installs already have
 * unique(object_id, object_type), so this only adds an index where none leads
 * with object_id. Non-unique, because those old tables may hold duplicates.
 */

return [
    'up' => function (Builder $schema) {
        foreach ($schema->getIndexes('seo_meta') as $index) {
            if (($index['columns'][0] ?? null) === 'object_id') {
                return;
            }
        }

        $schema->table('seo_meta', function (Blueprint $table) {
            $table->index(['object_id']);
        });
    },
    'down' => function (Builder $schema) {
        if (!$schema->hasIndex('seo_meta', $schema->getConnection()->getTablePrefix().'seo_meta_object_id_index')) {
            return;
        }

        $schema->table('seo_meta', function (Blueprint $table) {
            $table->dropIndex(['object_id']);
        });
    },
];
