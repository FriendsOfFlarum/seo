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

use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Google can only read a `noindex` on a page it may fetch. While fof/seo is
 * enabled it owns indexability, so fof/sitemap's robots.txt must not block
 * profiles or tags; without fof/seo, fof/sitemap keeps its blocks.
 */
class RobotsIndexabilityTest extends TestCase
{
    private function robotsTxt(): string
    {
        $response = $this->send($this->request('GET', '/robots.txt'));

        $this->assertSame(200, $response->getStatusCode());

        return (string) $response->getBody();
    }

    public static function exclusionSettings(): array
    {
        return [
            'sitemap exclusions on'  => [true],
            'sitemap exclusions off' => [false],
        ];
    }

    #[Test]
    #[DataProvider('exclusionSettings')]
    public function robots_txt_never_blocks_profiles_or_tags_while_fof_seo_is_enabled(bool $exclude): void
    {
        // The order Flarum boots them in: fof/seo lists fof/sitemap as an
        // optional dependency, so core sorts fof/sitemap (and the TagEntry it
        // adds) before fof/seo. flarum/testing boots the order given here.
        $this->extension('flarum-tags', 'fof-sitemap', 'fof-seo');

        $this->setting('fof-sitemap.excludeUsers', $exclude);
        $this->setting('fof-sitemap.excludeTags', $exclude);
        $this->setting('seo_noindex_profiles', '1');
        $this->setting('seo_noindex_tags', json_encode([1]));

        $robots = $this->robotsTxt();

        $this->assertDoesNotMatchRegularExpression('#^Disallow:\s*/u(/|$)#m', $robots);
        $this->assertDoesNotMatchRegularExpression('#^Disallow:\s*/t(/|$)#m', $robots);
        $this->assertDoesNotMatchRegularExpression('#^Disallow:\s*/tags\s*$#m', $robots);

        // fof/sitemap's other entries are untouched.
        $this->assertStringContainsString('Disallow: /admin', $robots);
        $this->assertStringContainsString('Disallow: /api', $robots);
        $this->assertMatchesRegularExpression('#^Sitemap: .*/sitemap\.xml$#m', $robots);
    }

    /**
     * Control: the same settings without fof/seo keep fof/sitemap's blocks,
     * which proves the assertions above can fail.
     */
    #[Test]
    public function robots_txt_keeps_sitemap_blocks_without_fof_seo(): void
    {
        $this->extension('flarum-tags', 'fof-sitemap');

        $this->setting('fof-sitemap.excludeUsers', true);
        $this->setting('fof-sitemap.excludeTags', true);

        $robots = $this->robotsTxt();

        $this->assertMatchesRegularExpression('#^Disallow:\s*/u/$#m', $robots);
        $this->assertMatchesRegularExpression('#^Disallow:\s*/t/$#m', $robots);
        $this->assertMatchesRegularExpression('#^Disallow:\s*/tags$#m', $robots);
    }
}
