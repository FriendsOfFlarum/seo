<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Formatter;

use Flarum\Foundation\Application;
use Flarum\Settings\SettingsRepositoryInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use s9e\TextFormatter\Renderer;
use s9e\TextFormatter\Utils;

class FormatLinks
{
    protected Application $app;

    protected SettingsRepositoryInterface $settings;

    protected string $internalDomain = '';

    /**
     * @var array<int, string> List of allowed domains to follow
     */
    protected array $doFollowList = [];

    public function __construct(Application $app, SettingsRepositoryInterface $settings)
    {
        $this->app = $app;
        $this->settings = $settings;

        $this->internalDomain = $this->urlToDomain($this->app->url());
        $this->doFollowList = array_merge($this->getDoFollowList(), [$this->internalDomain]);
    }

    /**
     * @param Renderer     $renderer
     * @param mixed        $context
     * @param string       $xml
     * @param Request|null $request
     *
     * @return string
     */
    public function __invoke(Renderer $renderer, mixed $context, string $xml, ?Request $request = null): string
    {
        return Utils::replaceAttributes($xml, 'URL', function (array $attributes): array {
            $domain = $this->urlToDomain($attributes['url']);

            // Do we add a nofollow?
            $attributes['rel'] = 'ugc noopener'.($this->addNofollow($domain) ? ' nofollow' : '');

            // Open link in new tab
            if (!isset($attributes['target'])) {
                $attributes['target'] = $this->openInNewTab($domain) ? '_blank' : '_self';
            }

            return $attributes;
        });
    }

    /**
     * Do we need to add a nofollow to this link?
     */
    private function addNofollow(string $domain): bool
    {
        return !in_array($domain, $this->doFollowList);
    }

    /**
     * Is the link an internal link?
     */
    private function openInNewTab(string $domain): bool
    {
        return $this->internalDomain != $domain;
    }

    /**
     * Load the do-follow list.
     *
     * @return array<int, string>
     */
    public function getDoFollowList(): array
    {
        return json_decode($this->settings->get('seo_dofollow_domains', ''), true) ?? [];
    }

    /**
     * Get domain (and strip subdomains, if any).
     */
    private function urlToDomain(string $url): string
    {
        $parsed = parse_url($url);

        if (!is_array($parsed) || !isset($parsed['host'])) {
            return '';
        }

        $domain = $parsed['host'];

        // Strip subdomains if Flarum is not installed in a subdomain
        if (!empty($this->internalDomain) && $this->isSubdomain($domain) && $domain !== $this->internalDomain) {
            $domain = implode('.', array_slice(explode('.', $domain), -2, 2, true));
        }

        return $domain;
    }

    /**
     * Check if this domain is a subdomain.
     */
    private function isSubdomain(string $domain): bool
    {
        return substr_count($domain, '.') > 1;
    }
}
