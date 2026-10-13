<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Page;

/**
 * For page drivers that render replies, and have an `$extensionManager`.
 */
trait LoadsMentionRelations
{
    /**
     * What flarum/mentions' formatter reads when a reply is rendered. Without
     * these loaded it looks each reply's mentions up separately.
     *
     * @return string[]
     */
    protected function mentionRelations(bool $tagsEnabled): array
    {
        if (!$this->extensionManager->isEnabled('flarum-mentions')) {
            return [];
        }

        $relations = ['mentionsPosts.user', 'mentionsPosts.discussion', 'mentionsUsers', 'mentionsGroups'];

        // flarum/tags defines this relation, so it only exists alongside it.
        if ($tagsEnabled) {
            $relations[] = 'mentionsTags';
        }

        return $relations;
    }
}
