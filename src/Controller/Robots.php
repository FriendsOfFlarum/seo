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

class Robots implements RequestHandlerInterface
{
    public function __construct(
        protected readonly SettingsRepositoryInterface $settings,
        protected readonly UrlGenerator $url,
    ) {
    }

    private function output(): string
    {
        $output = '';

        if ($this->settings->get('seo_allow_all_bots') !== '0') {
            $output .= 'User-agent: *';
            $output .= PHP_EOL.'Allow: /'.PHP_EOL;
        }

        $extensionsEnabled = json_decode($this->settings->get('extensions_enabled'), true);

        if (in_array('fof-sitemap', $extensionsEnabled, true)) {
            $output .= PHP_EOL.'Sitemap: '.$this->url->to('forum')->base().'/sitemap.xml'.PHP_EOL;
        }

        $customRobots = $this->settings->get('seo_robots_text');

        if ($customRobots !== null && $customRobots !== '') {
            $output .= $customRobots;
        }

        return $output;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write($this->output());

        return $response->withHeader('Content-Type', 'text/plain');
    }
}
