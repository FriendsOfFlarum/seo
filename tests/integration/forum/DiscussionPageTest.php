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
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Tags\Tag;
use FoF\Seo\Tests\integration\ForumHtmlTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * What crawlers see on a discussion page (`GET /d/{id}-{slug}`).
 *
 * Special attention here to UGC that could be abused (script-injection
 * attempts in titles, quote characters, very long bodies, etc.) — these
 * are the fields that end up in crawler-visible meta tags and JSON-LD.
 */
class DiscussionPageTest extends ForumHtmlTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-seo');

        $this->setting('forum_title', 'Example Forum');
    }

    #[Test]
    public function discussion_page_emits_og_type_article(): void
    {
        $this->seedDiscussion(title: 'How to bake bread', slug: 'bake-bread');

        $html = $this->fetchForumHtml('/d/1-bake-bread');

        $this->assertSame('article', $this->findMetaByProperty($html, 'og:type'));
    }

    /**
     * With "crawl all posts" enabled and tags present but fof/best-answer NOT
     * installed, DiscussionPage defers and the best-answer driver's fallback is
     * what must still emit DiscussionForumPosting.
     */
    #[Test]
    public function forum_posting_emitted_when_best_answer_absent_but_crawler_enabled(): void
    {
        $this->extension('flarum-tags');
        $this->setting('seo_post_crawler', '1');

        $now = Carbon::parse('2025-01-01 00:00:00');

        $this->prepareDatabase([
            Tag::class => [
                ['id' => 1, 'name' => 'General', 'slug' => 'general', 'description' => null, 'color' => '#000', 'position' => 0, 'is_restricted' => false, 'is_hidden' => false],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'A plain topic', 'slug' => 'plain-topic', 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1, 'created_at' => $now, 'last_posted_at' => $now],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Body.</p></t>', 'created_at' => $now],
            ],
            'discussion_tag' => [['discussion_id' => 1, 'tag_id' => 1]],
        ]);

        $html = $this->fetchForumHtml('/d/1-plain-topic');

        $this->assertNotNull($this->findSchemaEntry($html, 'DiscussionForumPosting'));
        $this->assertNull($this->findSchemaEntry($html, 'QAPage'));
    }

    /**
     * Optional fof/discussion-language integration: a discussion's own language
     * drives the schema.org inLanguage, overriding the viewer's locale.
     */
    #[Test]
    public function discussion_in_language_reflects_its_assigned_language_when_enabled(): void
    {
        $this->extension('flarum-tags', 'fof-discussion-language');

        $now = Carbon::parse('2025-01-01 00:00:00');

        $this->prepareDatabase([
            'discussion_languages' => [
                ['id' => 1, 'code' => 'de'],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'Hallo Welt', 'slug' => 'hallo-welt', 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1, 'language_id' => 1, 'created_at' => $now, 'last_posted_at' => $now],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Hallo.</p></t>', 'created_at' => $now],
            ],
        ]);

        $fp = $this->findSchemaEntry($this->fetchForumHtml('/d/1-hallo-welt'), 'DiscussionForumPosting');

        // Default locale in tests is 'en'; the discussion is tagged 'de'.
        $this->assertSame('de', $fp['inLanguage'] ?? null);
    }

    /**
     * Optional fof/discussion-views integration: expose the view count as a
     * schema.org ViewAction interaction counter.
     */
    #[Test]
    public function discussion_forum_posting_includes_view_count_when_discussion_views_enabled(): void
    {
        $this->extension('fof-discussion-views');

        $now = Carbon::parse('2025-01-01 00:00:00');

        $this->prepareDatabase([
            Discussion::class => [
                ['id' => 1, 'title' => 'Popular topic', 'slug' => 'popular', 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1, 'view_count' => 1234, 'created_at' => $now, 'last_posted_at' => $now],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Body.</p></t>', 'created_at' => $now],
            ],
        ]);

        $fp = $this->findSchemaEntry($this->fetchForumHtml('/d/1-popular'), 'DiscussionForumPosting');

        $view = null;
        foreach ((array) ($fp['interactionStatistic'] ?? []) as $stat) {
            if (($stat['interactionType'] ?? null) === 'https://schema.org/ViewAction') {
                $view = $stat;
            }
        }

        $this->assertNotNull($view, 'Expected a ViewAction InteractionCounter when fof/discussion-views is enabled.');
        $this->assertSame(1234, $view['userInteractionCount'] ?? null);
    }

    #[Test]
    public function discussion_forum_posting_includes_headline_text_and_comment_stats(): void
    {
        $now = Carbon::parse('2025-01-01 00:00:00');

        $this->prepareDatabase([
            Discussion::class => [
                ['id' => 1, 'title' => 'How to bake bread', 'slug' => 'bake-bread', 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 3, 'created_at' => $now, 'last_posted_at' => $now],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>You need flour and water.</p></t>', 'created_at' => $now],
            ],
        ]);

        $fp = $this->findSchemaEntry($this->fetchForumHtml('/d/1-bake-bread'), 'DiscussionForumPosting');

        $this->assertNotNull($fp);
        $this->assertSame('How to bake bread', $fp['headline'] ?? null);
        $this->assertStringContainsString('You need flour and water.', $fp['text'] ?? '');

        // comment_count includes the opening post, so replies = comment_count - 1.
        $this->assertSame(2, $fp['commentCount'] ?? null);

        $comment = null;
        foreach ((array) ($fp['interactionStatistic'] ?? []) as $stat) {
            if (($stat['interactionType'] ?? null) === 'https://schema.org/CommentAction') {
                $comment = $stat;
            }
        }
        $this->assertNotNull($comment, 'Expected a CommentAction InteractionCounter.');
        $this->assertSame('InteractionCounter', $comment['@type'] ?? null);
        $this->assertSame(2, $comment['userInteractionCount'] ?? null);
    }

    #[Test]
    public function discussion_title_becomes_og_title_and_twitter_title(): void
    {
        $this->seedDiscussion(title: 'How to bake bread', slug: 'bake-bread');

        $html = $this->fetchForumHtml('/d/1-bake-bread');

        $this->assertSame('How to bake bread', $this->findMetaByProperty($html, 'og:title'));
        $this->assertSame('How to bake bread', $this->findMetaByName($html, 'twitter:title'));
    }

    #[Test]
    public function discussion_page_sets_canonical_url_to_the_discussion_slug(): void
    {
        $this->seedDiscussion(title: 'How to bake bread', slug: 'bake-bread');

        $html = $this->fetchForumHtml('/d/1-bake-bread');

        // The og:url (set by PageListener) should include the slug so that
        // shares from any URL variant converge on the canonical form.
        $this->assertSame(
            'http://localhost/d/1-bake-bread',
            $this->findMetaByProperty($html, 'og:url')
        );
    }

    #[Test]
    public function discussion_page_emits_article_published_time(): void
    {
        $publishedAt = Carbon::parse('2025-06-01 12:34:56');

        $this->seedDiscussion(title: 'How to bake bread', slug: 'bake-bread', createdAt: $publishedAt);

        $html = $this->fetchForumHtml('/d/1-bake-bread');

        $this->assertSame(
            $publishedAt->format('c'),
            $this->findMetaByName($html, 'article:published_time')
        );
    }

    /**
     * Critical defence-in-depth check: a discussion title crafted to break
     * out of the meta content attribute must be escaped. The raw `<script>`
     * must not appear anywhere; only the entity-encoded form is acceptable.
     */
    #[Test]
    public function discussion_title_with_script_tag_is_html_escaped_in_meta(): void
    {
        $this->seedDiscussion(
            title: 'Clickbait <script>alert(1)</script> here',
            slug: 'clickbait',
        );

        $html = $this->fetchForumHtml('/d/1-clickbait');

        // The raw injection attempt should not appear executable anywhere.
        $this->assertStringNotContainsString(
            '<script>alert(1)</script>',
            $html,
            'Raw script tag from discussion title leaked into rendered HTML.'
        );

        // After entity decoding the meta content should round-trip to the original title.
        $this->assertSame(
            'Clickbait <script>alert(1)</script> here',
            $this->findMetaByProperty($html, 'og:title')
        );
        $this->assertSame(
            'Clickbait <script>alert(1)</script> here',
            $this->findMetaByName($html, 'twitter:title')
        );
    }

    #[Test]
    public function discussion_title_with_quote_characters_is_attribute_safe(): void
    {
        $this->seedDiscussion(
            title: 'Curly "quotes" \'apostrophes\' & ampersands',
            slug: 'punctuation',
        );

        $html = $this->fetchForumHtml('/d/1-punctuation');

        // The meta tag must parse as well-formed HTML — i.e. the double-quote
        // in the title must be entity-encoded so it doesn't terminate the attribute.
        $this->assertMatchesRegularExpression(
            '/<meta\s+property="og:title"\s+content="[^"]*"/',
            $html,
            'og:title meta tag appears malformed around the UGC title.'
        );

        $this->assertSame(
            'Curly "quotes" \'apostrophes\' & ampersands',
            $this->findMetaByProperty($html, 'og:title')
        );
    }

    #[Test]
    public function discussion_page_emits_schema_org_discussion_forum_posting(): void
    {
        $this->seedDiscussion(title: 'How to bake bread', slug: 'bake-bread');

        $html = $this->fetchForumHtml('/d/1-bake-bread');

        $entry = $this->findSchemaEntry($html, 'DiscussionForumPosting');

        $this->assertNotNull($entry, 'Expected a DiscussionForumPosting JSON-LD entry.');
        $this->assertSame('http://localhost/d/1-bake-bread', $entry['url'] ?? null);
        $this->assertSame('Person', $entry['author']['@type'] ?? null);
        $this->assertNotEmpty($entry['datePublished'] ?? null);
    }

    /**
     * Even under UGC abuse the JSON-LD block must remain valid JSON — any
     * unescaped double-quote in a title would break the parser and crash
     * structured data consumers.
     */
    #[Test]
    public function schema_json_ld_remains_valid_json_with_hostile_ugc(): void
    {
        $this->seedDiscussion(
            title: 'Title with "quote" and <script>alert(1)</script> and \ backslash',
            slug: 'hostile',
        );

        $html = $this->fetchForumHtml('/d/1-hostile');

        $entries = $this->findSchemaJsonLd($html);

        $this->assertIsArray($entries, 'JSON-LD block failed to parse as JSON.');
        $this->assertNotEmpty($entries);

        // The decoded value, round-tripped, should exactly match the input title.
        $posting = $this->findSchemaEntry($html, 'DiscussionForumPosting');
        $this->assertNotNull($posting);
    }

    #[Test]
    public function non_existent_discussion_does_not_crash_the_seo_extension(): void
    {
        // Flarum returns 404 for unknown discussions. We just need to confirm
        // our extension's driver handles the ModelNotFoundException cleanly
        // rather than leaking a 500.
        $response = $this->send($this->request('GET', '/d/999-missing'));

        $this->assertSame(404, $response->getStatusCode());
    }

    /**
     * A discussion with `robotsNoindex` on its SeoMeta row must emit a
     * `noindex` robots meta tag so crawlers skip it.
     */
    #[Test]
    public function discussion_with_noindex_seo_meta_emits_noindex_robots_tag(): void
    {
        $this->seedDiscussion(title: 'Hidden from crawlers', slug: 'hidden', metaOverrides: [
            'robots_noindex'  => 1,
            'robots_nofollow' => 0,
        ]);

        $html = $this->fetchForumHtml('/d/1-hidden');

        $robots = $this->findMetaByName($html, 'robots');
        $this->assertStringContainsString('noindex', $robots ?? '');
        $this->assertStringContainsString('follow', $robots ?? '');
    }

    /**
     * A discussion overridden with a custom SeoMeta title should surface that
     * title in the crawler-visible tags, overriding the discussion title.
     */
    #[Test]
    public function custom_seo_meta_title_overrides_discussion_title_for_crawlers(): void
    {
        $this->seedDiscussion(title: 'Internal name', slug: 'thing', metaOverrides: [
            'auto_update_data' => 0,
            'title'            => 'Crawler-facing title',
            'description'      => 'Crawler-facing description.',
        ]);

        $html = $this->fetchForumHtml('/d/1-thing');

        $this->assertSame('Crawler-facing title', $this->findMetaByProperty($html, 'og:title'));
        $this->assertSame('Crawler-facing title', $this->findMetaByName($html, 'twitter:title'));
        $this->assertSame('Crawler-facing description.', $this->findMetaByName($html, 'description'));
        $this->assertSame('Crawler-facing description.', $this->findMetaByProperty($html, 'og:description'));
    }

    /**
     * A per-discussion social image (set on its SeoMeta) is used for the
     * og:image / twitter:image, instead of the forum-wide default.
     *
     * Regression lock for GH #30 (social image meta tag per post).
     */
    #[Test]
    public function discussion_uses_its_own_social_image_for_crawlers(): void
    {
        $this->seedDiscussion(title: 'Has an image', slug: 'has-image', metaOverrides: [
            'auto_update_data'        => 0,
            'open_graph_image'        => 'https://example.com/custom-social.png',
            'open_graph_image_source' => 'custom',
        ]);

        $html = $this->fetchForumHtml('/d/1-has-image');

        $this->assertSame('https://example.com/custom-social.png', $this->findMetaByProperty($html, 'og:image'));
        $this->assertSame('https://example.com/custom-social.png', $this->findMetaByName($html, 'twitter:image'));
    }

    /**
     * A discussion with no custom SeoMeta still gets a description generated
     * from its first post, not the forum-wide description.
     *
     * Regression lock for GH #114 (meta-description not generated).
     */
    #[Test]
    public function discussion_description_is_generated_from_its_first_post(): void
    {
        $this->setting('forum_description', 'The forum-wide description.');

        $now = Carbon::parse('2025-01-01 00:00:00');
        $this->prepareDatabase([
            Discussion::class => [
                ['id' => 1, 'title' => 'Generated', 'slug' => 'generated', 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1, 'created_at' => $now, 'last_posted_at' => $now],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>A unique opening sentence for this thread.</p></t>', 'created_at' => $now],
            ],
        ]);

        $description = $this->findMetaByName($this->fetchForumHtml('/d/1-generated'), 'description');

        $this->assertSame('A unique opening sentence for this thread.', $description);
        $this->assertNotSame('The forum-wide description.', $description);
    }

    /**
     * @param array<string, mixed> $metaOverrides Column overrides for the auto-created seo_meta row.
     */
    private function seedDiscussion(
        string $title,
        string $slug,
        ?Carbon $createdAt = null,
        array $metaOverrides = [],
    ): void {
        $createdAt ??= Carbon::parse('2025-01-01 00:00:00');

        $db = [
            'discussions' => [
                [
                    'id'            => 1,
                    'title'         => $title,
                    'slug'          => $slug,
                    'user_id'       => 1,
                    'created_at'    => $createdAt,
                    'comment_count' => 1,
                ],
            ],
            'posts' => [
                [
                    'id'            => 1,
                    'discussion_id' => 1,
                    'user_id'       => 1,
                    'type'          => 'comment',
                    'content'       => '<t><p>Opening post body.</p></t>',
                    'created_at'    => $createdAt,
                ],
            ],
        ];

        if ($metaOverrides !== []) {
            $db['seo_meta'] = [
                array_merge([
                    'id'               => 1,
                    'object_type'      => 'discussions',
                    'object_id'        => 1,
                    'auto_update_data' => 1,
                    'title'            => null,
                    'description'      => null,
                    'created_at'       => $createdAt,
                    'updated_at'       => $createdAt,
                ], $metaOverrides),
            ];
        }

        $this->prepareDatabase($db);
    }
}
