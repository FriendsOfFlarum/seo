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
use FoF\Seo\Tests\integration\MatchesRobotsRules;
use FoF\Sitemap\Generate\RobotsGenerator;
use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\Attributes\Test;

/**
 * With fof/sitemap's "exclude tags" on, its `Disallow: /t/` and `/tags` stay,
 * and fof/seo adds an `Allow` for each noindexed tag page guests can open, so
 * crawlers can fetch exactly those pages and read their `noindex`.
 */
class RobotsTagAllowTest extends TestCase
{
    use MatchesRobotsRules;

    /**
     * Enable the extensions in the order Flarum boots them. fof/seo lists
     * fof/sitemap as an optional dependency, so core boots fof/sitemap (and
     * the TagEntry it adds) first; flarum/testing boots exactly the order
     * given here, so listing fof-sitemap first matches production.
     */
    private function enable(bool $withSeo = true): void
    {
        $this->extension(...array_filter(['flarum-tags', 'fof-sitemap', $withSeo ? 'fof-seo' : null]));

        $this->setting('fof-sitemap.excludeTags', true);

        $tag = fn (int $id, string $slug, array $extra = []) => array_merge(
            ['id' => $id, 'name' => ucfirst($slug), 'slug' => $slug, 'position' => $id],
            $extra
        );

        $this->prepareDatabase([
            'tags' => [
                $tag(1, 'one'),
                $tag(2, 'one-child', ['parent_id' => 1]),
                $tag(3, 'one-more'),                         // shares a prefix with "one"
                $tag(4, 'two'),
                $tag(5, 'staff', ['is_restricted' => true]), // guests can't open it...
                $tag(6, 'staff-child', ['parent_id' => 5]),  // ...nor its children
                $tag(7, '動力'),
                $tag(8, "q&a(1)+it's"),                     // ASCII crawlers request as typed
                $tag(9, 'a*b$c?d#e%f'),                     // robots.txt / URL syntax
            ],
        ]);
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

    private function robotsTxt(): string
    {
        $response = $this->send($this->request('GET', '/robots.txt'));

        $this->assertSame(200, $response->getStatusCode());

        return (string) $response->getBody();
    }

    /**
     * @return list<string>
     */
    private function allowLines(string $robots): array
    {
        preg_match_all('/^Allow: .*$/m', $robots, $matches);

        return $matches[0];
    }

    private function assertSitemapBlocksKept(string $robots): void
    {
        $this->assertMatchesRegularExpression('#^Disallow: /t/$#m', $robots);
        $this->assertMatchesRegularExpression('#^Disallow: /tags$#m', $robots);
        $this->assertStringContainsString('Disallow: /admin', $robots);
        $this->assertMatchesRegularExpression('#^Sitemap: .*/sitemap\.xml$#m', $robots);
    }

    #[Test]
    public function nothing_is_allowed_while_no_tag_is_noindexed(): void
    {
        $this->enable();
        // Neither a tag row without noindex nor another type's noindex row
        // with a tag's ID counts.
        $this->seoMeta('tags', 1, false);
        $this->seoMeta('discussions', 4, true);

        $robots = $this->robotsTxt();

        $this->assertSitemapBlocksKept($robots);
        $this->assertSame([], $this->allowLines($robots));
    }

    #[Test]
    public function a_selected_tag_and_its_children_are_allowed_and_nothing_else(): void
    {
        $this->enable();
        $this->setting('seo_noindex_tags', json_encode([1]));

        $robots = $this->robotsTxt();

        $this->assertSitemapBlocksKept($robots);
        $this->assertSame([
            'Allow: /t/one$',
            'Allow: /t/one?',
            'Allow: /t/one-child$',
            'Allow: /t/one-child?',
        ], $this->allowLines($robots));

        // What a crawler that follows Google's rules may fetch.
        $this->assertTrue($this->crawlable($robots, '/t/one'));
        $this->assertTrue($this->crawlable($robots, '/t/one?sort=newest'));
        $this->assertTrue($this->crawlable($robots, '/t/one-child'));
        $this->assertFalse($this->crawlable($robots, '/t/one-more'), 'A tag sharing the prefix stays blocked');
        $this->assertFalse($this->crawlable($robots, '/t/one/'));
        $this->assertFalse($this->crawlable($robots, '/t/two'));
        $this->assertFalse($this->crawlable($robots, '/tags'));
        $this->assertFalse($this->crawlable($robots, '/t/'));
        $this->assertTrue($this->crawlable($robots, '/d/1-hello'), 'Discussions are not affected');
    }

    #[Test]
    public function a_tag_with_its_own_noindex_is_allowed(): void
    {
        $this->enable();
        $this->seoMeta('tags', 4, true);

        $robots = $this->robotsTxt();

        $this->assertSitemapBlocksKept($robots);
        $this->assertSame(['Allow: /t/two$', 'Allow: /t/two?'], $this->allowLines($robots));
        $this->assertTrue($this->crawlable($robots, '/t/two'));
        $this->assertFalse($this->crawlable($robots, '/t/one'));
    }

    /**
     * A noindex no crawler can reach allows nothing: a selected ID whose tag
     * was deleted, a restricted tag, and (flarum-tags hides them too) its
     * children.
     */
    #[Test]
    public function unreachable_tags_are_not_allowed(): void
    {
        $this->enable();
        $this->setting('seo_noindex_tags', json_encode([99, 5]));
        $this->seoMeta('tags', 6, true);

        $robots = $this->robotsTxt();

        $this->assertSitemapBlocksKept($robots);
        $this->assertSame([], $this->allowLines($robots));
    }

    /**
     * Slugs are percent-encoded as RFC 3986 / RFC 9309 require, so a UTF-8
     * slug matches the URL a crawler requests.
     */
    #[Test]
    public function a_utf8_slug_is_percent_encoded(): void
    {
        $this->enable();
        $this->setting('seo_noindex_tags', json_encode([7]));

        $robots = $this->robotsTxt();

        $this->assertSame(['Allow: /t/%E5%8B%95%E5%8A%9B$', 'Allow: /t/%E5%8B%95%E5%8A%9B?'], $this->allowLines($robots));
        $this->assertTrue($this->crawlable($robots, '/t/%E5%8B%95%E5%8A%9B'));
    }

    /**
     * Ordinary ASCII stays as crawlers request it; only robots.txt and URL
     * syntax (`*`, `$`, `?`, `#`, `%`) is percent-encoded, as RFC 9309
     * section 2.2.3 does for a literal `*` (`%2A`).
     */
    #[Test]
    public function only_robots_and_url_syntax_is_percent_encoded(): void
    {
        $this->enable();
        $this->setting('seo_noindex_tags', json_encode([8, 9]));

        $robots = $this->robotsTxt();

        $this->assertSame([
            "Allow: /t/q&a(1)+it's$",
            "Allow: /t/q&a(1)+it's?",
            'Allow: /t/a%2Ab%24c%3Fd%23e%25f$',
            'Allow: /t/a%2Ab%24c%3Fd%23e%25f?',
        ], $this->allowLines($robots));
        $this->assertTrue($this->crawlable($robots, "/t/q&a(1)+it's"));
    }

    /**
     * Crawlers that take the first matching rule (instead of the longest)
     * must meet the Allow lines before `Disallow: /t/`.
     */
    #[Test]
    public function allow_lines_come_before_the_tag_disallows(): void
    {
        $this->enable();
        $this->setting('seo_noindex_tags', json_encode([4]));

        $robots = $this->robotsTxt();

        $this->assertLessThan(strpos($robots, 'Disallow: /t/'), strpos($robots, 'Allow: /t/two$'));
        $this->assertSitemapBlocksKept($robots);
    }

    #[Test]
    public function allow_lines_follow_the_tag_slug_driver(): void
    {
        $this->setting('slug_driver_Flarum\\Tags\\Tag', 'id_with_slug');
        $this->enable();
        $this->setting('seo_noindex_tags', json_encode([4]));

        $robots = $this->robotsTxt();

        $this->assertSame(['Allow: /t/4-two$', 'Allow: /t/4-two?'], $this->allowLines($robots));
        $this->assertTrue($this->crawlable($robots, '/t/4-two'));
        $this->assertFalse($this->crawlable($robots, '/t/4-two-more'));
    }

    #[Test]
    public function allow_lines_include_the_forum_base_path(): void
    {
        $this->config('url', 'http://localhost/forum');
        $this->enable();
        $this->setting('seo_noindex_tags', json_encode([4]));

        $robots = $this->app()->getContainer()->make(RobotsGenerator::class)->generate();

        $this->assertMatchesRegularExpression('#^Disallow: /forum/t/$#m', $robots);
        $this->assertSame(['Allow: /forum/t/two$', 'Allow: /forum/t/two?'], $this->allowLines($robots));
        $this->assertTrue($this->crawlable($robots, '/forum/t/two'));
        $this->assertFalse($this->crawlable($robots, '/forum/t/one'));
    }

    /**
     * One query, however many tags are noindexed: no per-tag lookups.
     */
    #[Test]
    public function generating_robots_txt_runs_the_same_queries_for_few_and_many_noindexed_tags(): void
    {
        $this->enable();
        $this->setting('seo_noindex_tags', json_encode([4]));

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

        for ($id = 10; $id <= 70; $id++) {
            $db->table('tags')->insert(['id' => $id, 'name' => "Tag $id", 'slug' => "tag-$id", 'position' => $id]);
            $db->table('seo_meta')->insert(['object_type' => 'tags', 'object_id' => $id, 'robots_noindex' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        $many = $count();

        $this->assertCount(2 + 2 * 61, $this->allowLines($container->make(RobotsGenerator::class)->generate()));
        $this->assertSame($few, $many);
        $this->assertLessThanOrEqual(1, $few, 'One query for the noindexed tags');
    }

    /**
     * Control: without fof/seo, fof/sitemap's output has no Allow lines.
     */
    #[Test]
    public function without_fof_seo_nothing_is_allowed(): void
    {
        $this->enable(false);
        $this->setting('seo_noindex_tags', json_encode([1]));

        $robots = $this->robotsTxt();

        $this->assertSitemapBlocksKept($robots);
        $this->assertSame([], $this->allowLines($robots));
    }
}
