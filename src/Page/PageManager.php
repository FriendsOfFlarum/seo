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

use Flarum\Extension\ExtensionManager;
use FoF\Seo\SeoExtenderManagerInterface;
use Illuminate\Support\Collection;

class PageManager implements SeoExtenderManagerInterface
{
    /**
     * @var array<string, PageDriverInterface>
     */
    protected array $extenders = [];

    public function __construct(
        protected readonly ExtensionManager $extensionManager,
    ) {
    }

    /**
     * Add page extender.
     *
     * @param string              $name     Extender name
     * @param PageDriverInterface $extender Extender
     */
    public function addExtender(string $name, PageDriverInterface $extender): void
    {
        $this->extenders[$name] = $extender;
    }

    /**
     * Get all extenders.
     */
    public function getExtenders(?string $routeName = null): array
    {
        return $this->getActiveExtenders()
            ->filter(function (PageDriverInterface $driver) use ($routeName) {
                return $routeName === null || in_array($routeName, $driver->handleRoutes());
            })
            ->toArray();
    }

    /**
     * Filter on active extenders.
     */
    public function getActiveExtenders(): Collection
    {
        return collect($this->extenders)
            // Filter drivers that require extensions to be enabled
            ->filter(function (PageDriverInterface $extender) {
                foreach ($extender->extensionDependencies() as $extensionId) {
                    if (!$this->extensionManager->isEnabled($extensionId)) {
                        return false;
                    }
                }

                return true;
            });
    }
}
