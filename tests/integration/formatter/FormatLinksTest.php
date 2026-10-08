<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Tests\integration\formatter;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Extend;
use Flarum\Post\Post;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;
use s9e\TextFormatter\Configurator;

/**
 * Integration test for the FormatLinks render callback.
 *
 * We exercise the full formatter pipeline by POSTing a reply through the
 * API: the JSON:API response's `contentHtml` attribute is rendered
 * server-side through the same pipeline that runs in production, including
 * this extension's `Extend\Formatter->render(FormatLinks::class)`.
 */
class FormatLinksTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-seo');

        // Bypass the post-creation rate limit so multiple tests can each
        // create a reply in the same setUp window.
        $this->extend(
            (new Extend\ThrottleApi())->remove('postTimeout')
        );

        $this->prepareDatabase([
            User::class       => [$this->normalUser()],
            Discussion::class => [
                ['id' => 1, 'title' => 'Test', 'slug' => 'test', 'user_id' => 2, 'created_at' => Carbon::now(), 'comment_count' => 1],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Opener.</p></t>', 'created_at' => Carbon::now()],
            ],
        ]);
    }

    #[Test]
    public function external_link_in_post_content_gets_nofollow_and_new_tab(): void
    {
        $html = $this->postReplyAndGetContentHtml('Please visit https://external.test/path for more info.');

        $this->assertStringContainsString('nofollow', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="ugc noopener', $html);
    }

    #[Test]
    public function link_to_the_forum_itself_does_not_get_nofollow(): void
    {
        // The internal/forum URL under test is http://localhost, which the
        // TextFormatter accepts as a valid URL. Links to it should not be
        // nofollow.
        $html = $this->postReplyAndGetContentHtml('See http://localhost/d/1 for details.');

        $this->assertStringNotContainsString('nofollow', $html);
        $this->assertStringContainsString('target="_self"', $html);
    }

    /**
     * A link written as a path is a link to this forum.
     *
     * `[text](/d/123)` is the commonest way to link between discussions, and
     * it was reaching readers as `rel="ugc noopener nofollow" target="_blank"`
     * — the forum refusing to follow its own links, and sending them off to a
     * new tab.
     */
    #[Test]
    public function relative_link_in_post_content_is_treated_as_internal(): void
    {
        $this->extension('fof-seo', 'flarum-markdown');

        $html = $this->postReplyAndGetContentHtml('See [FriendsOfFlarum OAuth](/d/25182) for details.');

        $this->assertStringNotContainsString('nofollow', $html);
        $this->assertStringNotContainsString('ugc', $html);
        $this->assertStringContainsString('target="_self"', $html);
    }

    /**
     * A protocol-relative link is absolute, and is not ours. Read as a path it
     * would look like one of the forum's own.
     */
    #[Test]
    public function protocol_relative_link_in_post_content_stays_external(): void
    {
        $this->extension('fof-seo', 'flarum-markdown');

        $html = $this->postReplyAndGetContentHtml('See [evil](//evil.test/d/1) for details.');

        $this->assertStringContainsString('nofollow', $html);
    }

    #[Test]
    public function domain_on_dofollow_list_does_not_get_nofollow(): void
    {
        $this->setting('seo_dofollow_domains', json_encode(['trusted.test']));

        $html = $this->postReplyAndGetContentHtml('Check https://trusted.test/page out.');

        // GH #113 — do-follow links must pass ranking signals: no nofollow and
        // no ugc (which Google also treats as nofollow).
        $this->assertStringNotContainsString('nofollow', $html);
        $this->assertStringNotContainsString('ugc', $html);
        $this->assertStringContainsString('target="_blank"', $html);
    }

    /**
     * GH — this extension used to append a template normalizer that rewrote
     * every <a> in every template to rel="{@rel}" target="{@target}". Those
     * attributes are only ever populated for core's URL tag, so any other
     * extension's link template rendered rel="" target="" and its links opened
     * in the same tab. Core's Formatter::configureExternalLinks() covers the
     * URL tag properly, so the normalizer is gone; other templates must be
     * left exactly as their extension wrote them.
     */
    #[Test]
    public function another_extensions_link_template_keeps_its_own_rel_and_target(): void
    {
        $this->extend(
            (new Extend\Formatter())->configure(function (Configurator $config) {
                $config->BBCodes->addCustom(
                    '[newtab]{TEXT}[/newtab]',
                    '<a href="https://third-party.test" target="_blank" rel="ugc noopener noreferrer">{TEXT}</a>'
                );
            })
        );

        $html = $this->postReplyAndGetContentHtml('[newtab]Third party link[/newtab]');

        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="ugc noopener noreferrer"', $html);
        $this->assertStringNotContainsString('target=""', $html);
        $this->assertStringNotContainsString('rel=""', $html);
    }

    /**
     * Rendering hostile text via TextFormatter must not allow a URL to break
     * out of its rel/target attribute and inject markup. Even a URL with a
     * closing quote + script tag must come through safely encoded.
     */
    #[Test]
    public function hostile_url_does_not_break_out_of_attributes(): void
    {
        $html = $this->postReplyAndGetContentHtml('Evil: https://evil.test/"><script>alert(1)</script>');

        // The TextFormatter URL matcher should either URL-encode or reject
        // the quote/script portion. Either way, a live <script> tag must
        // never appear in the rendered HTML.
        $this->assertStringNotContainsString('"><script>alert(1)</script>', $html);
    }

    /**
     * POST a reply to discussion 1 and extract `data.attributes.contentHtml`.
     */
    private function postReplyAndGetContentHtml(string $content): string
    {
        $response = $this->send(
            $this->request('POST', '/api/posts', [
                'authenticatedAs' => 2,
                'json'            => [
                    'data' => [
                        'attributes'    => ['content' => $content],
                        'relationships' => [
                            'discussion' => ['data' => ['type' => 'discussions', 'id' => '1']],
                        ],
                    ],
                ],
            ])
        );

        $this->assertSame(201, $response->getStatusCode(), 'Creating the reply failed: '.$response->getBody());

        $body = json_decode((string) $response->getBody(), true);

        $html = $body['data']['attributes']['contentHtml'] ?? null;
        $this->assertIsString($html, 'Response is missing contentHtml.');

        return $html;
    }
}
