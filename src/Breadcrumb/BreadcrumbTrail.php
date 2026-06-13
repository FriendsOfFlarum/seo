<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Breadcrumb;

/**
 * A mutable, ordered breadcrumb trail. Page drivers build it up and third
 * parties reshape it (via the BuildingBreadcrumb event) before it is rendered
 * to a schema.org BreadcrumbList.
 */
class BreadcrumbTrail
{
    /** @var list<Crumb> */
    protected array $crumbs = [];

    /**
     * Append a crumb to the end of the trail.
     */
    public function push(Crumb $crumb): self
    {
        $this->crumbs[] = $crumb;

        return $this;
    }

    /**
     * Prepend a crumb to the start of the trail (e.g. a "Home" root).
     */
    public function prepend(Crumb $crumb): self
    {
        array_unshift($this->crumbs, $crumb);

        return $this;
    }

    /**
     * Insert a crumb immediately after the first existing crumb matching the
     * predicate. If none match, the crumb is appended.
     *
     * @param callable(Crumb): bool $predicate
     */
    public function insertAfter(callable $predicate, Crumb $crumb): self
    {
        foreach ($this->crumbs as $index => $existing) {
            if ($predicate($existing)) {
                array_splice($this->crumbs, $index + 1, 0, [$crumb]);

                return $this;
            }
        }

        return $this->push($crumb);
    }

    /**
     * Remove every crumb matching the predicate.
     *
     * @param callable(Crumb): bool $predicate
     */
    public function remove(callable $predicate): self
    {
        $this->crumbs = array_values(array_filter(
            $this->crumbs,
            fn (Crumb $crumb) => !$predicate($crumb)
        ));

        return $this;
    }

    /**
     * Replace each crumb with the result of the callback. Returning the same
     * crumb (mutated) is fine; returning a new Crumb replaces it.
     *
     * @param callable(Crumb): Crumb $callback
     */
    public function map(callable $callback): self
    {
        $this->crumbs = array_values(array_map($callback, $this->crumbs));

        return $this;
    }

    /**
     * Replace the whole trail.
     *
     * @param list<Crumb> $crumbs
     */
    public function set(array $crumbs): self
    {
        $this->crumbs = array_values($crumbs);

        return $this;
    }

    /**
     * @return list<Crumb>
     */
    public function all(): array
    {
        return $this->crumbs;
    }

    public function count(): int
    {
        return count($this->crumbs);
    }

    public function isEmpty(): bool
    {
        return $this->crumbs === [];
    }

    /**
     * Render to a schema.org BreadcrumbList, or null when there are fewer than
     * two crumbs — Google requires at least two items, and a single crumb is
     * not a trail.
     *
     * Per Google's guidance the last item's `item` is omitted (Google uses the
     * containing page's URL); `itemListOrder`/`numberOfItems` are not emitted as
     * they are not required and the order is already conveyed by `position`.
     *
     * @return array<string, mixed>|null
     */
    public function toSchema(): ?array
    {
        if (count($this->crumbs) < 2) {
            return null;
        }

        $elements = [];
        $last = count($this->crumbs) - 1;

        foreach ($this->crumbs as $index => $crumb) {
            $element = [
                '@type'    => 'ListItem',
                'position' => $index + 1,
                'name'     => $crumb->name,
            ];

            // The current page (last crumb, or any crumb without a url) omits
            // `item`; Google falls back to the page URL.
            if ($crumb->url !== null && $index !== $last) {
                $element['item'] = array_merge([
                    '@type' => $crumb->type,
                    '@id'   => $crumb->url,
                    'name'  => $crumb->name,
                    'url'   => $crumb->url,
                ], $crumb->extra);
            }

            $elements[] = $element;
        }

        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }
}
