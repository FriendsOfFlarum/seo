<?php

namespace FoF\Seo\Extend;

use Flarum\Extension\Extension;
use Flarum\Extend\ExtenderInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;
use FoF\Seo\Page\PageManager;

class SEO implements ExtenderInterface
{
    /**
     * @var array<string, class-string>
     */
    protected array $extenders = [];

    /**
     * Register a new extender
     *
     * @param string $name Unique extender name
     * @param class-string $extender Extender class
     */
    public function addExtender(string $name, string $extender): self
    {
        $this->extenders[$name] = $extender;

        return $this;
    }

    /**
     * Remove existing extender
     *
     * @param string $name Extender name
     */
    public function removeExtender(string $name): self
    {
        Arr::forget($this->extenders, $name);

        return $this;
    }

    public function extend(Container $container, Extension $extension = null): void
    {
        $container->resolving(PageManager::class, function (PageManager $page) use ($container) {
            foreach ($this->extenders as $name => $extender) {
                $page->addExtender($name, $container->make($extender));
            }

            return $page;
        });
    }
}
