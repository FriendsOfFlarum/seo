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
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;

/**
 * Tests the `canConfigureSeo` attribute that this extension appends to
 * `/api/forum` responses via AttachForumSerializerAttributes.
 *
 * This attribute is how the JS frontend decides whether to surface the
 * "Configure SEO" controls on a discussion.
 */
class ForumAttributesTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    private const SEO_MANAGER_GROUP_ID = 10;
    private const TRUSTED_USER_ID = 3;

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-seo');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
                [
                    'id' => self::TRUSTED_USER_ID,
                    'username' => 'trusted',
                    'password' => '$2y$10$LO59tiT7uggl6Oe23o/O6.utnF6ipngYjvMvaxo1TciKqBttDNKim',
                    'email' => 'trusted@example.com',
                    'is_email_confirmed' => 1,
                ],
            ],
            'groups' => [
                [
                    'id' => self::SEO_MANAGER_GROUP_ID,
                    'name_singular' => 'SEO Manager',
                    'name_plural' => 'SEO Managers',
                    'is_hidden' => 0,
                ],
            ],
            'group_user' => [
                ['user_id' => self::TRUSTED_USER_ID, 'group_id' => self::SEO_MANAGER_GROUP_ID],
            ],
            'group_permission' => [
                ['group_id' => self::SEO_MANAGER_GROUP_ID, 'permission' => 'fof-seo.canConfigure'],
            ],
        ]);
    }

    /**
     * @return array<string, array{0: ?int, 1: bool}>
     */
    public function canConfigureSeoProvider(): array
    {
        return [
            'admin sees canConfigureSeo true'                => [1, true],
            'permitted non-admin sees canConfigureSeo true'  => [self::TRUSTED_USER_ID, true],
            'regular user sees canConfigureSeo false'        => [2, false],
            'guest sees canConfigureSeo false'               => [null, false],
        ];
    }

    /**
     * @test
     * @dataProvider canConfigureSeoProvider
     */
    public function forum_endpoint_exposes_can_configure_seo_per_actor(?int $authenticatedAs, bool $expected): void
    {
        $options = [];

        if ($authenticatedAs !== null) {
            $options['authenticatedAs'] = $authenticatedAs;
        }

        $response = $this->send($this->request('GET', '/api/', $options));

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true);

        $this->assertArrayHasKey('canConfigureSeo', $data['data']['attributes']);
        $this->assertSame($expected, $data['data']['attributes']['canConfigureSeo']);
    }

    /**
     * @test
     */
    public function discussion_api_response_can_include_seo_meta_relationship(): void
    {
        $now = Carbon::now();

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
            ],
            'discussions' => [
                ['id' => 1, 'title' => 'Hello', 'slug' => 'hello', 'user_id' => 1, 'created_at' => $now, 'comment_count' => 1],
            ],
            'posts' => [
                ['id' => 1, 'discussion_id' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Body.</p></t>', 'created_at' => $now],
            ],
            'seo_meta' => [
                [
                    'id' => 1,
                    'object_type' => 'discussions',
                    'object_id' => 1,
                    'auto_update_data' => 1,
                    'title' => 'Hello world',
                    'description' => 'Hello world description.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
        ]);

        $response = $this->send(
            $this->request('GET', '/api/discussions/1', [
                'authenticatedAs' => 1,
            ])
        );

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true);

        // The ShowDiscussionController is extended with addInclude('seoMeta')
        // so the relationship should be present in `included`.
        $this->assertArrayHasKey('relationships', $data['data']);
        $this->assertArrayHasKey('seoMeta', $data['data']['relationships']);

        $seoMeta = null;
        foreach ($data['included'] ?? [] as $entry) {
            if (($entry['type'] ?? null) === 'seoMeta') {
                $seoMeta = $entry;
                break;
            }
        }

        $this->assertNotNull($seoMeta, 'Expected seoMeta to appear in `included`.');
        $this->assertSame('Hello world', $seoMeta['attributes']['title']);
        $this->assertSame('Hello world description.', $seoMeta['attributes']['description']);
    }
}
