<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Page;

use Carbon\Carbon;
use FoF\Pages\PageRepository;
use FoF\Seo\SeoMeta\SeoMeta;
use FoF\Seo\SeoProperties;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class PageExtensionPage implements PageDriverInterface
{
    public function __construct(
        protected readonly TranslatorInterface $translator,
        protected readonly PageRepository $pageRepository,
    ) {
    }

    public function extensionDependencies(): array
    {
        return ['fof-pages'];
    }

    public function handleRoutes(): array
    {
        return ['pages.home', 'pages.page'];
    }

    public function handle(
        ServerRequestInterface $request,
        SeoProperties $properties
    ): void {
        $pageId = Arr::get($request->getQueryParams(), 'id');

        try {
            $page = $this->pageRepository->findOrFail($pageId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Do nothing, no model found
            return;
        }

        $content = $page->is_html ? $page->content : $page->contentHtml;

        $seoMeta = SeoMeta::findByModelOrCreate(
            $page,
            // Meta didn't exist yet, create one
            function (SeoMeta $meta) use ($page, $properties, $content) {
                $meta->title = $page->title;

                $meta->created_at = $page->time ?? Carbon::now();

                $meta->updated_at = $page->edit_time;

                // Get Tag description
                $meta->description = $properties->generateDescriptionFromContent(e(strip_tags($content)));
            }
        );

        $properties
            // Add Schema.org metadata: WebPage https://schema.org/WebPage
            ->setSchemaJson('@type', 'WebPage')
            ->setSchemaJson('text', e(strip_tags($content)))

            // Tag URL
            ->setUrl('/p/'.$page->getAttribute('id').'-'.$page->getAttribute('slug'))

            // Canonical url
            ->setCanonicalUrl('/p/'.$page->getAttribute('id'))

            ->generateTagsFromMetaData($seoMeta);
    }
}
