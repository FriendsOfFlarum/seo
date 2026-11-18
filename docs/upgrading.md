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
- **robots.txt lets crawlers fetch noindexed profiles and tag pages.**
  Search engines need to fetch a page to read its `noindex`.
  - If fof/sitemap's **exclude users** is on, `Disallow: /u/` is dropped
    while **De-index profile pages** is on.
  - If **exclude tags** is on, `Disallow: /t/` and `/tags` stay, and an
    `Allow: /t/<slug>$` and `Allow: /t/<slug>?` line is added for each
    noindexed tag (selected under **De-index tags**, a child of a selected
    tag, or with its own `noindex`) that guests can open. Every other tag
    page stays blocked.

  If you relied on these blocks to keep compliant crawlers off profiles or
  those tag pages (for example for members' privacy), note that they are
  then fetched, though kept out of search results by `noindex`; guests could
  always see them anyway.
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

`DiscussionPage` and `DiscussionBestAnswerPage` also take
`FoF\Seo\Page\DiscussionContext $context` immediately after `$authorSchema`.
Pass it through when overriding the constructor. This internal service shares
lookups between the drivers for a single request; `PageDriverInterface` and
`DiscussionPage::buildForumPosting()` are unchanged.
