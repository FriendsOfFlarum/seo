<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Controller;

use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class Robots.
 */
class Robots implements RequestHandlerInterface
{
    protected SettingsRepositoryInterface $settings;
    protected UrlGenerator $url;

    public function __construct(
        SettingsRepositoryInterface $settings,
        UrlGenerator $url
    ) {
        $this->settings = $settings;
        $this->url = $url;
    }

    private function output(): string
    {
        $output = '';

        if ($this->settings->get('seo_allow_all_bots') !== '0') {
            $output .= 'User-agent: *';
            $output .= PHP_EOL.'Allow: /'.PHP_EOL;
        }

        // Get extensions enabled
        $extensionsEnabled = json_decode($this->settings->get('extensions_enabled'), true);

        // If sitemap extension is enabled, add sitemap.xml
        if (in_array('fof-sitemap', $extensionsEnabled)) {
            $output .= PHP_EOL.'Sitemap: '.$this->url->to('forum')->base().'/sitemap.xml'.PHP_EOL;
        }

        // Custom robots txt
        if ($this->settings->get('seo_robots_text') !== null && $this->settings->get('seo_robots_text') !== '') {
            $output .= $this->settings->get('seo_robots_text');
        }

        return $output;
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write($this->output());

        return $response->withHeader('Content-Type', 'text/plain');
    }
}
