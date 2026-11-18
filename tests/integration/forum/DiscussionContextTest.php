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
use Illuminate\Database\Events\QueryExecuted;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class DiscussionContextTest extends ForumHtmlTestCase
{
    public static function cases(): array
    {
        return [
            'plain off'                 => [false, false, true, true, true, false, 6],
            'page 3'                    => [true, false, true, true, true, false, 8, true, false, false, '/d/1-topic?page=3'],
            'near'                      => [true, false, true, true, true, false, 8, true, false, false, '/d/1-topic/2'],
            'plain on'                  => [true, false, true, true, true, false, 8],
            'qa off'                    => [false, true, true, true, true, false, 8],
            'qa on'                     => [true, true, true, true, true, false, 8],
            'unanswered off'            => [false, true, true, true, true, false, 6, false],
            'unanswered on'             => [true, true, true, true, true, false, 8, false],
            'without tags'              => [false, false, false, true, false, false, 4],
            'without likes'             => [false, false, true, false, true, false, 6],
            'without best answer'       => [false, false, true, true, false, false, 6],
            'child qa'                  => [false, true, true, true, true, false, 8, true, true],
            'replacement off'           => [false, true, true, true, true, false, 2, false, false, true],
            'replacement on'            => [true, true, true, true, true, false, 4, false, false, true],
            'preloaded op'              => [false, false, true, true, true, false, 5, true, false, false, '/d/1-topic', true],
            'preloaded qa op'           => [false, true, true, true, true, false, 7, true, false, false, '/d/1-topic', true],
            'preloaded gamification op' => [false, false, true, true, true, true, 6, true, false, false, '/d/1-topic', true],
            'gamification'              => [false, false, true, true, true, true, 6],
        ];
    }

    #[Test]
    #[DataProvider('cases')]
    public function discussion_page_statements_and_output(bool $crawl, bool $qna, bool $tags, bool $likes, bool $best, bool $gamification, int $expected, bool $answer = true, bool $child = false, bool $replacement = false, string $path = '/d/1-topic', bool $preload = false): void
    {
        $this->extension('fof-seo');
        foreach (['flarum-tags' => $tags, 'flarum-likes' => $likes, 'fof-best-answer' => $best, 'fof-gamification' => $gamification] as $id => $enabled) {
            if ($enabled) {
                $this->extension($id);
            }
        }
        if ($replacement) {
            $this->extend((new \FoF\Seo\Extend\SEO())->addExtender('discussion', ContextReplacementDriver::class));
        }
        if ($preload) {
            $this->extend((new \Flarum\Extend\Frontend('forum'))->content(function ($document, $request) {
                $discussion = $this->app()->getContainer()->make(\FoF\Seo\Page\DiscussionContext::class)->resolve($request);
                $post = $discussion->firstPost;
                $this->assertNotNull($post);
                $this->assertArrayNotHasKey('likes_count', $post->getAttributes());
                $this->assertArrayNotHasKey('upvotes_count', $post->getAttributes());
            }, 1));
        }
        $this->setting('forum_title', 'Example Forum');
        $this->setting('seo_post_crawler', $crawl ? '1' : '0');
        $now = Carbon::parse('2025-01-01 00:00:00');
        $discussion = ['id' => 1, 'title' => 'Topic', 'slug' => 'topic', 'user_id' => 1, 'first_post_id' => 1, 'last_post_id' => 2, 'comment_count' => 2, 'created_at' => $now, 'last_posted_at' => $now];
        if ($best) {
            $discussion['best_answer_post_id'] = $answer ? 2 : null;
        }
        $data = [Discussion::class => [$discussion], Post::class => [
            ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Opening body.</p></t>', 'created_at' => $now],
            ['id' => 2, 'discussion_id' => 1, 'number' => 2, 'user_id' => 1, 'type' => 'comment', 'content' => $answer ? '<t><p>Reply body.</p></t>' : '<t></t>', 'created_at' => $now],
        ], 'seo_meta' => [['id' => 1, 'object_type' => 'discussions', 'object_id' => 1, 'title' => 'Topic', 'description' => 'Description', 'created_at' => $now, 'updated_at' => $now]]];
        if ($tags) {
            $tag = ['id' => 1, 'name' => 'General', 'slug' => 'general', 'position' => 0];
            if ($best) {
                $tag['is_qna'] = $qna;
            }
            $data[Tag::class] = [$tag];
            $data['discussion_tag'] = [['discussion_id' => 1, 'tag_id' => 1]];
        }
        if ($child) {
            $data[Tag::class][] = ['id' => 2, 'name' => 'Child', 'slug' => 'child', 'parent_id' => 1];
            $data['discussion_tag'] = [['discussion_id' => 1, 'tag_id' => 2]];
        }
        if ($likes) {
            $data['post_likes'] = [['post_id' => 1, 'user_id' => 1, 'created_at' => $now]];
        }
        if ($gamification) {
            $data['post_votes'] = [['post_id' => 1, 'user_id' => 1, 'value' => 1], ['post_id' => 2, 'user_id' => 1, 'value' => -1]];
        }
        $this->prepareDatabase($data);
        // Attribute statements by their live call stack, rather than SQL shape:
        // the API's seoMeta linkage queries are outside these page-driver pins.
        $queries = [];
        $this->app()->getContainer()->make('events')->listen(QueryExecuted::class, function (QueryExecuted $event) use (&$queries) {
            foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
                if (($frame['class'] ?? '') === \FoF\Seo\Listeners\PageListener::class && ($frame['function'] ?? '') === 'determine') {
                    $queries[] = $event->sql;
                    break;
                }
            }
        });
        parse_str(parse_url($path, PHP_URL_QUERY) ?? '', $query);
        $response = $this->send($this->request('GET', $path)->withQueryParams($query));
        $html = (string) $response->getBody();
        $this->assertSame(200, $response->getStatusCode(), $html);
        preg_match_all('~<meta\b[^>]*>|<title>.*?</title>|<link rel="canonical"[^>]*>|<script type="application/ld\+json"[^>]*>.*?</script>~s', $html, $matches);
        $output = implode("\n", $matches[0]);
        $key = implode('-', array_map(intval(...), [$crawl, $qna, $tags, $likes, $best, $gamification, $answer, $child, $replacement]));
        // Raw crawler-visible tags captured from 9bd3a05 before changing production code.
        // The key follows the boolean arguments above; no auto-update on test runs.
        $key .= $path === '/d/1-topic?page=3' ? '-page3' : '';
        $fixture = __DIR__.'/fixtures/discussion-context-'.$key.'.html';
        $this->assertSame(file_get_contents($fixture), $output);
        $grammar = $this->database()->getQueryGrammar();
        $discussionSelect = 'select * from '.$grammar->wrapTable('discussions');
        $tagId = $grammar->wrap('tags.id').' = ?';
        $this->assertCount($preload ? 0 : 1, array_filter($queries, fn ($sql) => str_starts_with($sql, $discussionSelect)), 'Discussion resolved once by SEO');
        $this->assertCount(0, array_filter($queries, fn ($sql) => str_contains($sql, $tagId) && !str_contains($sql, 'discussion_tag')), 'No parent lazy loads');
        $this->assertCount($expected, $queries, implode("\n", $queries));
    }
}

class ContextReplacementDriver implements \FoF\Seo\Page\PageDriverInterface
{
    public function extensionDependencies(): array
    {
        return [];
    }

    public function handleRoutes(): array
    {
        return ['discussion'];
    }

    public function handle(\Psr\Http\Message\ServerRequestInterface $request, \FoF\Seo\SeoProperties $properties): void
    {
        $properties->setSchemaJson('@type', 'Article')->setDescription('Replacement driver');
    }
}
