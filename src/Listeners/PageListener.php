<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Listeners;

use Flarum\Frontend\Document;
use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use FoF\Seo\Event\PreparingPageMeta;
use FoF\Seo\Page\PageManager;
use FoF\Seo\SeoMeta\SeoMeta;
use FoF\Seo\SeoProperties;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;

class PageListener
{
    protected readonly string $applicationUrl;

    protected ?Document $flarumDocument = null;

    private ?string $canonicalUrl = null;

    /**
     * Schema.org LD JSON.
     *
     * @var array<string, mixed>
     */
    protected array $schemaArray = [
        '@context' => 'http://schema.org',
        '@type'    => 'WebPage',
    ];

    /**
     * @var array<string, mixed>
     */
    protected array $schemaBreadcrumb = [];

    /**
     * Meta data with property tags.
     *
     * @var array<string, string>
     */
    protected array $metaProperty = [];

    protected Cloud $assets;

    public function __construct(
        protected readonly SettingsRepositoryInterface $settings,
        UrlGenerator $url,
        protected readonly PageManager $pageManager,
        protected readonly Dispatcher $events,
        Container $container,
    ) {
        $this->applicationUrl = $url->to('forum')->base();
        $this->assets = $container->make('filesystem')->disk('flarum-assets');
    }

    /**
     * Get current Flarum document and current Server Request.
     */
    public function __invoke(Document $flarumDocument, ServerRequestInterface $serverRequestInterface): void
    {
        $this->flarumDocument = $flarumDocument;

        $this->setSiteTags();
        $this->determine($serverRequestInterface);
        $this->finish($serverRequestInterface);
    }

    /**
     * Determine the current page type.
     */
    private function determine(ServerRequestInterface $serverRequest): void
    {
        // Request type
        $routeName = $serverRequest->getAttribute('routeName');

        // Initialize SEO Properties container
        $seoPropertiesExtender = new SeoProperties($this);

        // Handle through drivers
        foreach ($this->pageManager->getExtenders($routeName) as $extender) {
            $extender->handle($serverRequest, $seoPropertiesExtender);
        }
    }

    /**
     * Default site meta tags
     * Available for all webpages.
     */
    private function setSiteTags(): void
    {
        $applicationName = $this->settings->get('forum_title');
        $applicationDescription = $this->settings->get('forum_description');
        $applicationFavicon = $this->settings->get('favicon_path');
        $applicationLogo = $this->settings->get('logo_path');
        $applicationSeoSocialMediaImage = $this->settings->get('seo_social_media_image_path');
        $twitterCardLargeSize = $this->settings->get('seo_twitter_card_size', 'large') === 'large';

        $this
            // Add application name
            ->setMetaTag('application-name', $applicationName)
            ->setMetaPropertyTag('og:site_name', $applicationName)
            ->setMetaPropertyTag('og:type', 'website')

            // Robots, follow please! :)
            ->setMetaTag('robots', 'index, follow')

            // Twitter card
            ->setMetaTag('twitter:card', $twitterCardLargeSize ? 'summary_large_image' : 'summary');

        // Add application information
        $this->setSchemaJson('publisher', [
            '@type'       => 'Organization',
            'name'        => $applicationName,
            'url'         => $this->applicationUrl,
            'description' => $applicationDescription,
            'logo'        => $applicationLogo ? $this->applicationUrl.'/assets/'.$applicationLogo : null,
        ]);

        // Set image
        if ($applicationSeoSocialMediaImage !== null) {
            $this->setImage($this->assets->url($applicationSeoSocialMediaImage));
        }
        // Fallback to the logo
        elseif ($applicationLogo !== null) {
            $this->setImage($this->assets->url($applicationLogo));
        }
        // Fallback to the favicon
        elseif ($applicationFavicon !== null) {
            $this->setImage($this->assets->url($applicationFavicon));
        }
    }

    /**
     * Finish process and output language, meta property tags, canonical urls & Schema.org json.
     */
    public function finish(ServerRequestInterface $serverRequest): void
    {
        // Add language attribute to html tag
        $locale = $serverRequest->getAttribute('locale');
        $this->flarumDocument->language = $locale;

        // Declare the document language on the schema.org entity, unless a page
        // driver has already set its own value.
        if ($locale !== null && !isset($this->schemaArray['inLanguage'])) {
            $this->setSchemaJson('inLanguage', $locale);
        }

        // Let extensions read and modify the prepared metadata before it is written.
        $this->events->dispatch(
            new PreparingPageMeta(new SeoProperties($this), $this->flarumDocument, $serverRequest)
        );

        // Open Graph article dates only belong on article-type pages; emit them
        // from the recorded schema.org dates when this page is an article.
        $this->emitArticleDates();

        // Expose the resolved document language as og:locale (mirrors schema.org
        // inLanguage), unless a driver/listener already set one explicitly.
        $documentLanguage = $this->schemaArray['inLanguage'] ?? $locale;
        if ($documentLanguage !== null && !isset($this->metaProperty['og:locale'])) {
            $this->setMetaPropertyTag('og:locale', $this->normaliseLocale($documentLanguage));
        }

        // Describe the social image for preview cards / accessibility, unless set
        // explicitly. Uses the page's og:title, falling back to the forum name.
        if (isset($this->metaProperty['og:image']) && !isset($this->metaProperty['og:image:alt'])) {
            $imageAlt = $this->metaProperty['og:title'] ?? $this->settings->get('forum_title');

            if ($imageAlt !== null && $imageAlt !== '') {
                $this->setMetaPropertyTag('og:image:alt', $imageAlt);
                $this->setMetaTag('twitter:image:alt', $imageAlt);
            }
        }

        // Write meta property tags
        foreach ($this->metaProperty as $name => $content) {
            $this->flarumDocument->head[] = '<meta property="'.e($name).'" content="'.e($content).'">';
        }

        // Override Flarum default canonical url
        if ($this->canonicalUrl !== null) {
            $this->flarumDocument->canonicalUrl = $this->canonicalUrl;
        }

        // Add schema.org json
        $this->flarumDocument->head[] = $this->writeSchemesOrgJson();
    }

    /**
     * Schema.org json.
     */
    private function writeSchemesOrgJson(): string
    {
        $show = [];
        $show[] = $this->schemaArray;

        if (count($this->schemaBreadcrumb) > 0) {
            $show[] = $this->schemaBreadcrumb;
        }

        $show[] = $this->addSearchBar();

        return '<script type="application/ld+json">'.json_encode($show).'</script>';
    }

    /**
     * Add the potential search bar.
     *
     * @return array<string, mixed>
     */
    private function addSearchBar(): array
    {
        return [
            '@context'        => 'http://schema.org',
            '@type'           => 'WebSite',
            'name'            => $this->settings->get('forum_title'),
            'url'             => $this->applicationUrl.'/',
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => $this->applicationUrl.'/?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    public function setMetaPropertyTag(string $key, string $value): self
    {
        $this->metaProperty[$key] = $value;

        return $this;
    }

    public function setMetaTag(string $key, string $value): self
    {
        $this->flarumDocument->meta[$key] = $value;

        return $this;
    }

    public function setSchemaJson(string $key, mixed $value): self
    {
        $this->schemaArray[$key] = $value;

        return $this;
    }

    /**
     * @param array<int, array<string, mixed>> $tagList
     * @param string                           $listOrderType https://schema.org/ItemListOrderType
     */
    public function setSchemaBreadcrumb(array $tagList = [], string $listOrderType = 'ItemListUnordered'): void
    {
        // Don't add the list, there were no tags
        if (count($tagList) === 0) {
            return;
        }

        $list = [];

        // Loop through all tags
        foreach ($tagList as $index => $tag) {
            $list[] = [
                '@type'    => 'ListItem',
                'position' => ($index + 1),
                'item'     => [
                    '@type' => Arr::get($tag, 'type', 'Thing'),
                    '@id'   => Arr::get($tag, 'url', $this->applicationUrl),
                    'name'  => Arr::get($tag, 'name', 'Unknown'),
                    'url'   => Arr::get($tag, 'url', $this->applicationUrl),
                ],
            ];
        }

        $this->schemaBreadcrumb = [
            '@context'        => 'http://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $list,
            'itemListOrder'   => $listOrderType,
            'numberOfItems'   => count($list),
        ];
    }

    /**
     * Current page URL.
     */
    public function setUrl(string $path = '', bool $prependApplicationUrl = true): self
    {
        if ($prependApplicationUrl) {
            $path = $this->applicationUrl.$path;
        }

        $this->setMetaTag('twitter:url', $path);
        $this->setMetaPropertyTag('og:url', $path);
        $this->setSchemaJson('url', $path);

        return $this;
    }

    /**
     * Set canonical url.
     */
    public function setCanonicalUrl(string $path, bool $prependApplicationUrl = true): self
    {
        if ($prependApplicationUrl) {
            $path = $this->applicationUrl.$path;
        }

        $this->canonicalUrl = $path;

        return $this;
    }

    public function getApplicationPath(string $path): string
    {
        return $this->applicationUrl.$path;
    }

    /**
     * Emit the Open Graph article:published_time / article:modified_time tags
     * from the recorded schema.org dates, but only when the page is an article.
     * On other page types (tag listings, profiles, …) these tags don't apply.
     */
    private function emitArticleDates(): void
    {
        if (($this->metaProperty['og:type'] ?? null) !== 'article') {
            return;
        }

        if (isset($this->schemaArray['datePublished'])) {
            $this->setMetaPropertyTag('article:published_time', $this->schemaArray['datePublished']);
        }

        if (isset($this->schemaArray['dateModified'])) {
            $this->setMetaPropertyTag('article:modified_time', $this->schemaArray['dateModified']);
        }
    }

    /**
     * Normalise a Flarum locale (e.g. "en", "pt-br") to the Open Graph
     * "language_TERRITORY" form (e.g. "en", "pt_BR").
     */
    private function normaliseLocale(string $locale): string
    {
        $parts = preg_split('/[-_]/', $locale, 2);

        return $parts[0].(isset($parts[1]) ? '_'.strtoupper($parts[1]) : '');
    }

    /**
     * Set title.
     */
    public function setTitle(string $title, bool $headline = false): self
    {
        $this
            ->setMetaPropertyTag('og:title', $title)
            ->setMetaTag('twitter:title', $title);

        if ($headline === true) {
            $this->setSchemaJson('headline', $title);
        }

        return $this;
    }

    /**
     * Set description.
     */
    public function setDescription(string $description): self
    {
        $this
            ->setMetaPropertyTag('og:description', $description)
            ->setMetaTag('description', $description)
            ->setMetaTag('twitter:description', $description)
            ->setSchemaJson('description', $description);

        return $this;
    }

    /**
     * Set page keywords.
     *
     * @param string|array<int, string>|null $keywords
     */
    public function setKeywords(string|array|null $keywords): void
    {
        if (!$keywords) {
            return;
        }

        if (is_array($keywords)) {
            $keywords = implode(', ', $keywords);
        }

        $this->setMetaTag('keywords', $keywords);
    }

    /**
     * Get image from content.
     *
     * @param string|null $content
     *
     * @return string|null
     */
    public function getImageFromContent(?string $content = null): ?string
    {
        // Check post content is not empty
        if ($content !== null) {
            // Match http(s) and protocol-relative ("//host/img.png") image URLs.
            // SVG is deliberately excluded: Slack and X reject SVG for og:image
            // (only raster formats render), so an SVG badge would produce a
            // plain unfurl with no preview image (GH #149).
            // The URL body excludes `"` so a non-matching (e.g. SVG) src can't
            // let `.*?` run past the closing quote into the next img tag.
            $pattern = '/(?<=src=")((?:https?:)?\/\/[^"]*?\.)(jpe?g|png|[tg]iff?|webp)(\?[a-zA-Z0-9\_\-\=\&]*)?(?=")/';

            // Use the first raster image from the post for the social og:image.
            if (preg_match_all($pattern, $content, $matches)) {
                $contentImage = $matches[0][0];

                if ($contentImage !== '') {
                    // Normalise protocol-relative URLs so og:image is absolute.
                    return str_starts_with($contentImage, '//') ? 'https:'.$contentImage : $contentImage;
                }
            }
        }

        return null;
    }

    /**
     * Get estimated reading time in seconds.
     */
    public function getEstimatedReadingTime(?string $content = null): int
    {
        $words = str_word_count(strip_tags($content ?? ''));
        $minutes = (int) floor($words / 200);
        $seconds = (int) floor($words % 200 / (200 / 60));

        return ($minutes * 60) + $seconds;
    }

    /**
     * Set published on.
     */
    public function setPublishedOn(\DateTimeInterface|string $published): self
    {
        $date = $published instanceof \DateTimeInterface
            ? $published->format('c')
            : (new \DateTime($published))->format('c');

        // Record the date on the schema.org entity. The matching Open Graph
        // article:published_time tag is emitted in finish(), but only for
        // article-type pages (see emitArticleDates()).
        $this->setSchemaJson('datePublished', $date);

        return $this;
    }

    /**
     * Set updated time
     * Only used when a discussion has newer posts.
     */
    public function setUpdatedOn(\DateTimeInterface|string $updated): self
    {
        $date = $updated instanceof \DateTimeInterface
            ? $updated->format('c')
            : (new \DateTime($updated))->format('c');

        // As with datePublished, the og:article:modified_time tag is emitted in
        // finish() only when og:type is "article".
        $this->setSchemaJson('dateModified', $date);

        return $this;
    }

    /**
     * Set page image.
     */
    public function setImage(string $imagePath): self
    {
        return $this
            ->setMetaPropertyTag('og:image', $imagePath)
            ->setMetaTag('twitter:image', $imagePath)
            ->setSchemaJson('image', $imagePath);
    }

    /**
     * Auto set meta tags and data from received SeoMeta object.
     *
     * @param SeoMeta $seoMeta
     *
     * @return PageListener
     */
    public function generateTagsFromMetaData(SeoMeta $seoMeta): self
    {
        if ($seoMeta->title !== null && $seoMeta->title !== '') {
            $this->setPageTitle($seoMeta->title);
        }

        $ogTitle = $seoMeta->open_graph_title ?? $seoMeta->title;
        if ($ogTitle !== null && $ogTitle !== '') {
            $this->setMetaPropertyTag('og:title', $ogTitle);
        }

        $twitterTitle = $seoMeta->twitter_title ?? $seoMeta->title;
        if ($twitterTitle !== null && $twitterTitle !== '') {
            $this->setMetaTag('twitter:title', $twitterTitle);
        }

        // Description
        if ($seoMeta->description) {
            $this
                ->setMetaTag('description', $seoMeta->description)
                ->setSchemaJson('description', $seoMeta->description)
                ->setMetaPropertyTag('og:description', $seoMeta->open_graph_description ?? $seoMeta->description)
                ->setMetaTag('twitter:description', $seoMeta->twitter_description ?? $seoMeta->description);
        }

        // Image
        if ($seoMeta->open_graph_image) {
            $this
                ->setMetaPropertyTag('og:image', $seoMeta->open_graph_image)
                ->setMetaTag('twitter:image', $seoMeta->twitter_image ?? $seoMeta->open_graph_image)
                ->setSchemaJson('image', $seoMeta->open_graph_image);
        }

        // Keywords
        if ($seoMeta->keywords) {
            $this->setKeywords($seoMeta->keywords);
        }

        // Published on/created at
        $this->setPublishedOn($seoMeta->created_at);

        // Updated at
        if ($seoMeta->updated_at) {
            $this->setUpdatedOn($seoMeta->updated_at);
        }

        // Generate robots tags for this page
        $robotTags = [
            $seoMeta->robots_noindex ? 'noindex' : 'index',
            $seoMeta->robots_nofollow ? 'nofollow' : 'follow',
        ];

        if ($seoMeta->robots_noarchive) {
            $robotTags[] = 'noarchive';
        }

        if ($seoMeta->robots_noimageindex) {
            $robotTags[] = 'noimageindex';
        }

        if ($seoMeta->robots_nosnippet) {
            $robotTags[] = 'nosnippet';
        }

        $this->setMetaTag('robots', implode(', ', $robotTags));

        return $this;
    }

    /**
     * Set page title.
     */
    public function setPageTitle(string $title): self
    {
        $this->flarumDocument->title = $title;

        return $this;
    }
}
