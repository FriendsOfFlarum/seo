import app from 'flarum/admin/app';
import { extend } from 'flarum/common/extend';
import DashboardPage from 'flarum/admin/components/DashboardPage';
import SeoWidget from '../components/SeoWidget';

export default function extendDashboardPage() {
    extend(DashboardPage.prototype, 'availableWidgets', (widgets) => {
    widgets.add('seo-widget', <SeoWidget />, 500);
  });
}
