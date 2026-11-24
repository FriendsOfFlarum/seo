import app from 'flarum/admin/app';
import Extend from 'flarum/common/extenders';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import UploadImageButton from 'flarum/common/components/UploadImageButton';
import extractText from 'flarum/common/utils/extractText';
import isExtensionEnabled from 'flarum/admin/utils/isExtensionEnabled';
import type Mithril from 'mithril';
import SettingsPage from './Pages/SettingsPage';
import NoindexTagsSetting from './components/Forms/NoindexTagsSetting';
import { SEO_PERMISSION_CATEGORY } from './permissions';

const field = (label: Mithril.Children, help: Mithril.Children, control?: Mithril.Children): Mithril.Children => (
  <div className="Form-group">
    <label>{label}</label>
    <div className="helpText">{help}</div>
    {control}
  </div>
);

const indexItem = (id: string, section: string, label: string, help: string) => ({
  id: `fof-seo-${id}`,
  section,
  label: extractText(app.translator.trans(label)),
  help: extractText(app.translator.trans(help)),
  link: app.route('extension', { id: 'fof-seo', tab: 'settings' }),
});

export default [
  new Extend.Admin() //
    .page(SettingsPage)
    .tab(() => ({ id: 'settings', label: app.translator.trans('fof-seo.admin.header.seo_settings'), icon: 'fas fa-cogs' }))
    .section(() => ({
      id: 'search-results',
      tab: 'settings',
      icon: 'fas fa-search',
      label: app.translator.trans('fof-seo.admin.sections.search_results.label'),
      description: app.translator.trans('fof-seo.admin.settings.info.overview'),
    }))
    .section(() => ({
      id: 'social',
      tab: 'settings',
      icon: 'fas fa-share-alt',
      label: app.translator.trans('fof-seo.admin.sections.social.label'),
    }))
    .section(() => ({
      id: 'indexing',
      tab: 'settings',
      icon: 'fas fa-robot',
      label: app.translator.trans('fof-seo.admin.settings.indexing.heading'),
    }))
    .section(() => ({
      id: 'links',
      tab: 'settings',
      icon: 'fas fa-link',
      label: app.translator.trans('fof-seo.admin.sections.links.label'),
    }))
    .setting(
      () => ({
        setting: 'forum_description',
        type: 'textarea',
        label: app.translator.trans('core.admin.basics.forum_description_heading'),
        help: app.translator.trans('core.admin.basics.forum_description_text'),
        section: 'search-results',
      }),
      100
    )
    .setting(
      () => ({
        setting: 'forum_keywords',
        type: 'textarea',
        label: app.translator.trans('fof-seo.admin.settings.keywords.heading'),
        help: [
          app.translator.trans('fof-seo.admin.settings.keywords.help'),
          ' ',
          app.translator.trans('fof-seo.admin.settings.keywords.comma_note'),
          ' ',
          app.translator.trans('fof-seo.admin.settings.keywords.example'),
        ],
        placeholder: extractText(app.translator.trans('fof-seo.admin.settings.keywords.placeholder')),
        section: 'search-results',
      }),
      90
    )
    .customSetting(
      () =>
        field(
          app.translator.trans('fof-seo.admin.settings.social_media_image.heading'),
          [
            app.translator.trans('fof-seo.admin.settings.social_media_image.help_size'),
            ' ',
            app.translator.trans('fof-seo.admin.settings.social_media_image.help_usage'),
          ],
          <div className="social-media-uploader">
            <UploadImageButton
              name="seo_social_media_image"
              routePath="seo_social_media_image"
              value={app.data.settings['seo_social_media_image_path']}
              url={app.forum.attribute('seo_social_media_imageUrl')}
            />
          </div>
        ),
      80,
      'social'
    )
    .setting(
      () => ({
        setting: 'seo_twitter_card_size',
        type: 'select',
        label: app.translator.trans('fof-seo.admin.settings.twitter_card.heading'),
        help: app.translator.trans('fof-seo.admin.settings.twitter_card.help'),
        options: {
          large: app.translator.trans('fof-seo.admin.settings.twitter_card.option_large'),
          summary: app.translator.trans('fof-seo.admin.settings.twitter_card.option_summary'),
        },
        default: 'large',
        section: 'social',
      }),
      70
    )
    .customSetting(
      () =>
        field(
          app.translator.trans('fof-seo.admin.settings.crawl.heading'),
          app.translator.trans('fof-seo.admin.settings.crawl.help'),
          <Button className="Button" onclick={() => app.modal.show(() => import('./components/Modals/CrawlPostModal'))}>
            {app.translator.trans('fof-seo.admin.settings.crawl.button')}
          </Button>
        ),
      60,
      'indexing'
    )
    .setting(
      () => ({
        setting: 'seo_noindex_profiles',
        type: 'switch',
        label: app.translator.trans('fof-seo.admin.settings.indexing.profiles_label'),
        help: app.translator.trans('fof-seo.admin.settings.indexing.profiles_help'),
        section: 'indexing',
      }),
      50
    )
    .customSetting(
      () =>
        isExtensionEnabled('flarum-tags')
          ? field(
              app.translator.trans('fof-seo.admin.settings.indexing.tags_label'),
              app.translator.trans('fof-seo.admin.settings.indexing.tags_help'),
              <NoindexTagsSetting />
            )
          : null,
      40,
      'indexing'
    )
    .customSetting(
      () =>
        field(
          app.translator.trans('fof-seo.admin.settings.nofollow.heading'),
          [
            app.translator.trans('fof-seo.admin.settings.nofollow.help', { i: <i /> }),
            ' ',
            app.translator.trans('fof-seo.admin.settings.nofollow.help_dofollow', { i: <i /> }),
            ' ',
            <Link external={true} href="https://community.v17.dev/knowledgebase/36" target="_blank">
              {app.translator.trans('fof-seo.admin.common.learn_more')}
            </Link>,
          ],
          <Button className="Button" onclick={() => app.modal.show(() => import('./components/Modals/DoFollowListModal'))}>
            {app.translator.trans('fof-seo.admin.settings.nofollow.button')}
          </Button>
        ),
      30,
      'links'
    )
    .customSetting(
      () => field(app.translator.trans('fof-seo.admin.settings.new_tab.heading'), app.translator.trans('fof-seo.admin.settings.new_tab.help')),
      20,
      'links'
    )
    // Custom settings aren't in the settings registry, so the admin search is
    // told about them here.
    .generalIndexItems('settings', () => [
      indexItem(
        'social-media-image',
        'social',
        'fof-seo.admin.settings.social_media_image.heading',
        'fof-seo.admin.settings.social_media_image.help_usage'
      ),
      indexItem('crawl', 'indexing', 'fof-seo.admin.settings.crawl.heading', 'fof-seo.admin.settings.crawl.help'),
      indexItem('noindex-tags', 'indexing', 'fof-seo.admin.settings.indexing.tags_label', 'fof-seo.admin.settings.indexing.tags_help'),
      indexItem('nofollow', 'links', 'fof-seo.admin.settings.nofollow.heading', 'fof-seo.admin.settings.nofollow.help_dofollow'),
    ])
    .permission(
      () => ({
        icon: 'fas fa-search',
        label: app.translator.trans('fof-seo.admin.permissions.configure_seo'),
        permission: 'fof-seo.canConfigure',
      }),
      SEO_PERMISSION_CATEGORY,
      90
    ),
];
