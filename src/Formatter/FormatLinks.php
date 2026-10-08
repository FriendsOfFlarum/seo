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
    protected string $internalDomain = '';

    /**
     * @var array<int, string> List of allowed domains to follow
     */
    protected array $doFollowList = [];

    public function __construct(
        protected readonly Application $app,
        protected readonly SettingsRepositoryInterface $settings,
    ) {
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
            $domain = $this->isRelativeToForum($attributes['url'])
                ? $this->internalDomain
                : $this->urlToDomain($attributes['url']);

            // Do-follow domains (the forum itself + the configured allow-list)
            // should pass ranking signals, so they get neither `nofollow` nor
            // `ugc` (Google treats `ugc` as a nofollow hint too). Untrusted
            // user-generated links get both.
            $attributes['rel'] = $this->addNofollow($domain) ? 'ugc noopener nofollow' : 'noopener';

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
     * Is this URL a path on the forum itself?
     *
     * `[text](/d/123)` is how people link between discussions most of the
     * time. There is no host to match against the do-follow list, so without
     * this it read as a foreign site: the forum told search engines not to
     * follow its own links, and opened them in a new tab.
     */
    private function isRelativeToForum(string $url): bool
    {
        // A scheme with no host — `mailto:`, `tel:` — does not address a page
        // on this or any other site.
        if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $url)) {
            return false;
        }

        // `//evil.test/x` has no scheme but is absolute. Read as a path it
        // would look like one of ours, which is how a link off the forum could
        // be dressed up as a do-follow link on it.
        if (str_starts_with($url, '//')) {
            return false;
        }

        // A path-relative link like `d/1` resolves against whichever page it
        // is read on, which is not knowable here.
        return str_starts_with($url, '/');
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
