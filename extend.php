<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo;

use Flarum\Api\Endpoint;
use Flarum\Api\Resource\DiscussionResource;
use Flarum\Api\Resource\ForumResource;
use Flarum\Api\Schema;
use Flarum\Database\AbstractModel;
use Flarum\Discussion\Discussion as FlarumDiscussion;
use Flarum\Extend;
use FoF\Seo\Api\AttachForumResourceFields;
use FoF\Seo\Extend\SEO;
use FoF\Seo\Formatter\FormatLinks;
use FoF\Seo\Listeners\PageListener;
use FoF\Seo\Page as SeoPage;
use FoF\Seo\SeoMeta\SeoMeta;

return [
    (new Extend\Frontend('forum'))
      ->content(PageListener::class)
      ->js(__DIR__.'/js/dist/forum.js')
      ->css(__DIR__.'/less/Forum.less'),

    (new Extend\Frontend('admin'))
      ->js(__DIR__.'/js/dist/admin.js')
      ->css(__DIR__.'/less/Admin.less')
      ->jsDirectory(__DIR__.'/js/dist/admin'),

    (new Extend\Frontend('common'))
      ->jsDirectory(__DIR__.'/js/dist/common'),

    (new Extend\Routes('api'))
      ->post('/seo_social_media_image', 'seo.socialmedia.upload', Api\Controllers\UploadSocialMediaImageController::class)
      ->delete('/seo_social_media_image', 'seo.socialmedia.delete', Api\Controllers\DeleteSocialMediaImageController::class)
      ->get('/seo_meta', 'seo_meta.overview', Api\Controllers\ListSeoMetaController::class)
      // `{id}` accepts either a numeric primary key or an `{object_type}-{id}`
      // pair (e.g. `discussions-123`); the resource's find() handles both.
      ->get('/seo_meta/{id}', 'seo_meta.get', Api\Controllers\ShowSeoMetaController::class)
      ->patch('/seo_meta/{id}', 'seo_meta.update', Api\Controllers\UpdateSeoMetaController::class),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Console())
      ->command(Console\FixSvgSocialImagesCommand::class),

    // Cap how many replies are emitted as schema.org Comment nodes when the
    // post crawler is enabled. Rendering each reply is the dominant cost on
    // page load, so bound it; Google only needs the comments shown on the page.
    (new Extend\Settings())
      ->default('seo_post_crawler_limit', 100),

    (new Extend\Formatter())
      ->render(FormatLinks::class)
      ->configure(ConfigureLinks::class),

    // Add SEO Meta model relation
    (new Extend\Model(FlarumDiscussion::class))
      ->relationship('seoMeta', function (AbstractModel $model) {
          return $model->hasOne(SeoMeta::class, 'object_id', 'id')
            ->where('object_type', 'discussions');
      }),

    new Extend\ApiResource(Api\Resource\SeoMetaResource::class),

    // Expose the discussion's SEO meta as an includable relationship, included
    // by default on the single-discussion endpoint (as in 1.x).
    (new Extend\ApiResource(DiscussionResource::class))
      ->fields(fn () => [
          Schema\Relationship\ToOne::make('seoMeta')
            ->type('seoMeta')
            ->includable(),
      ])
      ->endpoint(Endpoint\Show::class, fn (Endpoint\Show $endpoint) => $endpoint->addDefaultInclude(['seoMeta'])),

    // Expose the forum-level SEO admin attributes.
    (new Extend\ApiResource(ForumResource::class))
      ->fields(AttachForumResourceFields::class),

    (new SEO())
      ->addExtender('index', SeoPage\IndexPage::class)
      ->addExtender('profile', SeoPage\ProfilePage::class)
      ->addExtender('discussion', SeoPage\DiscussionPage::class),

    (new Extend\Event())
      ->subscribe(Subscribers\DiscussionSubscriber::class)
      ->subscribe(Subscribers\PostSubscriber::class),

    (new Extend\Conditional())
      ->whenExtensionEnabled('flarum-tags', fn () => [
          (new Extend\Event())
            ->subscribe(Subscribers\TagSubscriber::class),

          (new SEO())
            ->addExtender('tag', SeoPage\TagPage::class)
            ->addExtender('tags', SeoPage\TagsPage::class),
      ])
      ->whenExtensionEnabled('fof-best-answer', fn () => [
          (new SEO())
            ->addExtender('discussion_best_answer', SeoPage\DiscussionBestAnswerPage::class),
      ])
      ->whenExtensionEnabled('fof-pages', fn () => [
          (new SEO())
            ->addExtender('page_extension', SeoPage\PageExtensionPage::class),
      ])
      ->whenExtensionEnabled('fof-user-directory', fn () => [
          (new SEO())
            ->addExtender('fof_user_directory', SeoPage\UserDirectoryPage::class),
      ]),
];
