import { jest } from '@jest/globals';
import bootstrapAdmin from '@flarum/jest-config/src/bootstrap/admin';
import app from 'flarum/admin/app';
import PermissionGrid from 'flarum/admin/components/PermissionGrid';
import GeneralSearchSource from 'flarum/admin/components/GeneralSearchSource';
import extendPermissionGrid from '../../../src/admin/extenders/extendPermissionGrid';
import { SEO_PERMISSION_CATEGORY } from '../../../src/admin/permissions';
import loadTranslations from '../loadTranslations';

beforeAll(() => {
  bootstrapAdmin({
    extensions: {
      'fof-seo': { id: 'fof-seo', extra: { 'flarum-extension': { title: 'FoF SEO' } } },
    },
  });
  loadTranslations();

  // Mirrors the permission `src/admin/extend.ts` registers; that file pulls in tags through the settings page.
  app.registry.for('fof-seo').registerPermission(
    {
      icon: 'fas fa-search',
      label: app.translator.trans('fof-seo.admin.permissions.configure_seo'),
      permission: 'fof-seo.canConfigure',
    },
    SEO_PERMISSION_CATEGORY,
    90
  );

  app.initializers.add('fof-seo', extendPermissionGrid);
  app.boot();
});

afterEach(() => jest.restoreAllMocks());

// The admin search reads permission categories straight off the prototype, with no attrs (#203).
it('adds the SEO category when permissionItems is called outside a rendered grid', () => {
  const handleError = jest.spyOn(app, 'handleErrorOnce');

  const items = PermissionGrid.prototype.permissionItems();

  expect(handleError).not.toHaveBeenCalled();
  expect(items.has('seo')).toBe(true);
  expect(items.get('seo').children.map((child) => child.permission)).toEqual(['fof-seo.canConfigure']);
});

it('searches the admin without an extension error', async () => {
  const handleError = jest.spyOn(app, 'handleErrorOnce');

  await new GeneralSearchSource().search('upload', 10);

  expect(handleError).not.toHaveBeenCalled();
});

it('lists the SEO permission in admin search under the SEO heading', async () => {
  const source = new GeneralSearchSource();

  await source.search('configuring seo', 10);

  const result = (source as any).results.get('configuring seo').find((r: { id: string }) => r.id === 'fof-seo-seo-fof-seo.canConfigure');

  expect(result.tree.map(String)).toEqual(['Permissions', 'SEO', 'Allow configuring SEO']);
});
