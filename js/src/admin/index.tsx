import app from 'flarum/admin/app';
import { extend } from 'flarum/common/extend';
import DashboardPage from 'flarum/admin/components/DashboardPage';
import SeoWidget from './components/SeoWidget';
import SettingsPage from './Pages/SettingsPage';
import PermissionGrid from 'flarum/admin/components/PermissionGrid';

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
    'seo',
    90
  );

  // Add addPermissions
  extend(PermissionGrid.prototype, 'permissionItems', function (items) {
    // Add knowledge base permissions
    items.add(
      'seo',
      {
        label: 'SEO',
        children: this.attrs.extensionId
          ? app.extensionData.getExtensionPermissions(this.extensionId, 'seo').toArray()
          : app.extensionData.getAllExtensionPermissions('seo').toArray(),
      },
      80
    );
  });
});

// @deprecated Kept so third-party extensions using
// `app.initializers.has('v17development-flarum-seo')` continue to detect this
// extension. Will be removed in fof/seo for Flarum 2.x.
app.initializers.add('v17development-flarum-seo', () => {});

export * from './components';
export * from './Pages';
