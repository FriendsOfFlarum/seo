import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import FieldSet from 'flarum/common/components/FieldSet';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';
import Select from 'flarum/common/components/Select';
import UploadImageButton from 'flarum/admin/components/UploadImageButton';
import saveSettings from 'flarum/admin/utils/saveSettings';
import Stream from 'flarum/common/utils/Stream';
import ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';

import CrawlPostModal from '../Modals/CrawlPostModal';
import RobotsModal from '../Modals/RobotsModal';
import DoFollowListModal from '../Modals/DoFollowListModal';
import countKeywords from '../../utils/countKeywords';

const SETTING_FIELDS = ['forum_title', 'forum_description', 'forum_keywords', 'seo_allow_all_bots', 'seo_twitter_card_size'] as const;

type SettingField = (typeof SETTING_FIELDS)[number];

export default class SeoSettings extends Component {
  saving: boolean = false;
  hasChanges: boolean = false;
  allowBotsValue: boolean = true;
  showField: string = 'all';

  values: Record<SettingField, Stream<string>> = {} as Record<SettingField, Stream<string>>;

  successAlert?: number;

  oninit(vnode: Mithril.Vnode<{}, this>) {
    super.oninit(vnode);

    const settings = app.data.settings;
    SETTING_FIELDS.forEach((key) => {
      this.values[key] = Stream<string>(settings[key] || '');
    });

    this.allowBotsValue = settings.seo_allow_all_bots !== '0';

    // Cheat 'seo_social_media_imageUrl'
    // Todo: Find a better way
    (app.forum.data.attributes as any).seo_social_media_imageUrl = app.data.settings.seo_social_media_image_url;

    const setting = m.route.param('setting');
    if (setting !== undefined) {
      this.showField = setting;
    }
  }

  view() {
    return (
      <div>
        {this.infoText()}

        <form onsubmit={this.onsubmit.bind(this)} className="BasicsPage">
          {this.viewItems().toArray()}
        </form>
      </div>
    );
  }

  viewItems(): ItemList<Mithril.Children> {
    const items = new ItemList<Mithril.Children>();

    const submitButton = (
      <Button type="submit" className="Button Button--primary" loading={this.saving} disabled={!this.changed()}>
        {app.translator.trans('core.admin.settings.submit_button')}
      </Button>
    );

    items.add(
      'description',
      <FieldSet
        label={app.translator.trans('core.admin.basics.forum_description_heading')}
        className={this.showField !== 'all' && this.showField !== 'description' ? 'hidden' : ''}
      >
        <div className="helpText">{app.translator.trans('core.admin.basics.forum_description_text')}</div>
        <textarea className="FormControl" bidi={this.values.forum_description} />
        {this.showField === 'description' && submitButton}
      </FieldSet>,
      100
    );

    items.add(
      'keywords',
      <FieldSet
        label={app.translator.trans('fof-seo.admin.settings.keywords.heading')}
        className={this.showField !== 'all' && this.showField !== 'keywords' ? 'hidden' : ''}
      >
        <div className="helpText">{app.translator.trans('fof-seo.admin.settings.keywords.help')}</div>
        <textarea
          className="FormControl"
          bidi={this.values.forum_keywords}
          placeholder={app.translator.trans('fof-seo.admin.settings.keywords.placeholder') as unknown as string}
        />
        <div
          className="helpText"
          style={{
            color: countKeywords(this.values.forum_keywords()) == false ? 'red' : undefined,
          }}
        >
          <b>{app.translator.trans('fof-seo.admin.settings.keywords.comma_note')}</b>{' '}
          {app.translator.trans('fof-seo.admin.settings.keywords.example')}
        </div>
        {this.showField === 'keywords' && submitButton}
      </FieldSet>,
      90
    );

    items.add(
      'twitterCardSize',
      <FieldSet label={app.translator.trans('fof-seo.admin.settings.twitter_card.heading')} className={this.showField !== 'all' ? 'hidden' : ''}>
        <div className="helpText">
          When your forum is shared on Twitter, it will have an image (if a social media image has been set up). This can be a big card with a big
          image, or a small card (summary) with a smaller image.
        </div>
        <Select
          options={{
            large: app.translator.trans('fof-seo.admin.settings.twitter_card.option_large') as unknown as string,
            summary: app.translator.trans('fof-seo.admin.settings.twitter_card.option_summary') as unknown as string,
          }}
          value={this.values.seo_twitter_card_size() || 'large'}
          onchange={(val: string) => {
            this.values.seo_twitter_card_size(val);
            this.hasChanges = true;
          }}
        />
        {submitButton}
      </FieldSet>,
      70
    );

    items.add(
      'socialMediaImage',
      <FieldSet
        label={app.translator.trans('fof-seo.admin.settings.social_media_image.heading')}
        className={'social-media-uploader ' + (this.showField !== 'all' && this.showField !== 'social-media' ? 'hidden' : '')}
      >
        <div className="helpText">
          Expecting a square image. Recommended size is 1200x1200 pixels. Otherwise use a landscape image, recommended size is 1200x630.
          <br />
          <br />
          This image will be used by Social Media when a user shares a page on your website (Facebook, Twitter, Reddit).
        </div>
        <UploadImageButton name="seo_social_media_image" />
      </FieldSet>,
      60
    );

    items.add(
      'crawlSettings',
      <FieldSet
        label={app.translator.trans('fof-seo.admin.settings.crawl.heading')}
        className={this.showField !== 'all' && this.showField !== 'discussion-post' ? 'hidden' : ''}
      >
        <div className="helpText">{app.translator.trans('fof-seo.admin.settings.crawl.help')}</div>
        <Button className="Button" onclick={() => app.modal.show(CrawlPostModal)}>
          {app.translator.trans('fof-seo.admin.settings.crawl.button')}
        </Button>
      </FieldSet>,
      50
    );

    items.add(
      'noFollowLink',
      <FieldSet label={app.translator.trans('fof-seo.admin.settings.nofollow.heading')} className={this.showField !== 'all' ? 'hidden' : ''}>
        <div className="helpText">
          All links to external domains will receive a '<i>nofollow</i>' attribute by default. This will make sure people do not spam your forum with
          links to other domains in order to get more referrals.
        </div>
        <div className="helpText">
          With this setting you are able to add domains to the 'do-follow' list. For example, you can add <i>flarum.org</i> to make sure links to this
          website do not receive a 'nofollow' attribute.{' '}
          <a href="https://community.v17.dev/knowledgebase/36" target="_blank">
            {app.translator.trans('fof-seo.admin.common.learn_more')}
          </a>
          .
        </div>
        <div style="height: 5px;"></div>
        <div>
          <Button className="Button" loading={this.saving} onclick={() => app.modal.show(DoFollowListModal)}>
            {app.translator.trans('fof-seo.admin.settings.nofollow.button')}
          </Button>
        </div>
      </FieldSet>,
      40
    );

    items.add(
      'linkTarget',
      <FieldSet label={app.translator.trans('fof-seo.admin.settings.new_tab.heading')} className={this.showField !== 'all' ? 'hidden' : ''}>
        <div className="helpText">
          This extension will also make sure that external links (to other domains) open in a new tab. Currently it is not possible to disable this
          setting.
        </div>
      </FieldSet>,
      30
    );

    items.add(
      'robots',
      <FieldSet
        label={app.translator.trans('fof-seo.admin.settings.robots.heading')}
        className={this.showField !== 'all' && this.showField !== 'robots' ? 'hidden' : ''}
      >
        <div className="helpText">
          You can edit your robot.txt here. Please note, writing nonsense could result that crawlers won't visit your site.
          <br />
          <br />
          When you've{' '}
          <a href="https://discuss.flarum.org/d/14941-fof-sitemap" target="_blank">
            FriendsOfFlarum Sitemap
          </a>{' '}
          installed and enabled, it will be automatically added to your robots.txt
        </div>
        <div style="height: 5px;"></div>
        <Switch state={this.allowBotsValue} onchange={(value: boolean) => this.saveAllowBots(value)}>
          {app.translator.trans('fof-seo.admin.settings.robots.allow_bots_switch')}
        </Switch>
        <div style="height: 5px;"></div>
        <div>
          <Button className="Button" loading={this.saving} onclick={() => app.modal.show(RobotsModal)}>
            {app.translator.trans('fof-seo.admin.settings.robots.edit_button')}
          </Button>{' '}
          <a href={app.forum.attribute<string>('baseUrl') + '/robots.txt'} target="_blank" className="robots-link">
            {app.translator.trans('fof-seo.admin.settings.robots.open_link')} <i className="fas fa-external-link-alt"></i>
          </a>
        </div>
      </FieldSet>,
      20
    );

    items.add(
      'updated',
      <FieldSet label={app.translator.trans('fof-seo.admin.settings.updated.heading')} className={this.showField === 'all' ? 'hidden' : ''}>
        <div className="helpText">{app.translator.trans('fof-seo.admin.settings.updated.help')}</div>
        <Button
          className="Button"
          icon="fas fa-sync"
          loading={this.saving}
          onclick={() => m.route.set(app.route('extension', { id: 'fof-seo' }))}
        >
          {app.translator.trans('fof-seo.admin.settings.updated.button')}
        </Button>
      </FieldSet>,
      10
    );

    return items;
  }

  infoText(): Mithril.Children {
    if (this.showField !== 'all') return null;

    return (
      <div>
        <p>
          This page contains some other settings from around the admin area. However, it's good to have a good overview about these settings. Do not
          forget to do the SEO check.
        </p>

        <p>Check all your settings when you first setup this extensions. Maintain them to get the best search results.</p>
      </div>
    );
  }

  changed(): boolean {
    return SETTING_FIELDS.some((key) => this.values[key]() !== app.data.settings[key]);
  }

  onsubmit(e: SubmitEvent) {
    e.preventDefault();

    if (this.saving) return;

    this.saving = true;
    if (this.successAlert) app.alerts.dismiss(this.successAlert);

    const settings: Record<string, string> = {};

    SETTING_FIELDS.forEach((key) => {
      settings[key] = this.values[key]();
    });

    if (settings.seo_twitter_card_size === '') {
      settings.seo_twitter_card_size = 'large';
    }

    saveSettings(settings)
      .then(() => app.alerts.show({ type: 'success' }, app.translator.trans('core.admin.settings.saved_message')))
      .catch(() => {})
      .then(() => {
        this.saving = false;
        m.redraw();
      });
  }

  saveAllowBots(value: boolean) {
    if (this.saving) return;

    this.saving = true;
    this.allowBotsValue = value;

    saveSettings({ seo_allow_all_bots: value })
      .then(() => app.alerts.show({ type: 'success' }, app.translator.trans('core.admin.settings.saved_message')))
      .catch(() => {})
      .then(() => {
        this.saving = false;
        m.redraw();
      });
  }

  saveSingleSetting(setting: string, value: unknown) {
    if (this.saving) return;

    this.saving = true;

    saveSettings({ [setting]: value })
      .then(() => app.alerts.show({ type: 'success' }, app.translator.trans('core.admin.settings.saved_message')))
      .catch(() => {})
      .then(() => {
        this.saving = false;
        m.redraw();
      });
  }
}
