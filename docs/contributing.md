# Contributing

Contributions to FoF SEO are very welcome! The extension is open source and
maintained by [FriendsOfFlarum](https://github.com/FriendsOfFlarum) at
[github.com/FriendsOfFlarum/seo](https://github.com/FriendsOfFlarum/seo).

Issues and pull requests are tracked on the
[issue tracker](https://github.com/FriendsOfFlarum/seo/issues).

## Frontend (JavaScript / TypeScript)

The frontend lives in `js/`.

```bash
cd js
npm install        # or: yarn
npm run dev        # watch + rebuild during development
npm run build      # production build
npm run format     # prettier
```

The source is TypeScript and follows current Flarum conventions (JSX, the `Link`
/ `LinkButton` components for navigation, the `icon()` helper, `ItemList` for
extensible lists, and so on).

## Backend (PHP)

The extension targets **PHP 8.2+** and **Flarum 1.8+**.

```bash
composer analyse:phpstan   # static analysis
composer test              # run unit + integration tests
composer test:unit         # unit tests only
composer test:integration  # integration tests only
composer test:setup        # one-time test database setup (see below)
```

### Running the integration tests

The integration suite needs a throwaway database. Configure it with the standard
Flarum testing environment variables and run the setup script once:

```bash
DB_HOST=127.0.0.1 DB_DATABASE=flarum_test DB_USERNAME=root DB_PASSWORD=root \
  composer test:setup
```

Then run the suite with the same variables. Please add or update tests when you
change behaviour — the suite covers the API, serializer attributes, the formatter
and the server-rendered SEO output.

## Guidelines

- Match the surrounding code style; run prettier (JS) and phpstan (PHP) before
  opening a PR.
- Keep user-facing strings translatable (add keys to `locale/en.yml`).
- Update these docs when you add or change a feature.
