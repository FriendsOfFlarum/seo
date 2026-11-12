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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Google can only read a `noindex` on a page it may fetch. So while fof/seo
 * noindexes profiles, fof/sitemap's robots.txt must not block them; when it
 * doesn't, fof/sitemap's block stays as it is. Tags: RobotsTagAllowTest.
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
                // No guest permission: guests (and crawlers) can't open it.
                ['id' => 3, 'name' => 'Staff', 'slug' => 'staff', 'position' => 2, 'is_restricted' => true],
                ['id' => 4, 'name' => 'Staff child', 'slug' => 'staff-child', 'position' => 3, 'parent_id' => 3],
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
        $this->assertStringNotContainsString('Allow:', $robots, 'No tag is noindexed');
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
        $this->assertStringNotContainsString('Allow:', $robots, 'Nothing is blocked, so nothing needs allowing');
        $this->assertUntouchedEntries($robots);
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
