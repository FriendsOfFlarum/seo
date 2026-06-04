<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Extend;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use FoF\Seo\SeoMeta\SeoMeta;

class SeoMetaTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    /**
     * ID for the test-only "SEO Manager" group, which we grant
     * `fof-seo.canConfigure` to in the test database. A trusted non-admin
     * user is placed in this group so permission checks can be exercised
     * without falling back on the admin bypass.
     */
    private const SEO_MANAGER_GROUP_ID = 10;

    /**
     * Non-admin user granted the SEO permission via the custom group.
     */
    private const TRUSTED_USER_ID = 3;

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-seo');

        // Exempt the SEO endpoints from CSRF so unauthenticated test requests
        // reach the permission check rather than being rejected at the CSRF
        // middleware. This mirrors the need to test the permission gate itself
        // — in production a CSRF token would be present for legitimate calls.
        $this->extend(
            (new Extend\Csrf())
                ->exemptRoute('seo_meta.update')
                ->exemptRoute('seo.socialmedia.upload')
                ->exemptRoute('seo.socialmedia.delete')
        );

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
                [
                    'id'                 => self::TRUSTED_USER_ID,
                    'username'           => 'trusted',
                    'password'           => '$2y$10$LO59tiT7uggl6Oe23o/O6.utnF6ipngYjvMvaxo1TciKqBttDNKim', // "too-obscure"
                    'email'              => 'trusted@machine.local',
                    'is_email_confirmed' => 1,
                ],
            ],
            'groups' => [
                [
                    'id'            => self::SEO_MANAGER_GROUP_ID,
                    'name_singular' => 'SEO Manager',
                    'name_plural'   => 'SEO Managers',
                    'is_hidden'     => 0,
                ],
            ],
            'group_user' => [
                ['user_id' => self::TRUSTED_USER_ID, 'group_id' => self::SEO_MANAGER_GROUP_ID],
            ],
            'group_permission' => [
                ['group_id' => self::SEO_MANAGER_GROUP_ID, 'permission' => 'fof-seo.canConfigure'],
            ],
            'discussions' => [
                ['id' => 1, 'title' => 'Test discussion', 'user_id' => 1, 'created_at' => Carbon::now(), 'comment_count' => 1],
            ],
            'posts' => [
                ['id' => 1, 'discussion_id' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<p>body</p>', 'created_at' => Carbon::now()],
            ],
            'seo_meta' => [
                [
                    'id'               => 1,
                    'object_type'      => 'discussions',
                    'object_id'        => 1,
                    'auto_update_data' => 1,
                    'title'            => 'Existing title',
                    'description'      => 'Existing description',
                    'keywords'         => null,
                    'created_at'       => Carbon::now(),
                    'updated_at'       => Carbon::now(),
                ],
            ],
        ]);
    }

    /**
     * Actors covered by the provider below:
     *
     *   - 'admin'      — administrator (ID 1); bypasses permission checks.
     *   - 'permitted'  — regular user in the SEO Manager group; has
     *                    `fof-seo.canConfigure` via the custom group.
     *   - 'regular'    — normal user (ID 2); authenticated but without the
     *                    SEO permission.
     *   - 'guest'      — unauthenticated request.
     *
     * @return array<string, array{0: ?int, 1: bool}>
     */
    public function seoMetaAccessProvider(): array
    {
        return [
            // [authenticatedAs, expectedAllowed]
            'admin bypasses permission checks'     => [1, true],
            'permitted non-admin user via group'   => [self::TRUSTED_USER_ID, true],
            'regular user without permission'      => [2, false],
            'unauthenticated guest'                => [null, false],
        ];
    }

    /**
     * @test
     *
     * @dataProvider seoMetaAccessProvider
     */
    public function listing_seo_meta_respects_permission(?int $authenticatedAs, bool $allowed): void
    {
        $response = $this->send($this->buildRequest('GET', '/api/seo_meta', $authenticatedAs));

        if ($allowed) {
            $this->assertSame(200, $response->getStatusCode());

            $data = json_decode($response->getBody()->getContents(), true);
            $this->assertCount(1, $data['data']);
            $this->assertSame('seoMeta', $data['data'][0]['type']);
            $this->assertSame('Existing title', $data['data'][0]['attributes']['title']);
        } else {
            $this->assertContains($response->getStatusCode(), [401, 403]);
        }
    }

    /**
     * @test
     *
     * @dataProvider seoMetaAccessProvider
     */
    public function showing_seo_meta_by_id_respects_permission(?int $authenticatedAs, bool $allowed): void
    {
        $response = $this->send($this->buildRequest('GET', '/api/seo_meta/1', $authenticatedAs));

        if ($allowed) {
            $this->assertSame(200, $response->getStatusCode());

            $data = json_decode($response->getBody()->getContents(), true);
            $this->assertSame('Existing title', $data['data']['attributes']['title']);
            $this->assertSame('discussions', $data['data']['attributes']['objectType']);
            $this->assertSame(1, $data['data']['attributes']['objectId']);
        } else {
            $this->assertContains($response->getStatusCode(), [401, 403]);
        }
    }

    /**
     * @test
     *
     * @dataProvider seoMetaAccessProvider
     */
    public function showing_seo_meta_by_object_type_respects_permission(?int $authenticatedAs, bool $allowed): void
    {
        $response = $this->send($this->buildRequest('GET', '/api/seo_meta/discussions-1', $authenticatedAs));

        if ($allowed) {
            $this->assertSame(200, $response->getStatusCode());

            $data = json_decode($response->getBody()->getContents(), true);
            $this->assertSame('Existing title', $data['data']['attributes']['title']);
        } else {
            $this->assertContains($response->getStatusCode(), [401, 403]);
        }
    }

    /**
     * @test
     *
     * @dataProvider seoMetaAccessProvider
     */
    public function showing_seo_meta_by_object_type_auto_creates_row_when_permitted(?int $authenticatedAs, bool $allowed): void
    {
        $response = $this->send($this->buildRequest('GET', '/api/seo_meta/discussions-999', $authenticatedAs));

        if ($allowed) {
            $this->assertSame(200, $response->getStatusCode());

            $this->assertNotNull(SeoMeta::where([
                'object_type' => 'discussions',
                'object_id'   => 999,
            ])->first());
        } else {
            $this->assertContains($response->getStatusCode(), [401, 403]);

            // The auto-create side effect must not fire for unauthorised actors.
            $this->assertNull(SeoMeta::where([
                'object_type' => 'discussions',
                'object_id'   => 999,
            ])->first());
        }
    }

    /**
     * @test
     *
     * @dataProvider seoMetaAccessProvider
     */
    public function updating_seo_meta_respects_permission(?int $authenticatedAs, bool $allowed): void
    {
        $response = $this->send(
            $this->buildRequest('PATCH', '/api/seo_meta/1', $authenticatedAs, [
                'data' => [
                    'attributes' => [
                        'autoUpdateData' => false,
                        'title'          => 'Updated title',
                        'description'    => 'Updated description',
                        'robotsNoindex'  => true,
                        'robotsNofollow' => false,
                    ],
                ],
            ])
        );

        $meta = SeoMeta::findOrFail(1);

        if ($allowed) {
            $this->assertSame(200, $response->getStatusCode());

            $this->assertFalse((bool) $meta->auto_update_data);
            $this->assertSame('Updated title', $meta->title);
            $this->assertSame('Updated description', $meta->description);
            $this->assertTrue((bool) $meta->robots_noindex);
            $this->assertFalse((bool) $meta->robots_nofollow);
        } else {
            $this->assertContains($response->getStatusCode(), [401, 403]);

            // Nothing should have changed in the database.
            $this->assertSame('Existing title', $meta->title);
            $this->assertSame('Existing description', $meta->description);
        }
    }

    /**
     * @param array<string, mixed>|null $json
     */
    private function buildRequest(string $method, string $path, ?int $authenticatedAs, ?array $json = null)
    {
        $options = [];

        if ($authenticatedAs !== null) {
            $options['authenticatedAs'] = $authenticatedAs;
        }

        if ($json !== null) {
            $options['json'] = $json;
        }

        return $this->request($method, $path, $options);
    }
}
