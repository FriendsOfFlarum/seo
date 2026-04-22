<?php

namespace FoF\Seo\Page;

use Carbon\Carbon;
use FoF\Pages\PageRepository;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use FoF\Seo\Page\PageDriverInterface;
use FoF\Seo\SeoMeta\SeoMeta;
use FoF\Seo\SeoProperties;

class PageExtensionPage implements PageDriverInterface
{
    protected TranslatorInterface $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
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
            $page = resolve(PageRepository::class)->findOrFail($pageId);
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
            ->setUrl('/p/' . $page->getAttribute('id') . '-' . $page->getAttribute('slug'))

            // Canonical url
            ->setCanonicalUrl('/p/' . $page->getAttribute('id'))

            ->generateTagsFromMetaData($seoMeta);
    }
}
