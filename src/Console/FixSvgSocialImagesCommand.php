<?php

/*
 * This file is part of fof/seo.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Seo\Console;

use Flarum\Console\AbstractCommand;
use Flarum\Discussion\Discussion;
use Flarum\Post\CommentPost;
use FoF\Seo\SeoMeta\SeoMeta;
use FoF\Seo\SeoProperties;
use Symfony\Component\Console\Input\InputOption;

/**
 * Re-derives the social image (`og:image`) for discussions whose auto-selected
 * image is an SVG. SVGs (e.g. shields.io badges) are rejected by Slack/X and
 * produce a plain unfurl with no preview, so they should never have been
 * selected (GH #149). The selection logic was fixed for new/edited discussions;
 * this backfills existing rows.
 *
 * Processed in id-ordered chunks so memory and database load stay flat
 * regardless of how many discussions a forum has.
 */
class FixSvgSocialImagesCommand extends AbstractCommand
{
    public function __construct(
        protected readonly SeoProperties $seoProperties,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('fof:seo:fix-svg-images')
            ->setDescription('Re-derive og:image for discussions whose auto-selected image is an SVG (GH #149).')
            ->addOption('batch', null, InputOption::VALUE_REQUIRED, 'Number of rows to process per chunk.', '100')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would change without writing anything.');
    }

    protected function fire(): int
    {
        $batch = max(1, (int) $this->input->getOption('batch'));
        $dryRun = (bool) $this->input->getOption('dry-run');

        if ($dryRun) {
            $this->info('Dry run — no changes will be saved.');
        }

        $scanned = 0;
        $updated = 0;
        $cleared = 0;

        // Only auto-managed discussion images that are SVGs. Manual uploads and
        // images managed by another extension (source != 'auto') are left alone.
        $query = SeoMeta::query()
            ->where('object_type', 'discussions')
            ->where('open_graph_image', 'like', '%.svg')
            ->where(function ($q) {
                $q->whereNull('open_graph_image_source')
                    ->orWhere('open_graph_image_source', 'auto');
            });

        // chunkById keeps a stable cursor and bounded memory even while we
        // mutate rows; ordering by the primary key avoids skipped/repeated rows.
        $query->chunkById($batch, function ($metas) use (&$scanned, &$updated, &$cleared, $dryRun) {
            foreach ($metas as $meta) {
                $scanned++;

                $newImage = $this->deriveImage($meta);

                // Nothing usable, and it is already cleared — skip.
                if ($newImage === $meta->open_graph_image) {
                    continue;
                }

                if ($newImage === null) {
                    $cleared++;
                    $this->info("#{$meta->object_id}: no usable image — cleared (will fall back to the forum image).");
                } else {
                    $updated++;
                    $this->info("#{$meta->object_id}: {$newImage}");
                }

                if (!$dryRun) {
                    $meta->open_graph_image = $newImage;
                    $meta->open_graph_image_source = 'auto';
                    $meta->save();
                }
            }
        }, 'id');

        $this->info(sprintf(
            'Done. Scanned %d SVG image(s): %d re-pointed to a raster image, %d cleared.',
            $scanned,
            $updated,
            $cleared
        ));

        return 0;
    }

    /**
     * Re-derive the first raster image from a discussion's first post, or null
     * when none exists (so rendering falls back to the forum social image).
     */
    private function deriveImage(SeoMeta $meta): ?string
    {
        $discussion = Discussion::find($meta->object_id);

        $firstPost = $discussion?->firstPost;

        if (!$firstPost instanceof CommentPost) {
            return null;
        }

        return $this->seoProperties->getImageFromContent($firstPost->formatContent());
    }
}
