<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Tests\integration\forum;

use Flarum\Http\RequestUtil;
use Flarum\User\Guest;
use FoF\Seo\Listeners\PageListener;
use FoF\Seo\Page\DiscussionBestAnswerPage;
use FoF\Seo\Page\DiscussionPage;
use FoF\Seo\SeoProperties;
use FoF\Seo\Tests\integration\ForumHtmlTestCase;
use Illuminate\Database\Events\QueryExecuted;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\Test;

class DiscussionContextMissTest extends ForumHtmlTestCase
{
    #[Test]
    public function discussion_miss_is_cached_for_both_drivers_but_not_another_request(): void
    {
        $this->extension('flarum-tags', 'fof-best-answer', 'fof-seo');
        $container = $this->app()->getContainer();
        $drivers = [$container->make(DiscussionPage::class), $container->make(DiscussionBestAnswerPage::class)];
        $properties = new SeoProperties($container->make(PageListener::class));
        $request = RequestUtil::withActor((new ServerRequest())->withQueryParams(['id' => '999-missing']), new Guest());
        $queries = [];
        $container->make('events')->listen(QueryExecuted::class, function (QueryExecuted $event) use (&$queries) {
            $queries[] = $event->sql;
        });
        foreach ($drivers as $driver) {
            $driver->handle($request, $properties);
        }
        $discussionSelect = 'select * from '.$this->database()->getQueryGrammar()->wrapTable('discussions');
        $lookups = function () use (&$queries, $discussionSelect) {
            return array_filter($queries, fn ($sql) => str_starts_with($sql, $discussionSelect));
        };
        $this->assertCount(1, $lookups());
        foreach ($drivers as $driver) {
            $driver->handle(clone $request, $properties);
        }
        $this->assertCount(3, $lookups(), 'Each distinct request must resolve afresh');
    }
}
