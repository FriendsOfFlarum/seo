<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Tests\integration\sitemap;

use Carbon\Carbon;
use Flarum\Testing\integration\TestCase;
use FoF\Sitemap\Generate\RobotsGenerator;
use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Google can only read a `noindex` on a page it may fetch. So while fof/seo
 * noindexes profiles or tags, fof/sitemap's robots.txt must not block them;
 * when fof/seo noindexes nothing there, fof/sitemap's blocks stay as they are.
 */
class RobotsIndexabilityTest extends TestCase
{
    private const USERS = '#^Disallow:\s*/u/$#m';
    private const TAG_PAGES = '#^Disallow:\s*/t/$#m';
    private const TAGS_INDEX = '#^Disallow:\s*/tags$#m';

    /**
     * Enable the extensions in the order Flarum boots them. fof/seo lists
     * fof/sitemap as an optional dependency, so core boots fof/sitemap (and
     * the TagEntry it adds) first; flarum/testing boots exactly the order
     * given here, so listing fof-sitemap first matches production.
     */
    private function enable(bool $withSeo = true): void
    {
        $this->extension(...array_filter(['flarum-tags', 'fof-sitemap', $withSeo ? 'fof-seo' : null]));

        $now = Carbon::parse('2026-01-01 12:00:00');

        $this->prepareDatabase([
            'tags' => [
                ['id' => 1, 'name' => 'One', 'slug' => 'one', 'position' => 0],
                ['id' => 2, 'name' => 'Two', 'slug' => 'two', 'position' => 1],
            ],
            'discussions' => [
                ['id' => 1, 'title' => 'D', 'slug' => 'd', 'user_id' => 1, 'comment_count' => 1, 'created_at' => $now],
            ],
        ]);
    }

    private function robotsTxt(): string
    {
        $response = $this->send($this->request('GET', '/robots.txt'));

        $this->assertSame(200, $response->getStatusCode());

        return (string) $response->getBody();
    }

    private function seoMeta(string $type, int $id, bool $noindex): void
    {
        $now = Carbon::parse('2026-01-01 12:00:00');

        $this->prepareDatabase([
            'seo_meta' => [
                ['object_type' => $type, 'object_id' => $id, 'robots_noindex' => $noindex, 'created_at' => $now, 'updated_at' => $now],
            ],
        ]);
    }

    private function assertUntouchedEntries(string $robots): void
    {
        // fof/sitemap's other entries are never touched.
        $this->assertStringContainsString('Disallow: /admin', $robots);
        $this->assertStringContainsString('Disallow: /api', $robots);
        $this->assertMatchesRegularExpression('#^Sitemap: .*/sitemap\.xml$#m', $robots);
    }

    #[Test]
    public function profile_block_stays_while_profiles_are_indexable(): void
    {
        $this->enable();
        $this->setting('fof-sitemap.excludeUsers', true);
        $this->setting('seo_noindex_profiles', '0');

        $robots = $this->robotsTxt();

        $this->assertMatchesRegularExpression(self::USERS, $robots);
        $this->assertUntouchedEntries($robots);
    }

    #[Test]
    public function profile_block_is_removed_while_profiles_are_noindexed(): void
    {
        $this->enable();
        $this->setting('fof-sitemap.excludeUsers', true);
        $this->setting('seo_noindex_profiles', '1');

        $robots = $this->robotsTxt();

        $this->assertDoesNotMatchRegularExpression(self::USERS, $robots);
        $this->assertUntouchedEntries($robots);
    }

    #[Test]
    public function tag_blocks_stay_while_no_tag_is_noindexed(): void
    {
        $this->enable();
        $this->setting('fof-sitemap.excludeTags', true);
        $this->setting('seo_noindex_tags', '[]');
        // Neither a tag row without noindex nor another type's noindex row
        // with a tag's ID counts.
        $this->seoMeta('tags', 1, false);
        $this->seoMeta('discussions', 2, true);

        $robots = $this->robotsTxt();

        $this->assertMatchesRegularExpression(self::TAG_PAGES, $robots);
        $this->assertMatchesRegularExpression(self::TAGS_INDEX, $robots);
        $this->assertUntouchedEntries($robots);
    }

    #[Test]
    public function tag_blocks_are_removed_while_a_tag_is_selected_for_noindex(): void
    {
        $this->enable();
        $this->setting('fof-sitemap.excludeTags', true);
        $this->setting('seo_noindex_tags', json_encode([1]));

        $robots = $this->robotsTxt();

        $this->assertDoesNotMatchRegularExpression(self::TAG_PAGES, $robots);
        $this->assertDoesNotMatchRegularExpression(self::TAGS_INDEX, $robots);
        $this->assertUntouchedEntries($robots);
    }

    #[Test]
    public function tag_blocks_are_removed_while_a_tag_has_its_own_noindex(): void
    {
        $this->enable();
        $this->setting('fof-sitemap.excludeTags', true);
        $this->seoMeta('tags', 2, true);

        $robots = $this->robotsTxt();

        $this->assertDoesNotMatchRegularExpression(self::TAG_PAGES, $robots);
        $this->assertDoesNotMatchRegularExpression(self::TAGS_INDEX, $robots);
        $this->assertUntouchedEntries($robots);
    }

    #[Test]
    public function profile_and_tag_decisions_are_independent(): void
    {
        $this->enable();
        $this->setting('fof-sitemap.excludeUsers', true);
        $this->setting('fof-sitemap.excludeTags', true);
        $this->setting('seo_noindex_profiles', '1');

        $robots = $this->robotsTxt();

        $this->assertDoesNotMatchRegularExpression(self::USERS, $robots);
        $this->assertMatchesRegularExpression(self::TAG_PAGES, $robots);
        $this->assertMatchesRegularExpression(self::TAGS_INDEX, $robots);
    }

    public static function noindexStates(): array
    {
        return [
            'fof/seo noindexes nothing' => [false],
            'fof/seo noindexes both'    => [true],
        ];
    }

    /**
     * With fof/sitemap's exclusions off there is nothing to block, whatever
     * fof/seo does.
     */
    #[Test]
    #[DataProvider('noindexStates')]
    public function nothing_is_blocked_while_sitemap_exclusions_are_off(bool $noindex): void
    {
        $this->enable();
        $this->setting('fof-sitemap.excludeUsers', false);
        $this->setting('fof-sitemap.excludeTags', false);
        $this->setting('seo_noindex_profiles', $noindex ? '1' : '0');
        $this->setting('seo_noindex_tags', $noindex ? json_encode([1]) : '[]');

        $robots = $this->robotsTxt();

        $this->assertDoesNotMatchRegularExpression(self::USERS, $robots);
        $this->assertDoesNotMatchRegularExpression(self::TAG_PAGES, $robots);
        $this->assertDoesNotMatchRegularExpression(self::TAGS_INDEX, $robots);
        $this->assertUntouchedEntries($robots);
    }

    /**
     * The per-item tag check is one query, however many tags have SEO meta.
     */
    #[Test]
    public function generating_robots_txt_runs_the_same_queries_for_few_and_many_tags(): void
    {
        $this->enable();
        $this->setting('fof-sitemap.excludeTags', true);

        $container = $this->app()->getContainer();
        /** @var \Illuminate\Database\Connection $db */
        $db = $container->make(ConnectionInterface::class);

        $count = function () use ($container, $db): int {
            $db->flushQueryLog();
            $db->enableQueryLog();
            $container->make(RobotsGenerator::class)->generate();
            $queries = count($db->getQueryLog());
            $db->disableQueryLog();

            return $queries;
        };

        $now = Carbon::parse('2026-01-01 12:00:00');
        $count(); // warm up per-process lookups
        $few = $count();

        for ($id = 3; $id <= 60; $id++) {
            $db->table('tags')->insert(['id' => $id, 'name' => "Tag $id", 'slug' => "tag-$id", 'position' => $id]);
            $db->table('seo_meta')->insert(['object_type' => 'tags', 'object_id' => $id, 'robots_noindex' => false, 'created_at' => $now, 'updated_at' => $now]);
        }

        $this->assertSame($few, $count());
        $this->assertLessThanOrEqual(1, $few, 'At most one query for the per-item tag check');
    }

    /**
     * Control: without fof/seo, fof/sitemap keeps its blocks.
     */
    #[Test]
    public function robots_txt_keeps_sitemap_blocks_without_fof_seo(): void
    {
        $this->enable(false);
        $this->setting('fof-sitemap.excludeUsers', true);
        $this->setting('fof-sitemap.excludeTags', true);

        $robots = $this->robotsTxt();

        $this->assertMatchesRegularExpression(self::USERS, $robots);
        $this->assertMatchesRegularExpression(self::TAG_PAGES, $robots);
        $this->assertMatchesRegularExpression(self::TAGS_INDEX, $robots);
    }
}
