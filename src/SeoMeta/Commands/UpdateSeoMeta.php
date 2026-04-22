<?php

namespace FoF\Seo\SeoMeta\Commands;

use Flarum\User\User;

class UpdateSeoMeta
{
    public User $actor;

    public int|string $id;

    /**
     * @var array<string, mixed>
     */
    public array $data;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(User $actor, int|string $id, array $data)
    {
        $this->actor = $actor;
        $this->id = $id;
        $this->data = $data;
    }
}
