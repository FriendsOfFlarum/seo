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

use Flarum\Testing\integration\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * Base class for tests that send a forum GET request and assert on the
 * rendered HTML — specifically the `<meta>` tags, `<title>`, canonical URL
 * link, and JSON-LD schema.org script block injected by the SEO extension.
 *
 * Crawlers parse this HTML; these tests pin down what Google/Bing/etc. see.
 */
abstract class ForumHtmlTestCase extends TestCase
{
    protected function fetchForumHtml(string $path, ?int $authenticatedAs = null): string
    {
        $options = [];

        if ($authenticatedAs !== null) {
            $options['authenticatedAs'] = $authenticatedAs;
        }

        $response = $this->send($this->request('GET', $path, $options));

        $this->assertSame(200, $response->getStatusCode(), "Expected 200 from {$path}");
        $this->assertStringStartsWith(
            'text/html',
            $response->getHeaderLine('Content-Type'),
            "Expected an HTML response from {$path}"
        );

        return (string) $response->getBody();
    }

    /**
     * Find the `content` attribute of a `<meta name="{$name}">` tag in HTML.
     * Returns null when the tag isn't present.
     */
    protected function findMetaByName(string $html, string $name): ?string
    {
        return $this->findAttributeByPattern(
            $html,
            '/<meta\s+name="'.preg_quote($name, '/').'"\s+content="([^"]*)"/i'
        );
    }

    /**
     * Find the `content` attribute of a `<meta property="{$property}">` tag.
     */
    protected function findMetaByProperty(string $html, string $property): ?string
    {
        return $this->findAttributeByPattern(
            $html,
            '/<meta\s+property="'.preg_quote($property, '/').'"\s+content="([^"]*)"/i'
        );
    }

    /**
     * Extract the first `<title>...</title>` contents from HTML.
     */
    protected function findTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches) === 1) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
        }

        return null;
    }

    /**
     * Find the `href` of the `<link rel="canonical">` tag.
     */
    protected function findCanonicalUrl(string $html): ?string
    {
        return $this->findAttributeByPattern(
            $html,
            '/<link\s+rel="canonical"\s+href="([^"]*)"/i'
        );
    }

    /**
     * Parse the `<script type="application/ld+json">...</script>` block and
     * return the list of schema.org nodes it contains. The extension emits a
     * single root object with an `@graph` list of nodes (a bare array root
     * crashes some consumers, e.g. Safari's structured-data parser), so unwrap
     * `@graph` to the node list. A bare array is still accepted for resilience.
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function findSchemaJsonLd(string $html): ?array
    {
        if (preg_match('/<script\s+type="application\/ld\+json"[^>]*>(.*?)<\/script>/is', $html, $matches) !== 1) {
            return null;
        }

        $decoded = json_decode($matches[1], true);

        if (!is_array($decoded)) {
            return null;
        }

        // Single root object with `@graph` => the nodes live under `@graph`.
        if (isset($decoded['@graph']) && is_array($decoded['@graph'])) {
            return $decoded['@graph'];
        }

        return $decoded;
    }

    /**
     * Locate a schema.org entry in the JSON-LD block by its `@var`.
     *
     * @return array<string, mixed>|null
     */
    protected function findSchemaEntry(ResponseInterface|string $htmlOrResponse, string $type): ?array
    {
        $html = $htmlOrResponse instanceof ResponseInterface
            ? (string) $htmlOrResponse->getBody()
            : $htmlOrResponse;

        $entries = $this->findSchemaJsonLd($html) ?? [];

        foreach ($entries as $entry) {
            if (is_array($entry) && ($entry['@type'] ?? null) === $type) {
                return $entry;
            }
        }

        return null;
    }

    private function findAttributeByPattern(string $html, string $pattern): ?string
    {
        if (preg_match($pattern, $html, $matches) === 1) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
        }

        return null;
    }
}
