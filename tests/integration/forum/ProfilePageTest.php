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
use Flarum\User\User;
use FoF\Seo\Tests\integration\ForumHtmlTestCase;
use PHPUnit\Framework\Attributes\Test;

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
            User::class => [
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

    #[Test]
    public function profile_page_sets_og_type_to_profile(): void
    {
        $html = $this->fetchForumHtml('/u/victorinox');

        $this->assertSame('profile', $this->findMetaByProperty($html, 'og:type'));
    }

    #[Test]
    public function profile_page_emits_profile_username_meta_tag(): void
    {
        $html = $this->fetchForumHtml('/u/victorinox');

        $this->assertSame('victorinox', $this->findMetaByProperty($html, 'profile:username'));
    }

    #[Test]
    public function profile_page_canonical_url_points_to_profile(): void
    {
        $html = $this->fetchForumHtml('/u/victorinox');

        $this->assertSame(
            'http://localhost/u/victorinox',
            $this->findMetaByProperty($html, 'og:url')
        );
    }

    #[Test]
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
     * Google's ProfilePage guidance puts the creator's identity and activity
     * stats on the `mainEntity` Person.
     */
    #[Test]
    public function profile_person_carries_identity_and_interaction_stats(): void
    {
        $person = $this->findSchemaEntry($this->fetchForumHtml('/u/victorinox'), 'ProfilePage')['mainEntity'] ?? [];

        $this->assertSame('Person', $person['@type'] ?? null);
        $this->assertSame('victorinox', $person['alternateName'] ?? null); // username
        $this->assertSame(2, $person['identifier'] ?? null);               // user id

        $write = null;
        foreach ((array) ($person['agentInteractionStatistic'] ?? []) as $stat) {
            if (($stat['interactionType'] ?? null) === 'https://schema.org/WriteAction') {
                $write = $stat;
            }
        }
        $this->assertNotNull($write, 'Expected a WriteAction agentInteractionStatistic for posts.');
        $this->assertSame('InteractionCounter', $write['@type'] ?? null);
        $this->assertSame(12, $write['userInteractionCount'] ?? null);     // comment_count
    }

    /**
     * Username that contains HTML-dangerous characters must never escape the
     * meta attribute. Flarum itself doesn't accept such usernames at signup,
     * but a malicious username created via DB manipulation or migration must
     * still not break crawler-visible output.
     */
    #[Test]
    public function username_with_html_characters_is_escaped_in_meta_tags(): void
    {
        // Flarum's username regex would reject this on creation, so insert directly.
        $this->prepareDatabase([
            User::class => [
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
     * Profiles inherit the site-wide `index, follow` robots default unless the
     * admin opts to deindex them (GH #62).
     */
    #[Test]
    public function profile_pages_are_indexable_by_default(): void
    {
        $html = $this->fetchForumHtml('/u/victorinox');

        $this->assertSame('index, follow', $this->findMetaByName($html, 'robots'));
    }

    /**
     * When `seo_noindex_profiles` is enabled, profile pages emit
     * `noindex, follow` so thin profile pages drop out of the index while
     * crawlers still follow the links on them (GH #62).
     */
    #[Test]
    public function profile_pages_are_noindexed_when_the_setting_is_enabled(): void
    {
        $this->setting('seo_noindex_profiles', '1');

        $html = $this->fetchForumHtml('/u/victorinox');

        $this->assertSame('noindex, follow', $this->findMetaByName($html, 'robots'));
    }

    /**
     * Explicitly disabling the setting keeps profiles indexable.
     */
    #[Test]
    public function disabling_the_noindex_setting_keeps_profiles_indexable(): void
    {
        $this->setting('seo_noindex_profiles', '0');

        $html = $this->fetchForumHtml('/u/victorinox');

        $this->assertSame('index, follow', $this->findMetaByName($html, 'robots'));
    }

    #[Test]
    public function missing_profile_does_not_crash_the_seo_extension(): void
    {
        $response = $this->send($this->request('GET', '/u/nobody'));

        // Flarum renders 404 for missing users; we just need to be sure no 500.
        $this->assertContains($response->getStatusCode(), [200, 404]);
    }

    /**
     * With the id-with-display-name user slug driver the profile URL is
     * `/u/{id}-{name}`. The driver must resolve the user through Flarum's
     * SlugManager — not assume the route param is a username — otherwise the
     * page emits no SEO at all (the bug this guards).
     */
    #[Test]
    public function profile_resolves_under_the_id_with_display_name_slug_driver(): void
    {
        $this->setting('slug_driver_Flarum\\User\\User', 'id_with_display_name');

        $html = $this->fetchForumHtml('/u/2-victorinox');

        $entry = $this->findSchemaEntry($html, 'ProfilePage');
        $this->assertNotNull($entry, 'Expected a ProfilePage entry under the id-with-name slug driver.');
        $this->assertSame('victorinox', $entry['mainEntity']['name'] ?? null);

        // URLs use the driver's slug, not the bare username.
        $this->assertSame('http://localhost/u/2-victorinox', $this->findMetaByProperty($html, 'og:url'));
        $this->assertSame('http://localhost/u/2-victorinox', $entry['mainEntity']['url'] ?? null);
    }

    /**
     * The id slug driver uses a bare numeric `/u/{id}`.
     */
    #[Test]
    public function profile_resolves_under_the_id_slug_driver(): void
    {
        $this->setting('slug_driver_Flarum\\User\\User', 'id');

        $html = $this->fetchForumHtml('/u/2');

        $entry = $this->findSchemaEntry($html, 'ProfilePage');
        $this->assertNotNull($entry, 'Expected a ProfilePage entry under the id slug driver.');
        $this->assertSame('victorinox', $entry['mainEntity']['name'] ?? null);
        $this->assertSame('http://localhost/u/2', $this->findMetaByProperty($html, 'og:url'));
    }
}
