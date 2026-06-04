import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import LinkButton from 'flarum/common/components/LinkButton';
import ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';

import SeoSettings from '../components/Forms/SeoSettings';
import HealthCheck from './HealthCheck';
import RegisterToSearchEngines from './RegisterToSearchEngines';
import SSLPage from './SSLPage';
import Sitemap from './Sitemap';

export default class SettingsPage extends ExtensionPage {
  content() {
    const page: string = m.route.param('page') || 'health';

    return (
      <div className="ExtensionPage-settings FlarumSEO">
        <div className={'seo-menu'}>
          <div className={'container'}>{this.menuButtons(page).toArray()}</div>
        </div>

        <div className="container FlarumSeoPage-container">{this.pageContent(page)}</div>
      </div>
    );
  }

  menuButtons(page: string): ItemList<Mithril.Children> {
    const items = new ItemList<Mithril.Children>();

    items.add(
      'health',
      <LinkButton
        className={`Button ${page === 'health' ? 'item-selected' : ''}`}
        href={app.route('extension', { id: 'fof-seo' })}
        icon="fas fa-heartbeat"
      >
        {app.translator.trans('fof-seo.admin.header.health_check')}
      </LinkButton>,
      100
    );

    items.add(
      'settings',
      <LinkButton
        className={`Button ${page === 'settings' ? 'item-selected' : ''}`}
        href={app.route('extension', { id: 'fof-seo', page: 'settings' })}
        icon="fas fa-cogs"
      >
        {app.translator.trans('fof-seo.admin.header.seo_settings')}
      </LinkButton>,
      90
    );

    items.add(
      'sitemap',
      <LinkButton
        className={`Button ${page === 'sitemap' ? 'item-selected' : ''}`}
        href={app.route('extension', { id: 'fof-seo', page: 'sitemap' })}
        icon="fas fa-sitemap"
      >
        {app.translator.trans('fof-seo.admin.header.sitemap_info')}
      </LinkButton>,
      80
    );

    items.add(
      'search-engines',
      <LinkButton
        className={`Button ${page === 'search-engines' ? 'item-selected' : ''}`}
        href={app.route('extension', { id: 'fof-seo', page: 'search-engines' })}
        icon="fas fa-search"
      >
        {app.translator.trans('fof-seo.admin.header.search_engines_info')}
      </LinkButton>,
      70
    );

    items.add(
      'ssl',
      <LinkButton
        className={`Button ${page === 'ssl' ? 'item-selected' : ''}`}
        href={app.route('extension', { id: 'fof-seo', page: 'ssl' })}
        icon="fas fa-shield-alt"
      >
        {app.translator.trans('fof-seo.admin.header.setup_ssl')}
      </LinkButton>,
      60
    );

    return items;
  }

  pages(): ItemList<Mithril.Children> {
    const items = new ItemList<Mithril.Children>();

    items.add('health', <HealthCheck />, 100);
    items.add('settings', <SeoSettings />, 90);
    items.add('sitemap', <Sitemap />, 80);
    items.add('search-engines', <RegisterToSearchEngines />, 70);
    items.add('ssl', <SSLPage />, 60);

    return items;
  }

  pageContent(page: string): Mithril.Children {
    const pages = this.pages();

    return pages.has(page) ? pages.get(page) : pages.get('health');
  }
}
