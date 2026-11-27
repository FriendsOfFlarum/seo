<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Tests\integration\admin;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The admin settings page counts a registered setting with no value in the
 * admin payload as changed, so a fresh forum would open with unsaved changes.
 */
class SettingDefaultsTest extends TestCase
{
    #[Test]
    public function the_settings_on_the_admin_page_have_defaults(): void
    {
        $this->extension('fof-seo');

        $settings = $this->app()->getContainer()->make(SettingsRepositoryInterface::class)->all();

        $this->assertSame('', $settings['forum_keywords'] ?? null);
        $this->assertSame('large', $settings['seo_twitter_card_size'] ?? null);
        $this->assertArrayHasKey('seo_noindex_profiles', $settings);
    }
}
