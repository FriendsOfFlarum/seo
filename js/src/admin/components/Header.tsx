import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Dropdown from 'flarum/common/components/Dropdown';

export default class Header extends Component {
  view() {
    return (
      <div className="seo-header container">
        <div className="pull-right">
          {Dropdown.component(
            {
              label: 'Tools',
              icon: 'fas fa-cog',
              buttonClassName: 'Button',
              menuClassName: 'Dropdown-menu--right',
            },
            [
              Button.component(
                {
                  className: 'Button',
                  onclick: () => m.route.set(app.route('seo')),
                  icon: 'fas fa-heartbeat',
                },
                'Health check'
              ),
              Button.component(
                {
                  className: 'Button',
                  onclick: () => m.route.set(app.route('seoSettings')),
                  icon: 'fas fa-cogs',
                },
                'SEO settings'
              ),
              Button.component(
                {
                  className: 'Button',
                  onclick: () => m.route.set(app.route('seoSitemap')),
                  icon: 'fas fa-sitemap',
                },
                'Sitemap information'
              ),
              Button.component(
                {
                  className: 'Button',
                  onclick: () => m.route.set(app.route('seoSearchEngines')),
                  icon: 'fas fa-search',
                },
                'Search engine information'
              ),
              Button.component(
                {
                  className: 'Button',
                  onclick: () => m.route.set(app.route('seoSSL')),
                  icon: 'fas fa-shield-alt',
                },
                'Set up SSL'
              ),
            ]
          )}
        </div>

        <h2>Search Engine Optimization</h2>

        <div className="clear" />
      </div>
    );
  }
}
