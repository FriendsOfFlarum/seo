<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Tests\unit\Formatter;

use Flarum\Foundation\Application;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\unit\TestCase;
use FoF\Seo\Formatter\FormatLinks;
use Mockery as m;
use s9e\TextFormatter\Renderer;

class FormatLinksTest extends TestCase
{
    public function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    /**
     * The do-follow list always contains the forum's own domain, so links
     * pointing to it should not receive a nofollow.
     */
    public function test_internal_link_does_not_get_nofollow(): void
    {
        $formatter = $this->makeFormatter(forumUrl: 'https://forum.example.com', doFollow: []);

        $xml = '<t><URL url="https://forum.example.com/d/1">link</URL></t>';

        $result = $formatter->__invoke(m::mock(Renderer::class), null, $xml);

        // Do-follow links (the forum's own domain) must pass ranking signals:
        // no `nofollow`, and no `ugc` (which Google also treats as nofollow).
        $this->assertStringContainsString('rel="noopener"', $result);
        $this->assertStringNotContainsString('ugc', $result);
        $this->assertStringNotContainsString('nofollow', $result);
    }

    /**
     * External links on a domain not in the do-follow list receive `ugc` and
     * `nofollow` (untrusted user-generated content).
     */
    public function test_external_link_gets_nofollow(): void
    {
        $formatter = $this->makeFormatter(forumUrl: 'https://forum.example.com', doFollow: []);

        $xml = '<t><URL url="https://external.test/path">link</URL></t>';

        $result = $formatter->__invoke(m::mock(Renderer::class), null, $xml);

        $this->assertStringContainsString('rel="ugc noopener nofollow"', $result);
    }

    /**
     * Domains on the do-follow list must pass ranking signals — no `nofollow`
     * and no `ugc` (GH #113).
     */
    public function test_dofollow_listed_external_link_does_not_get_nofollow(): void
    {
        $formatter = $this->makeFormatter(
            forumUrl: 'https://forum.example.com',
            doFollow: ['trusted.test'],
        );

        $xml = '<t><URL url="https://trusted.test/page">link</URL></t>';

        $result = $formatter->__invoke(m::mock(Renderer::class), null, $xml);

        $this->assertStringContainsString('rel="noopener"', $result);
        $this->assertStringNotContainsString('ugc', $result);
        $this->assertStringNotContainsString('nofollow', $result);
    }

    /**
     * External links open in a new tab.
     */
    public function test_external_link_opens_in_new_tab(): void
    {
        $formatter = $this->makeFormatter(forumUrl: 'https://forum.example.com', doFollow: []);

        $xml = '<t><URL url="https://external.test/path">link</URL></t>';

        $result = $formatter->__invoke(m::mock(Renderer::class), null, $xml);

        $this->assertStringContainsString('target="_blank"', $result);
    }

    /**
     * Internal links stay in the same tab.
     */
    public function test_internal_link_stays_in_same_tab(): void
    {
        $formatter = $this->makeFormatter(forumUrl: 'https://forum.example.com', doFollow: []);

        $xml = '<t><URL url="https://forum.example.com/d/1">link</URL></t>';

        $result = $formatter->__invoke(m::mock(Renderer::class), null, $xml);

        $this->assertStringContainsString('target="_self"', $result);
    }

    /**
     * Links to a different subdomain of the same second-level domain are
     * treated as external (nofollow).
     *
     * This documents current behaviour: `urlToDomain()` stores the full host
     * of the forum URL (e.g. "forum.example.com") as `internalDomain` during
     * construction (skipping the subdomain strip because `internalDomain` is
     * still empty at that point). Later calls to `urlToDomain()` DO strip
     * subdomains, so "www.example.com" becomes "example.com" — which does not
     * match "forum.example.com", so it falls through to nofollow.
     */
    public function test_different_subdomain_of_same_second_level_domain_is_treated_as_external(): void
    {
        $formatter = $this->makeFormatter(forumUrl: 'https://forum.example.com', doFollow: []);

        $xml = '<t><URL url="https://www.example.com/page">link</URL></t>';

        $result = $formatter->__invoke(m::mock(Renderer::class), null, $xml);

        $this->assertStringContainsString('nofollow', $result);
    }

    /**
     * Explicit target attributes on the source XML should be preserved, not overwritten.
     */
    public function test_existing_target_attribute_is_preserved(): void
    {
        $formatter = $this->makeFormatter(forumUrl: 'https://forum.example.com', doFollow: []);

        $xml = '<t><URL url="https://external.test/" target="_custom">link</URL></t>';

        $result = $formatter->__invoke(m::mock(Renderer::class), null, $xml);

        $this->assertStringContainsString('target="_custom"', $result);
        $this->assertStringNotContainsString('target="_blank"', $result);
    }

    /**
     * When the do-follow settings string is empty JSON/null, the list falls
     * back to an empty array (plus the internal domain).
     */
    public function test_empty_dofollow_setting_does_not_error(): void
    {
        $formatter = $this->makeFormatter(forumUrl: 'https://forum.example.com', doFollow: null);

        $this->assertEquals(['forum.example.com'], $formatter->getDoFollowList() + ['forum.example.com']);
    }

    /**
     * @param list<string>|null $doFollow null simulates an unset setting
     */
    private function makeFormatter(string $forumUrl, ?array $doFollow): FormatLinks
    {
        $app = m::mock(Application::class);
        $app->shouldReceive('url')->andReturn($forumUrl);

        $settings = m::mock(SettingsRepositoryInterface::class);
        $settings->shouldReceive('get')
            ->with('seo_dofollow_domains', '')
            ->andReturn($doFollow === null ? '' : json_encode($doFollow));

        return new FormatLinks($app, $settings);
    }
}
