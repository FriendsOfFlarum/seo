import app from 'flarum/admin/app';
import Page from 'flarum/common/components/Page';
import Button from 'flarum/common/components/Button';
import saveSettings from 'flarum/admin/utils/saveSettings';
import icon from 'flarum/common/helpers/icon';
import type Mithril from 'mithril';

export default class RegisterToSearchEngines extends Page {
  saving: boolean = false;
  hasConfirmed: boolean = false;

  oninit(vnode: Mithril.Vnode<{}, this>) {
    super.oninit(vnode);

    this.hasConfirmed = app.data.settings.seo_reviewed_search_engines === '1';
  }

  view() {
    return (
      <div>
        <h2>{app.translator.trans('fof-seo.admin.pages.search_engines.heading')}</h2>
        <p>{app.translator.trans('fof-seo.admin.pages.search_engines.intro')}</p>

        <p>
          {app.translator.trans('fof-seo.admin.pages.search_engines.sitemap_tip', {
            a: <a href="#/seo/sitemap" />,
          })}
        </p>

        <div>
          <h4>{app.translator.trans('fof-seo.admin.pages.search_engines.google_heading')}</h4>
          <p>
            {app.translator.trans('fof-seo.admin.pages.search_engines.google_visit', {
              link: (
                <a href="https://search.google.com/search-console" target="_blank">
                  Google Search Console {icon('fas fa-external-link-alt')}
                </a>
              ),
            })}
          </p>

          <p>{app.translator.trans('fof-seo.admin.pages.search_engines.google_www', { strong: <strong /> })}</p>

          <p>{app.translator.trans('fof-seo.admin.pages.search_engines.google_sitemap', { b: <b /> })}</p>
        </div>

        <div>
          <h4>{app.translator.trans('fof-seo.admin.pages.search_engines.bing_heading')}</h4>
          <p>
            {app.translator.trans('fof-seo.admin.pages.search_engines.bing_visit', {
              link: (
                <a href="https://www.bing.com/toolbox/webmaster" target="_blank">
                  Bing Webmaster Tools {icon('fas fa-external-link-alt')}
                </a>
              ),
            })}
          </p>

          <p>{app.translator.trans('fof-seo.admin.pages.search_engines.bing_sitemap')}</p>
        </div>

        <div>
          <h4>{app.translator.trans('fof-seo.admin.pages.search_engines.yandex_heading')}</h4>
          <p>
            {app.translator.trans('fof-seo.admin.pages.search_engines.yandex_visit', {
              link: (
                <a href="https://webmaster.yandex.com" target="_blank">
                  Yandex.Webmaster {icon('fas fa-external-link-alt')}
                </a>
              ),
            })}
          </p>

          <p>{app.translator.trans('fof-seo.admin.pages.search_engines.yandex_sitemap')}</p>
        </div>

        <div>
          <h4>{app.translator.trans('fof-seo.admin.pages.search_engines.yahoo_heading')}</h4>
          <p>{app.translator.trans('fof-seo.admin.pages.search_engines.yahoo_body')}</p>
        </div>

        <div className="clear"></div>
        <Button
          className={'Button pull-right ' + (this.hasConfirmed ? 'hidden' : '')}
          onclick={() => this.confirm()}
          icon="fas fa-check"
          loading={this.saving}
        >
          {app.translator.trans('fof-seo.admin.pages.search_engines.confirm_button')}
        </Button>
      </div>
    );
  }

  confirm() {
    this.saveSingleSetting('seo_reviewed_search_engines', true);
  }

  saveSingleSetting(setting: string, value: unknown) {
    if (this.saving) return;

    this.saving = true;

    saveSettings({ [setting]: value })
      .then(() => {
        this.hasConfirmed = true;
        app.alerts.show({ type: 'success' }, app.translator.trans('core.admin.settings.saved_message'));
      })
      .catch(() => {})
      .then(() => {
        this.saving = false;
        m.redraw();
      });
  }
}
