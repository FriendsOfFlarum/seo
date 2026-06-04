import app from 'flarum/admin/app';
import { extend } from 'flarum/common/extend';
import DashboardPage from 'flarum/admin/components/DashboardPage';
import SeoWidget from './components/SeoWidget';
import SettingsPage from './Pages/SettingsPage';
import PermissionGrid, { PermissionType } from 'flarum/admin/components/PermissionGrid';

// Custom permission category not in core's PermissionType union; the
// permissionItems() extender below renders it as its own section.
const SEO_PERMISSION_CATEGORY = 'seo' as unknown as PermissionType;

app.initializers.add('fof-seo', () => {
  app.extensionData.for('fof-seo').registerPage(SettingsPage);

  // Add widget
  extend(DashboardPage.prototype, 'availableWidgets', (widgets) => {
    widgets.add('seo-widget', <SeoWidget />, 500);
  });

  app.extensionData.for('fof-seo').registerPermission(
    {
      icon: 'fas fa-search',
      label: app.translator.trans('fof-seo.admin.permissions.configure_seo'),
      permission: 'fof-seo.canConfigure',
    },
    SEO_PERMISSION_CATEGORY,
    90
  );

  // Add addPermissions
  extend(PermissionGrid.prototype, 'permissionItems', function (items) {
    const extensionId = (this.attrs as { extensionId?: string }).extensionId;

    items.add(
      'seo',
      {
        label: 'SEO',
        children: extensionId
          ? app.extensionData.getExtensionPermissions(extensionId, SEO_PERMISSION_CATEGORY).toArray()
          : app.extensionData.getAllExtensionPermissions(SEO_PERMISSION_CATEGORY).toArray(),
      },
      80
    );
  });
});

export * from './components';
export * from './Pages';
