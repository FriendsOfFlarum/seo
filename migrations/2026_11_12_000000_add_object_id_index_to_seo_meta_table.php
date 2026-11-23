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
 * The index gets a name of its own rather than Laravel's conventional one, so
 * it cannot collide with a hand-made seo_meta_object_id_index and down() never
 * drops an index this migration did not create.
 *
 * Indexes are read from information_schema rather than Doctrine DBAL 2, which
 * throws on MySQL functional indexes (their key parts have no column name), and
 * from the write connection, which is where the ALTER TABLE runs.
 */

return [
    'up' => function (Builder $schema) {
        $connection = $schema->getConnection();

        $covered = $connection->selectOne(
            "SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND seq_in_index = 1 AND column_name = ? AND index_type = 'BTREE'",
            [$connection->getTablePrefix().'seo_meta', 'object_id'],
            false
        );

        if ($covered) {
            return;
        }

        $name = $connection->getTablePrefix().'seo_meta_object_id_lookup_index';

        $schema->table('seo_meta', function (Blueprint $table) use ($name) {
            $table->index(['object_id'], $name);
        });
    },
    'down' => function (Builder $schema) {
        $connection = $schema->getConnection();
        $name = $connection->getTablePrefix().'seo_meta_object_id_lookup_index';

        $exists = $connection->selectOne(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$connection->getTablePrefix().'seo_meta', $name],
            false
        );

        if (!$exists) {
            return;
        }

        $schema->table('seo_meta', function (Blueprint $table) use ($name) {
            $table->dropIndex($name);
        });
    },
];
