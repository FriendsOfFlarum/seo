<?php

namespace FoF\Seo\Page;

use Psr\Http\Message\ServerRequestInterface;
use FoF\Seo\SeoProperties;

interface PageDriverInterface
{
    /**
     * A list of Flarum extension IDs for extensions that should be enabled
     *
     * @return array<int, string>
     */
    public function extensionDependencies(): array;

    /**
     * A list of route names that will be handled
     *
     * Empty array if handles for all routes
     *
     * @return array<int, string>
     */
    public function handleRoutes(): array;

    /**
     * Handle page SEO
     */
    public function handle(ServerRequestInterface $request, SeoProperties $seo): void;
}
