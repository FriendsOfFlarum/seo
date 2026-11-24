<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Tests\integration\mcp;

use Flarum\Mcp\Testing\McpTestCase;
use PHPUnit\Framework\Attributes\Test;

class SeoMetaDescriptionTest extends McpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-tags', 'fof-seo');
    }

    #[Test]
    public function describe_api_explains_seo_meta(): void
    {
        $types = array_column(
            $this->toolOutput($this->callTool('describe_api', [], $this->bearer($this->developerToken(1))))['types'],
            null,
            'type'
        );

        $this->assertStringContainsString('SEO', $types['seoMeta']['description'] ?? '');
        $this->assertSame('fof-seo', $types['seoMeta']['extension'] ?? null);
    }
}
