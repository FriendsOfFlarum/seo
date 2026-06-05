import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import FieldSet from 'flarum/common/components/FieldSet';
import Button from 'flarum/common/components/Button';
import LinkButton from 'flarum/common/components/LinkButton';
import Link from 'flarum/common/components/Link';
import Select from 'flarum/common/components/Select';
import Switch from 'flarum/common/components/Switch';
import UploadImageButton from 'flarum/admin/components/UploadImageButton';
import saveSettings from 'flarum/admin/utils/saveSettings';
import Stream from 'flarum/common/utils/Stream';
import ItemList from 'flarum/common/utils/ItemList';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';

import CrawlPostModal from '../Modals/CrawlPostModal';
import DoFollowListModal from '../Modals/DoFollowListModal';
import countKeywords from '../../utils/countKeywords';

const SETTING_FIELDS = ['forum_title', 'forum_description', 'forum_keywords', 'seo_twitter_card_size'] as const;

type SettingField = (typeof SETTING_FIELDS)[number];

export default class SeoSettings extends Component {
  saving: boolean = false;
  hasChanges: boolean = false;
  showField: string = 'all';

  values: Record<SettingField, Stream<string>> = {} as Record<SettingField, Stream<string>>;

  successAlert?: number;

  oninit(vnode: Mithril.Vnode<{}, this>) {
    super.oninit(vnode);

    const settings = app.data.settings;
    SETTING_FIELDS.forEach((key) => {
      this.values[key] = Stream<string>(settings[key] || '');
    });

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
          placeholder={extractText(app.translator.trans('fof-seo.admin.settings.keywords.placeholder'))}
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
        <div className="helpText">{app.translator.trans('fof-seo.admin.settings.twitter_card.help')}</div>
        <Select
          options={{
            large: extractText(app.translator.trans('fof-seo.admin.settings.twitter_card.option_large')),
            summary: extractText(app.translator.trans('fof-seo.admin.settings.twitter_card.option_summary')),
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
          {app.translator.trans('fof-seo.admin.settings.social_media_image.help_size')}
          <br />
          <br />
          {app.translator.trans('fof-seo.admin.settings.social_media_image.help_usage')}
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
      'noindexProfiles',
      <FieldSet
        label={app.translator.trans('fof-seo.admin.settings.indexing.heading')}
        className={this.showField !== 'all' ? 'hidden' : ''}
      >
        <div className="helpText">{app.translator.trans('fof-seo.admin.settings.indexing.profiles_help')}</div>
        <Switch
          state={app.data.settings.seo_noindex_profiles === '1'}
          loading={this.saving}
          onchange={(value: boolean) => this.saveSingleSetting('seo_noindex_profiles', value ? '1' : '0')}
        >
          {app.translator.trans('fof-seo.admin.settings.indexing.profiles_label')}
        </Switch>
      </FieldSet>,
      45
    );

    items.add(
      'noFollowLink',
      <FieldSet label={app.translator.trans('fof-seo.admin.settings.nofollow.heading')} className={this.showField !== 'all' ? 'hidden' : ''}>
        <div className="helpText">{app.translator.trans('fof-seo.admin.settings.nofollow.help', { i: <i /> })}</div>
        <div className="helpText">
          {app.translator.trans('fof-seo.admin.settings.nofollow.help_dofollow', { i: <i /> })}{' '}
          <Link external={true} href="https://community.v17.dev/knowledgebase/36" target="_blank">
            {app.translator.trans('fof-seo.admin.common.learn_more')}
          </Link>
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
        <div className="helpText">{app.translator.trans('fof-seo.admin.settings.new_tab.help')}</div>
      </FieldSet>,
      30
    );

    items.add(
      'updated',
      <FieldSet label={app.translator.trans('fof-seo.admin.settings.updated.heading')} className={this.showField === 'all' ? 'hidden' : ''}>
        <div className="helpText">{app.translator.trans('fof-seo.admin.settings.updated.help')}</div>
        <LinkButton className="Button" icon="fas fa-sync" loading={this.saving} href={app.route('extension', { id: 'fof-seo' })}>
          {app.translator.trans('fof-seo.admin.settings.updated.button')}
        </LinkButton>
      </FieldSet>,
      10
    );

    return items;
  }

  infoText(): Mithril.Children {
    if (this.showField !== 'all') return null;

    return (
      <div>
        <p>{app.translator.trans('fof-seo.admin.settings.info.overview')}</p>

        <p>{app.translator.trans('fof-seo.admin.settings.info.maintain')}</p>
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
