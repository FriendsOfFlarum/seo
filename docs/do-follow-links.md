# Do-follow link list

By default, every link to an external domain in a post receives a
`rel="nofollow"` attribute (and opens in a new tab). This discourages people from
spamming your forum with links purely to gain referral "link juice".

The **do-follow list** lets you exempt domains you trust — links to them keep
their normal (followed) status. Use it for your own sites, partner/company
websites, or any source you're happy to vouch for.

## Managing the list

From *Administration → Extensions → FoF SEO → SEO settings*, open the
**No-follow links** section and click **Open domain do-follow list**.

Enter the **hostname** of each domain you want to allow. **Do not** include the
`www.` subdomain or the `https://` scheme.

For example, to allow `https://www.flarum.org`:

- ❌ `www.flarum.org`
- ✅ `flarum.org`

The domain your forum runs on is added to the list automatically.

## Is there a limit?

There's no hard limit, but the whole list is stored in Flarum's settings and the
formatter checks every external link in a post against it. Keep the list focused —
a very large list can slow down rendering.
