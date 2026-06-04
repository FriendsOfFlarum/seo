import app from 'flarum/admin/app';
import Page from 'flarum/common/components/Page';
import icon from 'flarum/common/helpers/icon';

export default class SSLPage extends Page {
  view() {
    return (
      <div>
        <h2>{app.translator.trans('fof-seo.admin.pages.ssl.intro_heading')}</h2>
        <p>{app.translator.trans('fof-seo.admin.pages.ssl.intro_body', { b: <b /> })}</p>

        <h4>{app.translator.trans('fof-seo.admin.pages.ssl.rankings_heading', { icon: icon('fas fa-heart') })}</h4>
        <p>{app.translator.trans('fof-seo.admin.pages.ssl.rankings_body')}</p>

        <p>{app.translator.trans('fof-seo.admin.pages.ssl.rankings_body_ssl')}</p>

        <h4>{app.translator.trans('fof-seo.admin.pages.ssl.what_heading')}</h4>
        <p>{app.translator.trans('fof-seo.admin.pages.ssl.what_body', { b: <b />, i: <i /> })}</p>

        <h4>{app.translator.trans('fof-seo.admin.pages.ssl.how_heading')}</h4>
        <p>
          {app.translator.trans('fof-seo.admin.pages.ssl.how_body', {
            link: (
              <a href="https://letsencrypt.org/" target="_blank">
                Let's Encrypt {icon('fas fa-external-link-alt')}
              </a>
            ),
          })}
        </p>

        <h4>{app.translator.trans('fof-seo.admin.pages.ssl.added_heading')}</h4>
        <p>{app.translator.trans('fof-seo.admin.pages.ssl.added_body', { b: <b /> })}</p>

        <h4>{app.translator.trans('fof-seo.admin.pages.ssl.no_ssl_heading')}</h4>
        <p>{app.translator.trans('fof-seo.admin.pages.ssl.no_ssl_body', { b: <b /> })}</p>
      </div>
    );
  }
}
