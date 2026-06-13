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

use Flarum\Http\RequestUtil;
use Flarum\Http\SlugManager;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use FoF\Seo\Breadcrumb\Crumb;
use FoF\Seo\SeoProperties;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProfilePage implements PageDriverInterface
{
    public function __construct(
        protected readonly TranslatorInterface $translator,
        protected readonly SettingsRepositoryInterface $settings,
        protected readonly SlugManager $slugManager,
    ) {
    }

    public function extensionDependencies(): array
    {
        return [];
    }

    public function handleRoutes(): array
    {
        return ['user'];
    }

    public function handle(
        ServerRequestInterface $request,
        SeoProperties $properties
    ): void {
        $slug = Arr::get($request->getQueryParams(), 'username');

        if ($slug === null) {
            return;
        }

        try {
            // Resolve through the configured user slug driver (username, id,
            // id-with-name, or a third-party driver) — the `/u/{username}` route
            // param is whatever that driver produced, not necessarily a username.
            /** @var User $user */
            $user = $this->slugManager->forResource(User::class)->fromSlug(
                $slug,
                RequestUtil::getActor($request)
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // No user matched the slug.
            return;
        }

        // The canonical profile slug under the configured driver (e.g. the
        // username, or `{id}-{name}`), used to build all profile URLs.
        $userSlug = $this->slugManager->forResource(User::class)->toSlug($user);

        // Profile title
        $profileTitle = $this->translator->trans('fof-seo.forum.profile_title', [
            'username' => $user->getAttribute('display_name'),
        ]);

        // Profile description
        $profileDescription = $this->translator->trans('fof-seo.forum.profile_description', [
            'username'         => $user->getAttribute('display_name'),
            'discussion_count' => $user->getAttribute('discussion_count'),
            'comment_count'    => $user->getAttribute('comment_count'),
        ]);

        // Schema — describe the creator on the Person mainEntity per Google's
        // ProfilePage guidance (identity + activity statistics).
        $mainEntity = [
            '@type'                     => 'Person',
            'name'                      => $user->getAttribute('display_name'),
            'alternateName'             => $user->getAttribute('username'),
            'identifier'                => $user->id,
            'url'                       => $properties->withApplicationPath('/u/'.$userSlug),
            'agentInteractionStatistic' => [
                [
                    '@type'                => 'InteractionCounter',
                    'interactionType'      => 'https://schema.org/WriteAction',
                    'userInteractionCount' => (int) $user->getAttribute('comment_count'),
                ],
                [
                    '@type'                => 'InteractionCounter',
                    'interactionType'      => 'https://schema.org/CreateAction',
                    'userInteractionCount' => (int) $user->getAttribute('discussion_count'),
                ],
            ],
        ];

        // Bio / avatar on the Person, when available.
        if ($user->getAttribute('bio') !== null) {
            $mainEntity['description'] = $user->getAttribute('bio');
        }

        if ($user->getAttribute('avatar_url') !== null) {
            $mainEntity['image'] = $user->getAttribute('avatar_url');
        }

        $properties
            // Page type
            ->setMetaPropertyTag('og:type', 'profile')
            ->setMetaPropertyTag('profile:username', $user->getAttribute('username'))

            // Add Schema.org metadata: ProfilePage https://schema.org/ProfilePage
            ->setSchemaJson('@type', 'ProfilePage')
            ->setSchemaJson('mainEntity', $mainEntity)
            ->setSchemaJson('name', $user->getAttribute('display_name'))
            ->setSchemaJson('dateCreated', $user->joined_at->toIso8601String());

        // Add avatar
        if ($user->getAttribute('avatar_url') !== null) {
            $properties->setImage($user->getAttribute('avatar_url'));
        }

        // Add bio if exists
        if ($user->getAttribute('bio') !== null) {
            $properties->setSchemaJson('about', $user->getAttribute('bio'));
        }

        $properties
            ->setSchemaJson('commentCount', $user->getAttribute('comment_count'))

            // Description
            ->setTitle($profileTitle)

            // Description
            ->setDescription($profileDescription)

            // Profile URL
            ->setUrl('/u/'.$userSlug)

            // Canonical url
            ->setCanonicalUrl('/u/'.$userSlug);

        // Breadcrumb: Home › {display name}. The profile is the current page.
        $properties->breadcrumb()->push(new Crumb($user->getAttribute('display_name')));

        // Optionally keep thin profile pages out of the search index (GH #62).
        // Links are still followed so crawlers can reach the content they link to.
        if ($this->settings->get('seo_noindex_profiles')) {
            $properties->setMetaTag('robots', 'noindex, follow');
        }
    }
}
