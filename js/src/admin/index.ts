import app from 'flarum/admin/app';
import extendDashboardPage from './extenders/extendDashboardPage';
import extendPermissionGrid from './extenders/extendPermissionGrid';

export { default as extend } from './extend';

app.initializers.add('fof-seo', () => {
  extendDashboardPage();
  extendPermissionGrid();
});
