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
 *
 * Indexes are read from information_schema rather than Doctrine DBAL 2, which
 * throws on MySQL functional indexes (their key parts have no column name).
 */

return [
    'up' => function (Builder $schema) {
        $connection = $schema->getConnection();

        $covered = $connection->selectOne(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND seq_in_index = 1 AND column_name = ?',
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
