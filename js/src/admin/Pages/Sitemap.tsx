import app from 'flarum/admin/app';
import Page from 'flarum/common/components/Page';
import icon from 'flarum/common/helpers/icon';

export default class Sitemap extends Page {
  view() {
    return (
      <div>
        <h2>{app.translator.trans('fof-seo.admin.pages.sitemap.why_heading')}</h2>
        <p>{app.translator.trans('fof-seo.admin.pages.sitemap.why_body')}</p>
        <p>{app.translator.trans('fof-seo.admin.pages.sitemap.generated_note')}</p>

        <h4>{app.translator.trans('fof-seo.admin.pages.sitemap.which_extension_heading')}</h4>
        <p>
          {app.translator.trans('fof-seo.admin.pages.sitemap.which_extension_body', {
            link: (
              <a href="https://discuss.flarum.org/d/14941-fof-sitemap" target="_blank">
                FriendsOfFlarum Sitemap {icon('fas fa-external-link-alt')}
              </a>
            ),
          })}
        </p>

        <p>{app.translator.trans('fof-seo.admin.pages.sitemap.which_extension_details', { b: <b /> })}</p>

        <h4>{app.translator.trans('fof-seo.admin.pages.sitemap.just_installed_heading')}</h4>
        <p>{app.translator.trans('fof-seo.admin.pages.sitemap.just_installed_body')}</p>
      </div>
    );
  }
}
