<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Tests\integration\forum;

use Flarum\Extend;
use FoF\Seo\Event\PreparingPageMeta;
use FoF\Seo\Tests\integration\ForumHtmlTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Third-party extensions must be able to intercept and modify the prepared
 * SEO metadata (language, description, arbitrary schema.org properties) via the
 * PreparingPageMeta event.
 */
class PageMetaExtensibilityTest extends ForumHtmlTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-seo');

        $this->setting('forum_title', 'Example Forum');
        $this->setting('forum_description', 'An example forum.');

        // A listener that overrides the language and description, and adds a
        // custom schema.org property.
        $this->extend(
            (new Extend\Event())->listen(PreparingPageMeta::class, function (PreparingPageMeta $event) {
                $event->properties
                    ->setSchemaJson('inLanguage', 'de')
                    ->setSchemaJson('isAccessibleForFree', true)
                    ->setDescription('Overridden by a third-party listener.');
            })
        );
    }

    #[Test]
    public function listener_can_override_language_on_the_schema(): void
    {
        $webPage = $this->findSchemaEntry($this->fetchForumHtml('/'), 'WebPage');

        $this->assertSame('de', $webPage['inLanguage'] ?? null);
    }

    #[Test]
    public function listener_can_add_arbitrary_schema_properties(): void
    {
        $webPage = $this->findSchemaEntry($this->fetchForumHtml('/'), 'WebPage');

        $this->assertTrue($webPage['isAccessibleForFree'] ?? null);
    }

    #[Test]
    public function listener_can_override_content(): void
    {
        $html = $this->fetchForumHtml('/');

        $this->assertSame('Overridden by a third-party listener.', $this->findMetaByName($html, 'description'));
        $this->assertSame('Overridden by a third-party listener.', $this->findMetaByProperty($html, 'og:description'));
    }
}
