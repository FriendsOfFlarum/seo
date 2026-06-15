<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Tests\unit\Support;

use Flarum\Http\SlugManager;
use Flarum\Http\UrlGenerator;
use FoF\Seo\Support\AuthorSchema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class AuthorSchemaTest extends TestCase
{
    private function authorSchema(): AuthorSchema
    {
        // The deleted-user branch never builds a URL, so url/slug collaborators
        // are stubbed but not configured.
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('[deleted]');

        return new AuthorSchema(
            $this->createStub(UrlGenerator::class),
            $this->createStub(SlugManager::class),
            $translator,
        );
    }

    #[Test]
    public function a_deleted_user_yields_a_named_person_with_no_url(): void
    {
        $author = $this->authorSchema()->forUser(null);

        $this->assertSame('Person', $author['@type']);
        $this->assertSame('[deleted]', $author['name']);
        // author.name is required; url is not emitted for a removed account.
        $this->assertArrayNotHasKey('url', $author);
    }

    // The live-user branch (Person with name + profile url) needs a booted app
    // for User::getDisplayNameAttribute(), so it's covered by the integration
    // tests (author_of_a_live_user_carries_a_profile_url and friends).
}
