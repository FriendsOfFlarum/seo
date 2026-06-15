<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Support;

use Flarum\Http\SlugManager;
use Flarum\Http\UrlGenerator;
use Flarum\User\User;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds the schema.org Person for a post/discussion author.
 *
 * `author` (and `author.name`) are REQUIRED on DiscussionForumPosting and on
 * each Comment — omitting them is a critical error that blocks rich results.
 * So a deleted user still yields a Person, named with the localized "[deleted]"
 * label, just without a profile `url` (which is only recommended, and which a
 * removed account no longer has). This keeps the markup GDPR-safe — "[deleted]"
 * carries no personal data — while satisfying Google's required fields.
 */
class AuthorSchema
{
    public function __construct(
        private readonly UrlGenerator $url,
        private readonly SlugManager $slugManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function forUser(?User $user): array
    {
        if ($user === null) {
            return [
                '@type' => 'Person',
                'name'  => $this->translator->trans('core.lib.username.deleted_text'),
            ];
        }

        return [
            '@type' => 'Person',
            'name'  => $user->getDisplayNameAttribute(),
            'url'   => $this->url->to('forum')->route('user', [
                'username' => $this->slugManager->forResource(User::class)->toSlug($user),
            ]),
        ];
    }
}
