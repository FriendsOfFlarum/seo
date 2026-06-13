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

use FoF\Seo\Breadcrumb\BreadcrumbTrail;
use FoF\Seo\Listeners\PageListener;
use FoF\Seo\SeoMeta\SeoMeta;

/**
 * FlarumSeo Properties Extender.
 */
class SeoProperties
{
    /**
     * Initializing extender. For internal user only.
     */
    public function __construct(
        private readonly PageListener $container,
    ) {
    }

    /**
     * Page title.
     *
     * @param string $title           Sets title
     * @param bool   $updatePageTitle Update page title as well
     * @param bool   $useAsHeadLine   Only true if you want to use this as headline
     */
    public function setTitle(string $title, bool $updatePageTitle = true, bool $useAsHeadLine = false): self
    {
        $this->container->setTitle($title, $useAsHeadLine);

        if ($updatePageTitle) {
            $this->container->setPageTitle($title);
        }

        return $this;
    }

    /**
     * Page description.
     *
     * @param string|null $content The description will automatically be 'dotted' if too long
     */
    public function setDescription(?string $content = null): self
    {
        if ($content === null) {
            return $this;
        }

        $this->container->setDescription($content);

        return $this;
    }

    /**
     * Generate page description.
     */
    public function generateDescriptionFromContent(string $content): string
    {
        $description = strip_tags($content);

        return trim(preg_replace('/\s+/', ' ', mb_substr($description, 0, 157))).(mb_strlen($description) > 157 ? '...' : '');
    }

    /**
     * Page full URL.
     *
     * @param string $url                   The path or url of the page (if it is the full url, set $prependApplicationUrl to false)
     * @param bool   $prependApplicationUrl Adds application before the URL if true
     */
    public function setUrl(string $url, bool $prependApplicationUrl = true): self
    {
        $this->container->setUrl($url, $prependApplicationUrl);

        return $this;
    }

    /**
     * Page canonical URL.
     *
     * @param string $path The path after the application URL
     *
     * Example: /topic/5-some-title
     */
    public function setCanonicalUrl(string $path, bool $prependApplicationUrl = true): self
    {
        $this->container->setCanonicalUrl($path, $prependApplicationUrl);

        return $this;
    }

    /**
     * Page keywords.
     *
     * @param array<int, string>|string $keywords An array of keywords that describes the page
     *
     * Example: ["keyword 1", "flarum", "site", "blog"]
     */
    public function setKeywords(array|string $keywords): self
    {
        $this->container->setKeywords($keywords);

        return $this;
    }

    /**
     * Social media image.
     *
     * @param string|null $imageUrl Path to an image
     */
    public function setImage(?string $imageUrl): self
    {
        if ($imageUrl) {
            $this->container->setImage($imageUrl);
        }

        return $this;
    }

    /**
     * Page published on.
     *
     * @param string $datetime The full date time
     *
     * Example: 2020-08-22 14:14:00
     */
    public function setPublishedOn(string $datetime): self
    {
        $this->container->setPublishedOn($datetime);

        return $this;
    }

    /**
     * Page last updated on.
     *
     * @param string $datetime The full date time
     *
     * Example: 2020-08-25 18:55:00
     */
    public function setUpdatedOn(string $datetime): self
    {
        $this->container->setUpdatedOn($datetime);

        return $this;
    }

    /**
     * Adds or updates an 'og:' key.
     *
     * example:
     * - key: "og:site_name"
     * - value: "blog"
     */
    public function setMetaPropertyTag(string $key, string $value): self
    {
        $this->container->setMetaPropertyTag($key, $value);

        return $this;
    }

    /**
     * Adds or updates a meta tag.
     *
     * example:
     * - key: "robots"
     * - value: "index, follow"
     */
    public function setMetaTag(string $key, string $value): self
    {
        $this->container->setMetaTag($key, $value);

        return $this;
    }

    /**
     * Adds or updates a JSON schema key.
     *
     * @param mixed $value
     *
     * example:
     * - key: "@type"
     * - value: "WebPage"
     */
    public function setSchemaJson(string $key, mixed $value): self
    {
        $this->container->setSchemaJson($key, $value);

        return $this;
    }

    /**
     * Returns current application full-path.
     */
    public function withApplicationPath(string $path): string
    {
        return $this->container->getApplicationPath($path);
    }

    public function getImageFromContent(?string $content = null): ?string
    {
        return $this->container->getImageFromContent($content);
    }

    public function getEstimatedReadingTime(?string $content = null): int
    {
        return $this->container->getEstimatedReadingTime($content);
    }

    /**
     * The breadcrumb trail for the current request, pre-seeded with a "Home"
     * crumb. Page drivers push their crumbs onto it; third parties reshape it
     * via the {@see \FoF\Seo\Event\BuildingBreadcrumb} event.
     *
     * ```php
     * $properties->breadcrumb()
     *     ->push(new Crumb($tag->name, $tagUrl))
     *     ->push(new Crumb($discussion->title)); // current page: no url
     * ```
     */
    public function breadcrumb(): BreadcrumbTrail
    {
        return $this->container->breadcrumb();
    }

    /**
     * A fresh breadcrumb trail seeded with the "Home" root, for building an
     * additional trail (e.g. a second primary-tag lineage). Register it with
     * {@see addBreadcrumb()}.
     */
    public function newBreadcrumb(): BreadcrumbTrail
    {
        return $this->container->newSeededTrail();
    }

    /**
     * Register an additional breadcrumb trail; each renders as its own
     * schema.org BreadcrumbList.
     */
    public function addBreadcrumb(BreadcrumbTrail $trail): self
    {
        $this->container->addBreadcrumb($trail);

        return $this;
    }

    /**
     * Generates a schema.org breadcrumb list from a flat tag array.
     *
     * @param array<int, array<string, mixed>> $tags
     *
     * @deprecated Use {@see breadcrumb()} and push Crumb objects instead.
     */
    public function generateSchemaBreadcrumb(array $tags): self
    {
        $this->container->setSchemaBreadcrumb($tags);

        return $this;
    }

    /**
     * Generate default tags from meta.
     */
    public function generateTagsFromMetaData(SeoMeta $data): self
    {
        $this->container->generateTagsFromMetaData($data);

        return $this;
    }
}
