<?php

namespace FoF\Seo\Page;

use Flarum\User\UserRepository;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use FoF\Seo\SeoProperties;

class ProfilePage implements PageDriverInterface
{
    protected UserRepository $userRepository;

    protected TranslatorInterface $translator;

    public function __construct(UserRepository $userRepository, TranslatorInterface $translator)
    {
        $this->userRepository = $userRepository;
        $this->translator = $translator;
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
        $username = Arr::get($request->getQueryParams(), 'username');

        try {
            $user = is_numeric($username) ? $this->userRepository->findOrFail($username) : $this->userRepository->findByIdentification($username);

            // Make sure there's a user
            if ($user === null) return;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Do nothing. It just did not work
            return;
        }

        // Profile title
        $profileTitle = $this->translator->trans("fof-seo.forum.profile_title", [
            'username' => $user->getAttribute('display_name'),
        ]);

        // Profile description
        $profileDescription = $this->translator->trans("fof-seo.forum.profile_description", [
            'username' => $user->getAttribute('display_name'),
            'discussion_count' => $user->getAttribute('discussion_count'),
            'comment_count' => $user->getAttribute('comment_count')
        ]);

        // Schema
        $mainEntity = [
            "@type" => "Person",
            'name' => $user->getAttribute('username')
        ];

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
            ->setUrl('/u/' . $user->getAttribute('username'))

            // Canonical url
            ->setCanonicalUrl('/u/' . $user->getAttribute('username'));
    }
}
