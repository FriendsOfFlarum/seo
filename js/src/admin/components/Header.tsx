import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Dropdown from 'flarum/common/components/Dropdown';

export default class Header extends Component {
  view() {
    return (
      <div className="seo-header container">
        <div className="pull-right">
          <Dropdown
            label={app.translator.trans('fof-seo.admin.header.tools')}
            icon="fas fa-cog"
            buttonClassName="Button"
            menuClassName="Dropdown-menu--right"
          >
            <Button className="Button" onclick={() => m.route.set(app.route('seo'))} icon="fas fa-heartbeat">
              {app.translator.trans('fof-seo.admin.header.health_check')}
            </Button>
            <Button className="Button" onclick={() => m.route.set(app.route('seoSettings'))} icon="fas fa-cogs">
              {app.translator.trans('fof-seo.admin.header.seo_settings')}
            </Button>
            <Button className="Button" onclick={() => m.route.set(app.route('seoSitemap'))} icon="fas fa-sitemap">
              {app.translator.trans('fof-seo.admin.header.sitemap_info')}
            </Button>
            <Button className="Button" onclick={() => m.route.set(app.route('seoSearchEngines'))} icon="fas fa-search">
              {app.translator.trans('fof-seo.admin.header.search_engines_info')}
            </Button>
            <Button className="Button" onclick={() => m.route.set(app.route('seoSSL'))} icon="fas fa-shield-alt">
              {app.translator.trans('fof-seo.admin.header.setup_ssl')}
            </Button>
          </Dropdown>
        </div>

        <h2>{app.translator.trans('fof-seo.admin.header.title')}</h2>

        <div className="clear" />
      </div>
    );
  }
}
