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
 * Installs that ran an early version of the create migration have no usable
 * index on seo_meta.object_id. object_type was TEXT then: MySQL rejected the
 * unique (object_id, object_type) index, MariaDB 10.4+ built it as a long
 * unique HASH index that cannot serve lookups, and the hasTable() guard
 * skipped every retry. Non-unique, because old MySQL tables may hold duplicates.
 */

return [
    'up' => function (Builder $schema) {
        foreach ($schema->getIndexes('seo_meta') as $index) {
            // SQLite reports no type; its indexes are always B-trees.
            if (($index['columns'][0] ?? null) === 'object_id' && ($index['type'] ?? 'btree') === 'btree') {
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
