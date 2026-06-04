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

use s9e\TextFormatter\Configurator;

class ConfigureLinks
{
    public function __invoke(Configurator $configurator): void
    {
        $configurator->templateNormalizer->append(
            function (\DOMElement $template) {
                foreach ($template->getElementsByTagName('a') as $a) {
                    $a->setAttribute('rel', '{@rel}');
                    $a->setAttribute('target', '{@target}');
                }
            }
        );
    }
}
