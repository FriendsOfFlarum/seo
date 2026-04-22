<?php

namespace FoF\Seo\Page;

use Flarum\Settings\SettingsRepositoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use FoF\Seo\Page\PageDriverInterface;
use FoF\Seo\SeoProperties;

class IndexPage implements PageDriverInterface
{
    protected SettingsRepositoryInterface $settings;

    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
    }

    public function extensionDependencies(): array
    {
        return [];
    }

    public function handleRoutes(): array
    {
        return ['default', 'index'];
    }

    public function handle(
        ServerRequestInterface $request,
        SeoProperties $properties
    ): void {
        $routeName = $request->getAttribute('routeName');

        $properties->setDescription($this->settings->get('forum_description'));
        $properties->setKeywords($this->settings->get('forum_keywords') ?? []);
        $properties->setTitle($this->settings->get('forum_title'));
        $properties->setUrl('');
        $properties->setCanonicalUrl('');

        // Update meta tag URL when it's the discussion overview page
        if ($routeName === "default" && $this->settings->get('default_route') !== '/all') {
            $properties->setUrl('/all');
            $properties->setCanonicalUrl('/all');
        }
    }
}
