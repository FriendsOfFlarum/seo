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

        <p className="seo-intro">For optimal search engine results, make sure all checks are green.</p>

        <table className="seo-check-table">
          <thead>
            <tr>
              <td>Technique</td>
              <td width="150">Status</td>
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
    let reason = 'You did not set up a forum description yet!';

    if (passed === true && description!.length <= 20) {
      passed = false;
      reason = 'Your forum description is lower then 20 characters. Please expand it for better search results.';
    }

    if (passed === true && description!.indexOf('This is beta software') >= 0) {
      passed = 'must';
      reason = 'You did not change the default forum description after installation!';
    }

    return (
      <tr>
        <td>
          Your forum has a description
          {this.notPassedError(passed, reason, 'Update description', this.getSettingUrl('description'))}
        </td>
        {this.passed(passed)}
      </tr>
    );
  }

  forumKeywords() {
    const keywords = this.settings.forum_keywords;
    const passed: PassState = typeof keywords !== 'undefined' && keywords !== '';
    const reason = 'You did not set up a forum keywords yet!';

    return (
      <tr>
        <td>
          Your forum has keywords set up
          {this.notPassedError(passed, reason, 'Update keywords', this.getSettingUrl('keywords'))}
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
          Your site has a secure connection available (SSL/TLS)
          {this.notPassedError(
            passed,
            "Your forum does not force a SSL/TLS connection (a secure connection to your website). Most search engines won't index your website or lower your ranking if you have no secure connection available.",
            'How to set up SSL',
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
          Review discussion post crawl settings
          {this.notPassedError(
            passed,
            'You will need to review this setting to pass.',
            'Review post settings',
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
          Set Up a social media image
          {this.notPassedError(
            passed,
            'You did not set a social media image for your forum. It is recommended to set one. Your favicon will now be used as preview on social media.',
            'Update image',
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
          Your forum has a sitemap available
          {this.notPassedError(
            passed,
            'It is highly recommended to install the FriendsOfFlarum sitemap extension!',
            'Read more about adding a sitemap',
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
          Your forum has a <b>robots.txt</b> available.{' '}
          <a href={app.forum.attribute<string>('baseUrl') + '/robots.txt'} target="_blank" className="robots-link">
            Open robots.txt <i className="fas fa-external-link-alt"></i>
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
          Your forum has <b>meta tags</b> available (generated by this plugin)
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
          Register your forum to search engines
          {this.notPassedError(
            passed,
            'You will need to review this to pass.',
            'More information',
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
          Review your SEO settings every two months. Next review needed on <b>{nextReviewDate.toDateString()}</b>
          {this.notPassedError(passed, 'It is time to re-review your SEO settings.', 'Ok! I reviewed them!', () => {
            const now = new Date();
            const nextDate = Math.floor(new Date(now.getFullYear(), now.getMonth() + 2, 1).getTime() / 1000);

            this.saveSingleSetting('seo_review_settings', nextDate);
          })}
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
          <i class="fas fa-exclamation-circle" /> Warning!
        </td>
      );
    }

    if (!passed) {
      return (
        <td className="row-warning">
          <i class="fas fa-exclamation-circle" /> Warning!
        </td>
      );
    }

    return (
      <td className="row-passed">
        <i class="fas fa-check" /> All set!
      </td>
    );
  }

  notPassedError(
    passed: PassState,
    reason: string,
    buttonText: string = 'Update setting',
    url: string | (() => void) = app.route('seoSettings')
  ): Mithril.Children {
    if (passed === true) return null;

    return (
      <div className="row-not-passed-error">
        {reason}

        <div className="button-container">
          {Button.component(
            {
              className: 'Button',
              onclick: () => {
                if (typeof url === 'string') {
                  m.route.set(url);
                } else {
                  url();
                }
              },
            },
            buttonText
          )}
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
