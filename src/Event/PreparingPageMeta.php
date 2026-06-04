<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Event;

use Flarum\Frontend\Document;
use FoF\Seo\SeoProperties;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Dispatched after all page drivers have run but before the meta tags and
 * schema.org JSON-LD are written to the document.
 *
 * Listeners may read and mutate the prepared metadata through the
 * {@see SeoProperties} API — e.g. override the language, title, description,
 * social image, or add/replace arbitrary schema.org properties:
 *
 * ```php
 * (new Extend\Event())->listen(PreparingPageMeta::class, function (PreparingPageMeta $event) {
 *     $event->properties->setSchemaJson('inLanguage', 'de');
 *     $event->properties->setDescription('A localised description.');
 * });
 * ```
 */
class PreparingPageMeta
{
    public function __construct(
        public readonly SeoProperties $properties,
        public readonly Document $document,
        public readonly ServerRequestInterface $request,
    ) {
    }
}
