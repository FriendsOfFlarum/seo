import app from 'flarum/admin/app';
import ExtensionPage, { type ExtensionPageAttrs } from 'flarum/admin/components/ExtensionPage';
import type { TabItem } from 'flarum/common/components/Tabs';
import type ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';

import HealthCheck from './HealthCheck';
import RegisterToSearchEngines from './RegisterToSearchEngines';
import SSLPage from './SSLPage';
import Sitemap from './Sitemap';

export default class SettingsPage extends ExtensionPage {
  oninit(vnode: Mithril.Vnode<ExtensionPageAttrs, this>) {
    super.oninit(vnode);

    // Links from before the page had native tabs name the tab `?page=`.
    const page = m.route.param('page');
    if (!this.attrs.tab && typeof page === 'string') this.activePageTab = page;
  }

  /**
   * The health check first, then the settings, the informational pages and
   * core's Permissions tab.
   */
  pageTabItems(): ItemList<TabItem> {
    const items = super.pageTabItems();

    items.add('health', { id: 'health', label: app.translator.trans('fof-seo.admin.header.health_check'), icon: 'fas fa-heartbeat' }, 100);
    items.add('sitemap', { id: 'sitemap', label: app.translator.trans('fof-seo.admin.header.sitemap_info'), icon: 'fas fa-sitemap' }, 8);
    items.add(
      'search-engines',
      { id: 'search-engines', label: app.translator.trans('fof-seo.admin.header.search_engines_info'), icon: 'fas fa-search' },
      6
    );
    items.add('ssl', { id: 'ssl', label: app.translator.trans('fof-seo.admin.header.setup_ssl'), icon: 'fas fa-shield-alt' }, 4);

    return items;
  }

  pageTabContent(tab: string, vnode: Mithril.VnodeDOM<ExtensionPageAttrs, this>): Mithril.Children {
    const content = this.infoPages()[tab];

    if (!content) return super.pageTabContent(tab, vnode);

    return (
      <div className="FlarumSEO">
        <div className="FlarumSeoPage-container">{content()}</div>
      </div>
    );
  }

  infoPages(): Record<string, () => Mithril.Children> {
    return {
      health: () => <HealthCheck />,
      sitemap: () => <Sitemap />,
      'search-engines': () => <RegisterToSearchEngines />,
      ssl: () => <SSLPage />,
    };
  }
}
