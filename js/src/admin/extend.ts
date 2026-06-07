import app from 'flarum/admin/app';
import Extend from 'flarum/common/extenders';
import SettingsPage from './Pages/SettingsPage';
import { SEO_PERMISSION_CATEGORY } from './permissions';

export default [
  new Extend.Admin() //
    .page(SettingsPage)
    .permission(
      () => ({
        icon: 'fas fa-search',
        label: app.translator.trans('fof-seo.admin.permissions.configure_seo'),
        permission: 'fof-seo.canConfigure',
      }),
      SEO_PERMISSION_CATEGORY,
      90
    ),
];
