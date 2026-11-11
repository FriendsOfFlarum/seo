<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Tests\integration;

use FoF\Sitemap\Deploy\DeployInterface;
use FoF\Sitemap\Deploy\Memory;
use FoF\Sitemap\Generate\Generator;
use Illuminate\Database\ConnectionInterface;

/**
 * Builds fof/sitemap's output in-process (the default `run` mode keeps it in
 * memory) and reads the URLs back, so tests can assert on exactly what a
 * crawler would be handed.
 */
trait BuildsSitemap
{
    /**
     * Every `<loc>` in every set of a fresh build, sorted.
     *
     * @return list<string>
     */
    protected function buildSitemapUrls(): array
    {
        $container = $this->app()->getContainer();

        /** @var DeployInterface $deploy */
        $deploy = $container->make(DeployInterface::class);
        $this->assertInstanceOf(Memory::class, $deploy, 'These tests expect fof/sitemap\'s default run (in-memory) mode.');

        $sets = $container->make(Generator::class)->loop();

        $urls = [];

        foreach (array_keys($sets) as $index) {
            $urls = array_merge($urls, $this->locsIn((string) $deploy->getSet($index)));
        }

        sort($urls);

        return $urls;
    }

    /**
     * The number of SQL queries one full build runs.
     */
    protected function countSitemapBuildQueries(): int
    {
        $container = $this->app()->getContainer();
        $generator = $container->make(Generator::class);

        /** @var ConnectionInterface&\Illuminate\Database\Connection $db */
        $db = $container->make(ConnectionInterface::class);

        $db->flushQueryLog();
        $db->enableQueryLog();

        $generator->loop();

        $count = count($db->getQueryLog());

        $db->disableQueryLog();
        $db->flushQueryLog();

        return $count;
    }

    /**
     * The `<loc>` values of a sitemap set, or of a sitemap index.
     *
     * @return list<string>
     */
    protected function locsIn(string $xml): array
    {
        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml), 'Sitemap XML should be well-formed');

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('sm', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        $locs = [];

        foreach ($xpath->query('//sm:loc') as $node) {
            $locs[] = $node->textContent;
        }

        return $locs;
    }

    /**
     * Paths (no scheme/host) of the given absolute URLs, for readable asserts.
     *
     * @param list<string> $urls
     *
     * @return list<string>
     */
    protected function pathsOf(array $urls): array
    {
        return array_map(fn (string $url) => (string) parse_url($url, PHP_URL_PATH), $urls);
    }
}
