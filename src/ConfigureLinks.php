<?php

namespace FoF\Seo;

use s9e\TextFormatter\Configurator;

class ConfigureLinks
{
    public function __invoke(Configurator $configurator): void
    {
        $configurator->templateNormalizer->append(
            function (\DOMElement $template) {
                foreach ($template->getElementsByTagName('a') as $a) {
                    $a->setAttribute('rel', "{@rel}");
                    $a->setAttribute('target', "{@target}");
                }
            }
        );
    }
}
