# Upgrading

## To 4.0

### For forum admins: fof/sitemap and robots.txt

With [fof/sitemap](sitemap-and-robots.md) enabled (3.1.0 or later is
required; Composer refuses older versions next to FoF SEO):

- **The sitemap follows FoF SEO's noindex settings.** Discussions and tags
  selected under **De-index tags** (and their child tags), profiles while
  **De-index profile pages** is on, and discussions, tags and Pages whose own
  SEO meta has `noindex` are no longer listed. In fof/sitemap's cached modes
  this takes effect at its next scheduled build.
- **robots.txt can stop blocking profiles and tags.** If fof/sitemap's
  **exclude users** is on, `Disallow: /u/` is dropped while **De-index profile
  pages** is on. If **exclude tags** is on, `Disallow: /t/` and `/tags` are
  dropped while any tag is noindexed (selected under **De-index tags**, or
  with its own `noindex`). Search engines need to fetch a page to read its
  `noindex`. Since fof/sitemap's tags block covers every tag page, all tag
  pages become crawlable in that case, not just the noindexed ones.
- Nothing changes when those FoF SEO settings are off and no item has its own
  `noindex`: the sitemap and robots.txt stay exactly as fof/sitemap makes
  them.

See [Noindex, the sitemap and robots.txt](sitemap-and-robots.md#noindex-the-sitemap-and-robotstxt).

### For developers: page driver constructors

If you extend one of FoF SEO's page drivers and override its constructor:

- `FoF\Seo\Page\DiscussionPage`, `DiscussionBestAnswerPage` and `TagPage`
  take `FoF\Seo\Indexability\IndexabilityPolicy $indexability` where they
  took `FoF\Seo\TagIndexingPolicy $tagIndexingPolicy`. Use
  `$this->indexability->tagsNoindexed($tags)` instead of
  `$this->tagIndexingPolicy->shouldNoindex($tags)`.
- `FoF\Seo\Page\ProfilePage` takes an additional
  `IndexabilityPolicy $indexability` as its last argument.

`TagIndexingPolicy` itself is unchanged and can still be injected.
