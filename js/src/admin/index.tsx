import app from 'flarum/admin/app';
import SettingsPage from './Pages/SettingsPage';
import { SEO_PERMISSION_CATEGORY } from './permissions';
import extendDashboardPage from './extenders/extendDashboardPage';
import extendPermissionGrid from './extenders/extendPermissionGrid';

app.initializers.add('fof-seo', () => {
  app.extensionData
    .for('fof-seo')
    .registerPage(SettingsPage)
    .registerPermission(
      {
        icon: 'fas fa-search',
        label: app.translator.trans('fof-seo.admin.permissions.configure_seo'),
        permission: 'fof-seo.canConfigure',
      },
      SEO_PERMISSION_CATEGORY,
      90
    );

  extendDashboardPage();
  extendPermissionGrid();
});

export * from './components';
export * from './Pages';
