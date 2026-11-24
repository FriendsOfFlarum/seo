import app from 'flarum/admin/app';
import ExtensionPage, { type ExtensionPageAttrs } from 'flarum/admin/components/ExtensionPage';
import type { TabItem } from 'flarum/common/components/Tabs';
import type ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';

import HealthCheck from './HealthCheck';
import RegisterToSearchEngines from './RegisterToSearchEngines';
import SSLPage from './SSLPage';
import Sitemap from './Sitemap';

const CORE_SETTINGS = ['forum_description'];

export default class SettingsPage extends ExtensionPage {
  oninit(vnode: Mithril.Vnode<ExtensionPageAttrs, this>) {
    super.oninit(vnode);

    // Links from before the page had native tabs name the tab `?page=`. The page
    // is keyed on its route params except `tab`, so it is reopened with `tab`
    // before anything is edited: a later tab switch would drop `page` and lose
    // unsaved changes.
    const page = m.route.param('page');
    if (!this.attrs.tab && typeof page === 'string') {
      m.route.set(app.route('extension', { id: this.extension.id, tab: page }), undefined, { replace: true });
    }
  }

  /**
   * Core's reset deletes every setting the extension registered; the forum
   * description belongs to the Basics page and is edited here only for
   * convenience.
   */
  settingsFormControls(): ItemList<Mithril.Children> {
    const items = super.settingsFormControls();

    if (items.has('reset')) {
      const settings = (app.registry.getSettings(this.extension.id) || [])
        .filter((e): e is Exclude<typeof e, () => Mithril.Children> => typeof e !== 'function')
        .filter((e) => !CORE_SETTINGS.includes(e.setting))
        .map((e) => ({ key: e.setting, label: e.label }));

      items.setContent(
        'reset',
        this.resetButton(
          settings,
          app.translator.trans(
            'core.admin.extension.reset_settings.title_extension',
            { extensionTitle: this.extension.extra['flarum-extension'].title },
            true
          ),
          this.extension.id
        )
      );
    }

    return items;
  }

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
