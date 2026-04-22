<?php

namespace FoF\Seo;

use FoF\Seo\Listeners\PageListener;
use FoF\Seo\SeoMeta\SeoMeta;

/**
 * FlarumSeo Properties Extender
 */
class SeoProperties
{
    private ?PageListener $container = null;

    /**
     * Initializing extender. For internal user only.
     */
    public function __construct(PageListener $container)
    {
        $this->container = $container;
    }

    /**
     * Page title
     *
     * @param string $title Sets title
     * @param bool $updatePageTitle Update page title as well
     * @param bool $useAsHeadLine Only true if you want to use this as headline
     */
    public function setTitle(string $title, bool $updatePageTitle = true, bool $useAsHeadLine = false): self
    {
        if ($this->container === null) self::throwError("setTitle");

        $this->container->setTitle($title, $useAsHeadLine);

        // Update page title as well
        if ($updatePageTitle) {
            $this->container->setPageTitle($title);
        }

        return $this;
    }

    /**
     * Page description
     *
     * @param string $content The description will automatically be 'dotted' if too long
     */
    public function setDescription(string|null $content = null): self
    {
        // Empty description
        if ($content === null) return $this;

        // Container not initialized
        if ($this->container === null) self::throwError("setDescription");

        // Set description
        $this->container->setDescription($content);

        return $this;
    }

    /**
     * Generate page description
     */
    public function generateDescriptionFromContent(string $content): string
    {
        $description = strip_tags($content);
        $description = trim(preg_replace('/\s+/', ' ', mb_substr($description, 0, 157))) . (mb_strlen($description) > 157 ? '...' : '');

        return $description;
    }

    /**
     * Page full URL
     *
     * @param string $url The path or url of the page (if it is the full url, set $prependApplicationUrl to false)
     * @param bool $prependApplicationUrl Adds application before the URL if true
     */
    public function setUrl(string $url, bool $prependApplicationUrl = true): self
    {
        if ($this->container === null) self::throwError("setUrl");

        $this->container->setUrl($url, $prependApplicationUrl);

        return $this;
    }

    /**
     * Page canonical URL
     *
     * @param string $path The path after the application URL
     *
     * Example: /topic/5-some-title
     */
    public function setCanonicalUrl(string $path, bool $prependApplicationUrl = true): self
    {
        if ($this->container === null) self::throwError("setCanonicalUrl");

        $this->container->setCanonicalUrl($path, $prependApplicationUrl);

        return $this;
    }

    /**
     * Page keywords
     *
     * @param array $keywords An array of keywords that describes the page
     *
     * Example: ["keyword 1", "flarum", "site", "blog"]
     */
    public function setKeywords(array|string $keywords): self
    {
        if ($this->container === null) self::throwError("setKeywords");

        $this->container->setKeywords($keywords);

        return $this;
    }

    /**
     * Social media image
     *
     * @param string|null $imageUrl Path to an image
     */
    public function setImage(?string $imageUrl): self
    {
        if ($this->container === null) self::throwError("setImage");

        if ($imageUrl) {
            $this->container->setImage($imageUrl);
        }

        return $this;
    }

    /**
     * Page published on
     *
     * @param string $datetime The full date time
     *
     * Example: 2020-08-22 14:14:00
     */
    public function setPublishedOn(string $datetime): self
    {
        if ($this->container === null) self::throwError("setPublishedOn");

        $this->container->setPublishedOn($datetime);

        return $this;
    }

    /**
     * Page last updated on
     *
     * @param string $datetime The full date time
     *
     * Example: 2020-08-25 18:55:00
     */
    public function setUpdatedOn(string $datetime): self
    {
        if ($this->container === null) self::throwError("setUpdatedOn");

        $this->container->setUpdatedOn($datetime);

        return $this;
    }

    /**
     * Adds or updates an 'og:' key
     *
     * example:
     * - key: "og:site_name"
     * - value: "blog"
     */
    public function setMetaPropertyTag(string $key, string $value): self
    {
        if ($this->container === null) self::throwError("setMetaPropertyTag");

        $this->container->setMetaPropertyTag($key, $value);

        return $this;
    }

    /**
     * Adds or updates a meta tag
     *
     * example:
     * - key: "robots"
     * - value: "index, follow"
     */
    public function setMetaTag(string $key, string $value): self
    {
        if ($this->container === null) self::throwError("setMetaTag");

        $this->container->setMetaTag($key, $value);

        return $this;
    }

    /**
     * Adds or updates a JSON schema key
     *
     * @param mixed $value
     *
     * example:
     * - key: "@type"
     * - value: "WebPage"
     */
    public function setSchemaJson(string $key, $value): self
    {
        if ($this->container === null) self::throwError("setSchemaJson");

        $this->container->setSchemaJson($key, $value);

        return $this;
    }

    /**
     * Returns current application full-path
     */
    public function withApplicationPath(string $path): string
    {
        return $this->container->getApplicationPath($path);
    }

    public function getImageFromContent(?string $content = null): ?string
    {
        return $this->container->getImageFromContent($content);
    }

    public function getEstimatedReadingTime(string $content = null): int
    {
        return $this->container->getEstimatedReadingTime($content);
    }

    /**
     * Generates a schema.org breadcrumb list
     *
     * @param array<int, array<string, mixed>> $tags
     */
    public function generateSchemaBreadcrumb(array $tags): self
    {
        $this->container->setSchemaBreadcrumb($tags);

        return $this;
    }

    /**
     * Generate default tags from meta
     */
    public function generateTagsFromMetaData(SeoMeta $data): self
    {
        $this->container->generateTagsFromMetaData($data);

        return $this;
    }

    /**
     * Container was not yet initialized
     *
     * @return never
     */
    private static function throwError(string $caller): void
    {
        throw new \Exception("SeoProperties::" . $caller . "(..): You're doing it wrong, container was improperly initialized. Please review Flarum SEO documentation.");
    }
}
