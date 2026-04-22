import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
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
          <div className={'container'}>{this.menuButtons(page)}</div>
        </div>

        <div className="container FlarumSeoPage-container">{this.pageContent(page)}</div>
      </div>
    );
  }

  menuButtons(page: string): Mithril.Children[] {
    return [
      Button.component(
        {
          className: `Button ${page === 'health' ? 'item-selected' : ''}`,
          onclick: () =>
            m.route.set(
              app.route('extension', {
                id: 'fof-seo',
              })
            ),
          icon: 'fas fa-heartbeat',
        },
        'Health check'
      ),
      Button.component(
        {
          className: `Button ${page === 'settings' ? 'item-selected' : ''}`,
          onclick: () =>
            m.route.set(
              app.route('extension', {
                id: 'fof-seo',
                page: 'settings',
              })
            ),
          icon: 'fas fa-cogs',
        },
        'SEO settings'
      ),
      Button.component(
        {
          className: `Button ${page === 'sitemap' ? 'item-selected' : ''}`,
          onclick: () =>
            m.route.set(
              app.route('extension', {
                id: 'fof-seo',
                page: 'sitemap',
              })
            ),
          icon: 'fas fa-sitemap',
        },
        'Sitemap information'
      ),
      Button.component(
        {
          className: `Button ${page === 'search-engines' ? 'item-selected' : ''}`,
          onclick: () =>
            m.route.set(
              app.route('extension', {
                id: 'fof-seo',
                page: 'search-engines',
              })
            ),
          icon: 'fas fa-search',
        },
        'Search engine information'
      ),
      Button.component(
        {
          className: `Button ${page === 'ssl' ? 'item-selected' : ''}`,
          onclick: () =>
            m.route.set(
              app.route('extension', {
                id: 'fof-seo',
                page: 'ssl',
              })
            ),
          icon: 'fas fa-shield-alt',
        },
        'Set up SSL'
      ),
    ];
  }

  pageContent(page: string): Mithril.Children {
    if (page === 'search-engines') return <RegisterToSearchEngines />;
    if (page === 'settings') return <SeoSettings />;
    if (page === 'ssl') return <SSLPage />;
    if (page === 'sitemap') return <Sitemap />;

    return <HealthCheck />;
  }
}
