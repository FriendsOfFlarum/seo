<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

/*
 * Backwards-compatibility aliases for third-party extensions that depend on
 * the pre-FoF (V17Development\FlarumSeo) namespace. These will be removed in
 * a future major version — update third-party code to use the FoF\Seo
 * namespace. See the migration guide for details.
 */

class_alias(\FoF\Seo\Extend\SEO::class, 'V17Development\\FlarumSeo\\Extend\\SEO');
class_alias(\FoF\Seo\SeoProperties::class, 'V17Development\\FlarumSeo\\SeoProperties');

if (!interface_exists('V17Development\\FlarumSeo\\Page\\PageDriverInterface', false)) {
    class_alias(\FoF\Seo\Page\PageDriverInterface::class, 'V17Development\\FlarumSeo\\Page\\PageDriverInterface');
}

if (!interface_exists('V17Development\\FlarumSeo\\SeoExtenderManagerInterface', false)) {
    class_alias(\FoF\Seo\SeoExtenderManagerInterface::class, 'V17Development\\FlarumSeo\\SeoExtenderManagerInterface');
}
