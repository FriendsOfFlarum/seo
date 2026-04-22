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

use Carbon\Carbon;
use FoF\Seo\Tests\integration\ForumHtmlTestCase;

/**
 * What crawlers see on a user profile page (`GET /u/{username}`).
 */
class ProfilePageTest extends ForumHtmlTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-seo');

        $this->prepareDatabase([
            'users' => [
                [
                    'id'                 => 2,
                    'username'           => 'victorinox',
                    'email'              => 'v@example.com',
                    'password'           => '$2y$10$LO59tiT7uggl6Oe23o/O6.utnF6ipngYjvMvaxo1TciKqBttDNKim',
                    'joined_at'          => Carbon::parse('2024-03-15'),
                    'discussion_count'   => 3,
                    'comment_count'      => 12,
                    'is_email_confirmed' => 1,
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function profile_page_sets_og_type_to_profile(): void
    {
        $html = $this->fetchForumHtml('/u/victorinox');

        $this->assertSame('profile', $this->findMetaByProperty($html, 'og:type'));
    }

    /**
     * @test
     */
    public function profile_page_emits_profile_username_meta_tag(): void
    {
        $html = $this->fetchForumHtml('/u/victorinox');

        $this->assertSame('victorinox', $this->findMetaByProperty($html, 'profile:username'));
    }

    /**
     * @test
     */
    public function profile_page_canonical_url_points_to_profile(): void
    {
        $html = $this->fetchForumHtml('/u/victorinox');

        $this->assertSame(
            'http://localhost/u/victorinox',
            $this->findMetaByProperty($html, 'og:url')
        );
    }

    /**
     * @test
     */
    public function profile_page_emits_schema_org_profile_page_entry(): void
    {
        $html = $this->fetchForumHtml('/u/victorinox');

        $entry = $this->findSchemaEntry($html, 'ProfilePage');

        $this->assertNotNull($entry, 'Expected a ProfilePage JSON-LD entry.');

        $this->assertSame('Person', $entry['mainEntity']['@type'] ?? null);
        $this->assertSame('victorinox', $entry['mainEntity']['name'] ?? null);
        $this->assertSame('victorinox', $entry['name'] ?? null);
        $this->assertSame(12, $entry['commentCount'] ?? null);
        $this->assertSame('http://localhost/u/victorinox', $entry['url'] ?? null);

        // dateCreated must be ISO-8601, matching the user's joined_at.
        $this->assertNotEmpty($entry['dateCreated'] ?? null);
        $this->assertSame('2024-03-15T00:00:00+00:00', $entry['dateCreated']);
    }

    /**
     * Username that contains HTML-dangerous characters must never escape the
     * meta attribute. Flarum itself doesn't accept such usernames at signup,
     * but a malicious username created via DB manipulation or migration must
     * still not break crawler-visible output.
     *
     * @test
     */
    public function username_with_html_characters_is_escaped_in_meta_tags(): void
    {
        // Flarum's username regex would reject this on creation, so insert directly.
        $this->prepareDatabase([
            'users' => [
                [
                    'id'                 => 3,
                    'username'           => 'ab"cd',
                    'email'              => 'hostile@example.com',
                    'password'           => '$2y$10$LO59tiT7uggl6Oe23o/O6.utnF6ipngYjvMvaxo1TciKqBttDNKim',
                    'joined_at'          => Carbon::now(),
                    'is_email_confirmed' => 1,
                ],
            ],
        ]);

        $response = $this->send($this->request('GET', '/u/3'));

        // Route may 404 because of username validation at the router level —
        // either way we just need to be sure we don't 500 and we don't leak
        // a raw unescaped quote into the profile:username meta tag.
        if ($response->getStatusCode() === 200) {
            $html = (string) $response->getBody();

            // If emitted, the content must use escaped form.
            $username = $this->findMetaByProperty($html, 'profile:username');
            if ($username !== null) {
                $this->assertSame('ab"cd', $username);
            }
        } else {
            $this->assertContains($response->getStatusCode(), [400, 404]);
        }
    }

    /**
     * @test
     */
    public function missing_profile_does_not_crash_the_seo_extension(): void
    {
        $response = $this->send($this->request('GET', '/u/nobody'));

        // Flarum renders 404 for missing users; we just need to be sure no 500.
        $this->assertContains($response->getStatusCode(), [200, 404]);
    }
}
