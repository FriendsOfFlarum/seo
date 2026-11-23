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
 *
 * Indexes are read from information_schema rather than Doctrine DBAL 2, which
 * throws on MySQL functional indexes (their key parts have no column name).
 */

return [
    'up' => function (Builder $schema) {
        $connection = $schema->getConnection();

        $covered = $connection->selectOne(
            "SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND seq_in_index = 1 AND column_name = ? AND index_type = 'BTREE'",
            [$connection->getTablePrefix().'seo_meta', 'object_id']
        );

        if ($covered) {
            return;
        }

        $schema->table('seo_meta', function (Blueprint $table) {
            $table->index(['object_id']);
        });
    },
    'down' => function (Builder $schema) {
        $connection = $schema->getConnection();

        $exists = $connection->selectOne(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$connection->getTablePrefix().'seo_meta', $connection->getTablePrefix().'seo_meta_object_id_index']
        );

        if (!$exists) {
            return;
        }

        $schema->table('seo_meta', function (Blueprint $table) {
            $table->dropIndex(['object_id']);
        });
    },
];
