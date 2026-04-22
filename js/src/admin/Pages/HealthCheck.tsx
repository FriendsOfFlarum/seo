import app from 'flarum/admin/app';
import Page from 'flarum/common/components/Page';
import Button from 'flarum/common/components/Button';
import saveSettings from 'flarum/admin/utils/saveSettings';
import type Mithril from 'mithril';

type PassState = true | false | 'must';

export default class HealthCheck extends Page {
  settings: Record<string, string | undefined> = {};
  saving: boolean = false;

  oninit(vnode: Mithril.Vnode<{}, this>) {
    super.oninit(vnode);

    this.settings = app.data.settings;
  }

  view() {
    return (
      <div>
        <p className="seo-intro">
          A quick SEO-health-check overview. If you have questions, ask your question the official{' '}
          <a href="https://discuss.flarum.org/d/18316-flarum-seo" target="_blank">
            Flarum forums <i className="fas fa-external-link-alt" />
          </a>
          . When you have issues,{' '}
          <a href="https://github.com/FriendsOfFlarum/seo/issues" target="_blank">
            create a new issue <i className="fas fa-external-link-alt" />
          </a>
          .
        </p>
        <p className="seo-intro">
          Are you a developer with some free time left? Contribute to the project{' '}
          <a href="https://github.com/FriendsOfFlarum/seo" target="_blank">
            on GitHub <i className="fas fa-external-link-alt" />
          </a>
          . Have you have built a Flarum Extension and you'd like to use the SEO tools from this extension? Please{' '}
          <a href="https://community.v17.dev/knowledgebase/22" target="_blank">
            read the documentation <i className="fas fa-external-link-alt" />
          </a>
          .
        </p>

        <p className="seo-intro">{app.translator.trans('fof-seo.admin.pages.health.legend')}</p>

        <table className="seo-check-table">
          <thead>
            <tr>
              <td>{app.translator.trans('fof-seo.admin.pages.health.table.technique')}</td>
              <td width="150">{app.translator.trans('fof-seo.admin.pages.health.table.status')}</td>
            </tr>
          </thead>
          <tbody>
            {this.forumDescription()}
            {this.forumKeywords()}
            {this.siteUsesSSL()}
            {this.discussionPostSet()}
            {this.socialMediaImage()}
            {this.hasSitemap()}
            {this.registeredSearchEngines()}
            {this.robotsTxt()}
            {this.tagsAvailable()}
            {this.reviewAgain()}
          </tbody>
        </table>
      </div>
    );
  }

  forumDescription() {
    const description = this.settings.forum_description;
    let passed: PassState = typeof description !== 'undefined' && description !== '' ? true : 'must';
    let reason: Mithril.Children = app.translator.trans('fof-seo.admin.pages.health.checks.description.reason_missing');

    if (passed === true && description!.length <= 20) {
      passed = false;
      // TODO(i18n): two-sentence reason, leaving untranslated for now.
      reason = 'Your forum description is lower then 20 characters. Please expand it for better search results.';
    }

    if (passed === true && description!.indexOf('This is beta software') >= 0) {
      passed = 'must';
      reason = app.translator.trans('fof-seo.admin.pages.health.checks.description.reason_default');
    }

    return (
      <tr>
        <td>
          {app.translator.trans('fof-seo.admin.pages.health.checks.description.label')}
          {this.notPassedError(
            passed,
            reason,
            app.translator.trans('fof-seo.admin.pages.health.checks.description.button'),
            this.getSettingUrl('description')
          )}
        </td>
        {this.passed(passed)}
      </tr>
    );
  }

  forumKeywords() {
    const keywords = this.settings.forum_keywords;
    const passed: PassState = typeof keywords !== 'undefined' && keywords !== '';
    const reason = app.translator.trans('fof-seo.admin.pages.health.checks.keywords.reason');

    return (
      <tr>
        <td>
          {app.translator.trans('fof-seo.admin.pages.health.checks.keywords.label')}
          {this.notPassedError(
            passed,
            reason,
            app.translator.trans('fof-seo.admin.pages.health.checks.keywords.button'),
            this.getSettingUrl('keywords')
          )}
        </td>
        {this.passed(passed)}
      </tr>
    );
  }

  siteUsesSSL() {
    const passed: PassState = app.forum.attribute<string>('baseUrl').indexOf('https://') >= 0 ? true : 'must';

    return (
      <tr>
        <td>
          {app.translator.trans('fof-seo.admin.pages.health.checks.ssl.label')}
          {this.notPassedError(
            passed,
            // TODO(i18n): multi-sentence reason, leaving untranslated for now.
            "Your forum does not force a SSL/TLS connection (a secure connection to your website). Most search engines won't index your website or lower your ranking if you have no secure connection available.",
            app.translator.trans('fof-seo.admin.pages.health.checks.ssl.button'),
            app.route('extension', {
              id: 'fof-seo',
              page: 'ssl',
            })
          )}
        </td>
        {this.passed(passed)}
      </tr>
    );
  }

  discussionPostSet() {
    const passed: PassState = typeof this.settings.seo_reviewed_post_crawler !== 'undefined';

    return (
      <tr>
        <td>
          {app.translator.trans('fof-seo.admin.pages.health.checks.crawl.label')}
          {this.notPassedError(
            passed,
            app.translator.trans('fof-seo.admin.pages.health.checks.crawl.reason'),
            app.translator.trans('fof-seo.admin.pages.health.checks.crawl.button'),
            this.getSettingUrl('discussion-post')
          )}
        </td>
        {this.passed(passed)}
      </tr>
    );
  }

  socialMediaImage() {
    const path = this.settings.seo_social_media_image_path;
    const passed: PassState = !(typeof path === 'undefined' || path === null);

    return (
      <tr>
        <td>
          {app.translator.trans('fof-seo.admin.pages.health.checks.social_media.label')}
          {this.notPassedError(
            passed,
            // TODO(i18n): multi-sentence reason, leaving untranslated for now.
            'You did not set a social media image for your forum. It is recommended to set one. Your favicon will now be used as preview on social media.',
            app.translator.trans('fof-seo.admin.pages.health.checks.social_media.button'),
            this.getSettingUrl('social-media')
          )}
        </td>
        {this.passed(passed)}
      </tr>
    );
  }

  hasSitemap() {
    const enabled = app.data.settings.extensions_enabled || '';
    const passed: PassState = enabled.indexOf('flagrow-sitemap') !== -1 || enabled.indexOf('fof-sitemap') !== -1;

    return (
      <tr>
        <td>
          {app.translator.trans('fof-seo.admin.pages.health.checks.sitemap.label')}
          {this.notPassedError(
            passed,
            app.translator.trans('fof-seo.admin.pages.health.checks.sitemap.reason'),
            app.translator.trans('fof-seo.admin.pages.health.checks.sitemap.button'),
            app.route('extension', {
              id: 'fof-seo',
              page: 'sitemap',
            })
          )}
        </td>
        {this.passed(passed)}
      </tr>
    );
  }

  robotsTxt() {
    return (
      <tr>
        <td>
          {/* TODO(i18n): sentence contains inline <b>, leaving untranslated. */}
          Your forum has a <b>robots.txt</b> available.{' '}
          <a href={app.forum.attribute<string>('baseUrl') + '/robots.txt'} target="_blank" className="robots-link">
            {app.translator.trans('fof-seo.admin.pages.health.checks.robots.open_link')} <i className="fas fa-external-link-alt"></i>
          </a>
        </td>
        {this.passed(true)}
      </tr>
    );
  }

  tagsAvailable() {
    return (
      <tr>
        <td>
          {app.translator.trans('fof-seo.admin.pages.health.checks.meta_tags.label')}
        </td>
        {this.passed(true)}
      </tr>
    );
  }

  registeredSearchEngines() {
    const passed: PassState = typeof this.settings.seo_reviewed_search_engines !== 'undefined';

    return (
      <tr>
        <td>
          {app.translator.trans('fof-seo.admin.pages.health.checks.search_engines.label')}
          {this.notPassedError(
            passed,
            app.translator.trans('fof-seo.admin.pages.health.checks.search_engines.reason'),
            app.translator.trans('fof-seo.admin.pages.health.checks.search_engines.button'),
            app.route('extension', {
              id: 'fof-seo',
              page: 'search-engines',
            })
          )}
        </td>
        {this.passed(passed)}
      </tr>
    );
  }

  reviewAgain() {
    let passed: PassState = true;

    let nextReviewDate = new Date();

    const stored = app.data.settings.seo_review_settings;
    if (typeof stored === 'undefined') {
      passed = false;
    } else {
      nextReviewDate = new Date(Number(stored) * 1000);
    }

    if (passed && Math.floor(Date.now() / 1000) > Number(stored)) {
      passed = false;
    }

    return (
      <tr>
        <td>
          {/* TODO(i18n): two-sentence label with embedded <b>date</b>, leaving untranslated. */}
          Review your SEO settings every two months. Next review needed on <b>{nextReviewDate.toDateString()}</b>
          {this.notPassedError(
            passed,
            app.translator.trans('fof-seo.admin.pages.health.checks.review.reason'),
            app.translator.trans('fof-seo.admin.pages.health.checks.review.button'),
            () => {
              const now = new Date();
              const nextDate = Math.floor(new Date(now.getFullYear(), now.getMonth() + 2, 1).getTime() / 1000);

              this.saveSingleSetting('seo_review_settings', nextDate);
            }
          )}
        </td>
        {this.passed(passed)}
      </tr>
    );
  }

  getSettingUrl(setting: string = ''): string {
    if (setting === '') {
      return app.route('extension', {
        id: 'fof-seo',
      });
    }

    return app.route('extension', {
      id: 'fof-seo',
      page: 'settings',
      setting: setting,
    });
  }

  passed(passed: PassState): Mithril.Children {
    if (passed === 'must') {
      return (
        <td className="row-must">
          <i class="fas fa-exclamation-circle" /> {app.translator.trans('fof-seo.admin.pages.health.status.warning')}
        </td>
      );
    }

    if (!passed) {
      return (
        <td className="row-warning">
          <i class="fas fa-exclamation-circle" /> {app.translator.trans('fof-seo.admin.pages.health.status.warning')}
        </td>
      );
    }

    return (
      <td className="row-passed">
        <i class="fas fa-check" /> {app.translator.trans('fof-seo.admin.pages.health.status.passed')}
      </td>
    );
  }

  notPassedError(
    passed: PassState,
    reason: Mithril.Children,
    buttonText: Mithril.Children = app.translator.trans('fof-seo.admin.pages.health.default_button'),
    url: string | (() => void) = app.route('seoSettings')
  ): Mithril.Children {
    if (passed === true) return null;

    return (
      <div className="row-not-passed-error">
        {reason}

        <div className="button-container">
          <Button
            className="Button"
            onclick={() => {
              if (typeof url === 'string') {
                m.route.set(url);
              } else {
                url();
              }
            }}
          >
            {buttonText}
          </Button>
        </div>
      </div>
    );
  }

  saveSingleSetting(setting: string, value: unknown) {
    if (this.saving) return;

    this.saving = true;

    saveSettings({ [setting]: value })
      .then(() => {
        app.alerts.show({ type: 'success' }, app.translator.trans('core.admin.settings.saved_message'));
      })
      .catch(() => {})
      .then(() => {
        this.saving = false;
        m.redraw();
      });
  }
}
