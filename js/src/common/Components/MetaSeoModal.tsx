import app from 'flarum/common/app';
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';
import Stream from 'flarum/common/utils/Stream';
import Alert from 'flarum/common/components/Alert';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type Mithril from 'mithril';
import clsx from 'clsx';

import SeoMeta, { SeoImageSource } from '../Models/SeoMeta';
import countKeywords from '../../admin/utils/countKeywords';

declare const require: (id: string) => any;

export interface MetaSeoModalAttrs extends IInternalModalAttrs {
  object?: {
    seoMeta?: () => SeoMeta;
  };
  objectType?: string;
  objectId?: string | number;
}

export default class MetaSeoModal extends Modal<MetaSeoModalAttrs> {
  initialized = true;
  initialLoading = false;
  loading = false;
  hasChanges = false;
  closeText: Mithril.Children = app.translator.trans('fof-seo.forum.meta_seo.close.close');
  closeInfoText: Mithril.Children = null;

  enableCustomTwitter = false;
  enableCustomOpenGraph = false;
  wasManaged = true;
  seoTagsOpened = false;

  meta?: SeoMeta;

  autoUpdateData!: Stream<boolean>;
  metaTitle!: Stream<string | null>;
  description!: Stream<string | null>;
  keywords!: Stream<string | null>;
  robotsNoindex!: Stream<boolean>;
  robotsNofollow!: Stream<boolean>;
  robotsNoarchive!: Stream<boolean>;
  robotsNoimageindex!: Stream<boolean>;
  robotsNosnippet!: Stream<boolean>;
  twitterTitle!: Stream<string | null>;
  twitterDescription!: Stream<string | null>;
  twitterImage!: Stream<string | null>;
  twitterImageSource!: Stream<SeoImageSource>;
  openGraphTitle!: Stream<string | null>;
  openGraphDescription!: Stream<string | null>;
  openGraphImage!: Stream<string | null>;
  openGraphImageSource!: Stream<SeoImageSource>;
  estimatedReadingTime!: Stream<number | null>;
  createdAt!: Stream<Date | null>;
  updatedAt!: Stream<Date | null>;

  oninit(vnode: Mithril.Vnode<MetaSeoModalAttrs, this>) {
    super.oninit(vnode);

    if (this.attrs.object) {
      if (!this.attrs.object.seoMeta) {
        this.initialized = false;

        app.alerts.show(
          Alert,
          {
            type: 'error',
            title: app.translator.trans('fof-seo.forum.meta_seo.unsupported_object.title'),
            controls: [
              <a class="Button Button--link" href="https://community.v17.dev/knowledgebase/46" target="_blank">
                {app.translator.trans('fof-seo.forum.meta_seo.unsupported_object.docs_link')}
              </a>,
            ],
          },
          // TODO(i18n): multi-sentence error body, leaving untranslated for now.
          'Please open this dialog using the objectType and objectId properties or register the object relationship instead.'
        );

        setTimeout(() => this.hide(), 100);
        return;
      }

      this.meta = this.attrs.object.seoMeta();
    } else {
      this.initializeLoad();
    }

    this.initializeData();
  }

  initializeData() {
    if (!this.meta) return;

    const meta = this.meta;

    this.autoUpdateData = Stream(meta.autoUpdateData());
    this.wasManaged = meta.autoUpdateData() === true;

    this.metaTitle = Stream(meta.title());
    this.description = Stream(meta.description());
    this.keywords = Stream(meta.keywords());
    this.robotsNoindex = Stream(meta.robotsNoindex());
    this.robotsNofollow = Stream(meta.robotsNofollow());
    this.robotsNoarchive = Stream(meta.robotsNoarchive());
    this.robotsNoimageindex = Stream(meta.robotsNoimageindex());
    this.robotsNosnippet = Stream(meta.robotsNosnippet());
    this.twitterTitle = Stream(meta.twitterTitle());
    this.twitterDescription = Stream(meta.twitterDescription());
    this.twitterImage = Stream(meta.twitterImage());
    this.twitterImageSource = Stream(meta.twitterImageSource());
    this.openGraphTitle = Stream(meta.openGraphTitle());
    this.openGraphDescription = Stream(meta.openGraphDescription());
    this.openGraphImage = Stream(meta.openGraphImage());
    this.openGraphImageSource = Stream(meta.openGraphImageSource());
    this.estimatedReadingTime = Stream(meta.estimatedReadingTime());
    this.createdAt = Stream(meta.createdAt());
    this.updatedAt = Stream(meta.updatedAt());

    this.enableCustomTwitter = this.twitterTitle() !== null || this.twitterDescription() !== null || this.twitterImageSource() !== 'auto';

    this.enableCustomOpenGraph = this.openGraphTitle() !== null || this.openGraphDescription() !== null;
  }

  title() {
    return app.translator.trans('fof-seo.forum.meta_seo.title');
  }

  className() {
    return 'Modal Modal-SEO-settings';
  }

  initializeLoad() {
    this.initialLoading = true;

    app.store
      .find<SeoMeta>('seo_meta', `${this.attrs.objectType}-${this.attrs.objectId}`)
      .then((data) => {
        this.meta = data;
        this.initialLoading = false;
        this.initializeData();
      })
      .then(() => {
        m.redraw();
      });
  }

  content() {
    if (!this.initialized || this.initialLoading) {
      return (
        <div>
          <LoadingIndicator />
        </div>
      );
    }

    return (
      <div>
        <div className="Modal-body" onkeyup={() => this.updateHasChanges()}>
          <div className="Form">
            <div className="SeoItemContainer">
              <div className="SeoItemInfo">
                <div class="SeoItemInfo-title">{app.translator.trans('fof-seo.forum.meta_seo.auto_update.label')}</div>
                <div className="helpText">{app.translator.trans('fof-seo.forum.meta_seo.auto_update.help')}</div>
              </div>
              <div className="SeoItemContent">
                <div className="ManagedContainer">
                  <Switch
                    state={this.autoUpdateData()}
                    onchange={(value: boolean) => {
                      this.autoUpdateData(value);
                      this.updateHasChanges();
                    }}
                  >
                    {app.translator.trans('fof-seo.forum.meta_seo.auto_update.switch')}
                  </Switch>
                </div>
              </div>
            </div>

            <div className="SeoItemContainer">
              <div className="SeoItemInfo">
                <div class="SeoItemInfo-title">{app.translator.trans('fof-seo.forum.meta_seo.meta_title.label')}</div>
                <div className="helpText">{app.translator.trans('fof-seo.forum.meta_seo.meta_title.help')}</div>
              </div>

              <div className="SeoItemContent">
                <div className="ManagedContainer">
                  <input
                    className="FormControl"
                    bidi={this.metaTitle}
                    placeholder={app.translator.trans('fof-seo.forum.meta_seo.meta_title.placeholder') as unknown as string}
                    disabled={this.autoUpdateData()}
                  />

                  {this.autoUpdateData() && (
                    <div className="ManagedText">
                      <i className="fas fa-check" /> {app.translator.trans('fof-seo.forum.meta_seo.managed')}
                    </div>
                  )}
                </div>
              </div>
            </div>

            <div className="SeoItemContainer">
              <div className="SeoItemInfo">
                <div class="SeoItemInfo-title">{app.translator.trans('fof-seo.forum.meta_seo.meta_description.label')}</div>
                <div className="helpText">{app.translator.trans('fof-seo.forum.meta_seo.meta_description.help')}</div>
              </div>

              <div className="SeoItemContent">
                <div className="ManagedContainer">
                  <textarea
                    className="FormControl"
                    bidi={this.description}
                    placeholder={app.translator.trans('fof-seo.forum.meta_seo.keywords.placeholder') as unknown as string}
                    disabled={this.autoUpdateData()}
                  />

                  {this.autoUpdateData() && (
                    <div className="ManagedText">
                      <i className="fas fa-check" /> {app.translator.trans('fof-seo.forum.meta_seo.managed')}
                    </div>
                  )}
                </div>
              </div>
            </div>

            <div className="SeoItemContainer">
              <div className="SeoItemInfo">
                <div class="SeoItemInfo-title">{app.translator.trans('fof-seo.forum.meta_seo.keywords.label')}</div>
                <div className="helpText">{app.translator.trans('fof-seo.forum.meta_seo.keywords.help')}</div>
              </div>

              <div className="SeoItemContent">
                <textarea
                  className="FormControl"
                  bidi={this.keywords}
                  placeholder={app.translator.trans('fof-seo.forum.meta_seo.keywords.placeholder') as unknown as string}
                />
                <div className={clsx('SeoItemContent-helpertext', countKeywords(this.keywords() ?? '') == false && 'invalid')}>
                  <b>{app.translator.trans('fof-seo.forum.meta_seo.keywords.comma_note')}</b>{' '}
                  {app.translator.trans('fof-seo.forum.meta_seo.keywords.example')}
                </div>
              </div>
            </div>

            <div className="SeoItemContainer">
              <div className="SeoItemInfo">
                <div class="SeoItemInfo-title">{app.translator.trans('fof-seo.forum.meta_seo.image.label')}</div>
                <div className="helpText">{app.translator.trans('fof-seo.forum.meta_seo.image.help')}</div>
              </div>

              <div className="SeoItemContent">
                <div className="ManagedContainer">
                  <input
                    className="FormControl"
                    bidi={this.openGraphImage}
                    placeholder={app.translator.trans('fof-seo.forum.meta_seo.image.placeholder') as unknown as string}
                    disabled={this.autoUpdateData() && this.openGraphImageSource() === 'auto'}
                  />

                  {this.autoUpdateData() && this.openGraphImageSource() !== 'custom' && (
                    <div className="ManagedText">
                      <i className="fas fa-check" /> {app.translator.trans('fof-seo.forum.meta_seo.managed')}
                    </div>
                  )}

                  {!this.autoUpdateData() &&
                    this.returnFoFUploadButton((fileUrl: string) => {
                      this.openGraphImage(fileUrl);
                      this.openGraphImageSource('fof-upload');
                    })}

                  {this.openGraphImageSource() !== 'auto' && this.openGraphImageSource() !== 'custom' && (
                    <div className="SeoItemContent-helpertext">
                      {app.translator.trans('fof-seo.forum.meta_seo.image.managed_by', { source: this.openGraphImageSource() })}
                    </div>
                  )}
                </div>
              </div>
            </div>

            <div className="SeoItemContainer">
              <div className="SeoItemInfo">
                <div class="SeoItemInfo-title">{app.translator.trans('fof-seo.forum.meta_seo.robots.label')}</div>
                <div className="helpText">{app.translator.trans('fof-seo.forum.meta_seo.robots.help')}</div>
              </div>

              <div className="SeoItemContent">
                <div class={clsx('SeoTags-dropdown-container', this.seoTagsOpened && 'SeoTags-dropdown-open')}>
                  <div className="SeoTags" onclick={() => (this.seoTagsOpened = !this.seoTagsOpened)}>
                    {this.returnTag(
                      !this.robotsNoindex(),
                      app.translator.trans('fof-seo.forum.meta_seo.robots.tags.indexing_allowed'),
                      app.translator.trans('fof-seo.forum.meta_seo.robots.tags.indexing_not_allowed')
                    )}
                    {this.returnTag(
                      !this.robotsNofollow(),
                      app.translator.trans('fof-seo.forum.meta_seo.robots.tags.follow_allowed'),
                      app.translator.trans('fof-seo.forum.meta_seo.robots.tags.follow_not_allowed')
                    )}
                    {this.robotsNoarchive() &&
                      this.returnTag(false, '', app.translator.trans('fof-seo.forum.meta_seo.robots.tags.archive_not_allowed'))}
                    {this.robotsNoimageindex() &&
                      this.returnTag(false, '', app.translator.trans('fof-seo.forum.meta_seo.robots.tags.imageindex_not_allowed'))}
                    {this.robotsNosnippet() &&
                      this.returnTag(false, '', app.translator.trans('fof-seo.forum.meta_seo.robots.tags.snippet_not_allowed'))}
                  </div>

                  <div className="SeoTags-dropdown">
                    <Switch
                      state={!this.robotsNoindex()}
                      onchange={(value: boolean) => {
                        this.robotsNoindex(!value);
                        this.updateHasChanges();
                      }}
                    >
                      {app.translator.trans('fof-seo.forum.meta_seo.robots.switch.indexing')}
                    </Switch>
                    <Switch
                      state={!this.robotsNofollow()}
                      onchange={(value: boolean) => {
                        this.robotsNofollow(!value);
                        this.updateHasChanges();
                      }}
                    >
                      {app.translator.trans('fof-seo.forum.meta_seo.robots.switch.follow')}
                    </Switch>
                    <Switch
                      state={this.robotsNoarchive()}
                      onchange={(value: boolean) => {
                        this.robotsNoarchive(value);
                        this.updateHasChanges();
                      }}
                    >
                      {app.translator.trans('fof-seo.forum.meta_seo.robots.switch.noarchive')}
                    </Switch>
                    <Switch
                      state={this.robotsNoimageindex()}
                      onchange={(value: boolean) => {
                        this.robotsNoimageindex(value);
                        this.updateHasChanges();
                      }}
                    >
                      {app.translator.trans('fof-seo.forum.meta_seo.robots.switch.noimageindex')}
                    </Switch>
                    <Switch
                      state={this.robotsNosnippet()}
                      onchange={(value: boolean) => {
                        this.robotsNosnippet(value);
                        this.updateHasChanges();
                      }}
                    >
                      {app.translator.trans('fof-seo.forum.meta_seo.robots.switch.nosnippet')}
                    </Switch>
                  </div>
                </div>
              </div>
            </div>

            <div className="SeoItemContainer">
              <div className="SeoItemInfo">
                <div class="SeoItemInfo-title">{app.translator.trans('fof-seo.forum.meta_seo.reading_time.label')}</div>
                <div className="helpText">{app.translator.trans('fof-seo.forum.meta_seo.reading_time.help')}</div>
              </div>

              <div className="SeoItemContent">
                <div className="ManagedContainer">
                  <input
                    className="FormControl"
                    bidi={this.estimatedReadingTime}
                    placeholder={app.translator.trans('fof-seo.forum.meta_seo.reading_time.placeholder') as unknown as string}
                    type="number"
                    disabled={this.autoUpdateData()}
                  />

                  {this.autoUpdateData() && (
                    <div className="ManagedText">
                      <i className="fas fa-check" /> {app.translator.trans('fof-seo.forum.meta_seo.managed')}
                    </div>
                  )}
                </div>
              </div>
            </div>

            <div className="SeoItemContainer">
              <div className="SeoItemInfo">
                <div class="SeoItemInfo-title">{app.translator.trans('fof-seo.forum.meta_seo.twitter.label')}</div>
              </div>

              <div className="SeoItemContent">
                <div className="ManagedContainer">
                  <Switch
                    state={!this.enableCustomTwitter}
                    onchange={(value: boolean) => (this.enableCustomTwitter = !value)}
                    disabled={this.autoUpdateData()}
                  >
                    {app.translator.trans('fof-seo.forum.meta_seo.twitter.auto_switch')}
                  </Switch>

                  {this.autoUpdateData() && (
                    <div className="ManagedText">
                      <i className="fas fa-check" /> {app.translator.trans('fof-seo.forum.meta_seo.managed')}
                    </div>
                  )}
                </div>
              </div>
            </div>

            {this.enableCustomTwitter && (
              <div className="SeoItemContainer">
                <div className="SeoItemInfo">
                  <div class="SeoItemInfo-title">{app.translator.trans('fof-seo.forum.meta_seo.twitter.title')}</div>
                </div>

                <div className="SeoItemContent">
                  <div className="ManagedContainer">
                    <input className="FormControl" bidi={this.twitterTitle} placeholder={this.metaTitle() ?? ''} disabled={this.autoUpdateData()} />
                  </div>
                </div>
              </div>
            )}

            {this.enableCustomTwitter && (
              <div className="SeoItemContainer">
                <div className="SeoItemInfo">
                  <div class="SeoItemInfo-title">{app.translator.trans('fof-seo.forum.meta_seo.twitter.description')}</div>
                </div>

                <div className="SeoItemContent">
                  <div className="ManagedContainer">
                    <textarea
                      className="FormControl"
                      bidi={this.twitterDescription}
                      placeholder={this.description() ?? ''}
                      disabled={this.autoUpdateData()}
                    />
                  </div>
                </div>
              </div>
            )}

            {this.enableCustomTwitter && (
              <div className="SeoItemContainer">
                <div className="SeoItemInfo">
                  <div class="SeoItemInfo-title">{app.translator.trans('fof-seo.forum.meta_seo.twitter.image.label')}</div>
                  <div className="helpText">{app.translator.trans('fof-seo.forum.meta_seo.twitter.image.help')}</div>
                </div>

                <div className="SeoItemContent">
                  <div className="ManagedContainer">
                    <input
                      className="FormControl"
                      bidi={this.twitterImage}
                      placeholder={this.openGraphImage() ?? (app.translator.trans('fof-seo.forum.meta_seo.image.placeholder') as unknown as string)}
                      disabled={this.autoUpdateData() && this.twitterImage() === 'auto'}
                    />

                    {this.returnFoFUploadButton((fileUrl: string) => {
                      this.twitterImage(fileUrl);
                      this.twitterImageSource('fof-upload');
                    })}

                    {this.twitterImageSource() !== 'auto' && this.twitterImageSource() !== 'custom' && (
                      <div className="SeoItemContent-helpertext">
                        {app.translator.trans('fof-seo.forum.meta_seo.image.managed_by', { source: this.twitterImageSource() })} -{' '}
                        <a
                          href="#"
                          onclick={(e: Event) => {
                            e.preventDefault();

                            this.twitterImage(null);
                            this.twitterImageSource('auto');
                            this.updateHasChanges();
                          }}
                        >
                          {app.translator.trans('fof-seo.forum.meta_seo.twitter.image.reset')}
                        </a>
                      </div>
                    )}
                  </div>
                </div>
              </div>
            )}

            <div className="SeoItemContainer">
              <div className="SeoItemInfo">
                <div class="SeoItemInfo-title">{app.translator.trans('fof-seo.forum.meta_seo.og.label')}</div>
              </div>

              <div className="SeoItemContent">
                <div className="ManagedContainer">
                  <Switch
                    state={!this.enableCustomOpenGraph}
                    onchange={(value: boolean) => (this.enableCustomOpenGraph = !value)}
                    disabled={this.autoUpdateData()}
                  >
                    {app.translator.trans('fof-seo.forum.meta_seo.og.auto_switch')}
                  </Switch>

                  {this.autoUpdateData() && (
                    <div className="ManagedText">
                      <i className="fas fa-check" /> {app.translator.trans('fof-seo.forum.meta_seo.managed')}
                    </div>
                  )}
                </div>
              </div>
            </div>

            {this.enableCustomOpenGraph && (
              <div className="SeoItemContainer">
                <div className="SeoItemInfo">
                  <div class="SeoItemInfo-title">{app.translator.trans('fof-seo.forum.meta_seo.og.title')}</div>
                </div>

                <div className="SeoItemContent">
                  <div className="ManagedContainer">
                    <input className="FormControl" bidi={this.openGraphTitle} placeholder={this.metaTitle() ?? ''} disabled={this.autoUpdateData()} />
                  </div>
                </div>
              </div>
            )}

            {this.enableCustomOpenGraph && (
              <div className="SeoItemContainer">
                <div className="SeoItemInfo">
                  <div class="SeoItemInfo-title">{app.translator.trans('fof-seo.forum.meta_seo.og.description.label')}</div>
                </div>

                <div className="SeoItemContent">
                  <div className="ManagedContainer">
                    <textarea
                      className="FormControl"
                      bidi={this.openGraphDescription}
                      placeholder={app.translator.trans('fof-seo.forum.meta_seo.og.description.placeholder') as unknown as string}
                      disabled={this.autoUpdateData()}
                    />
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>
        <div style="padding: 25px 30px; text-align: center;">
          {this.closeInfoText && (
            <div style="margin-bottom: 15px; font-size: 12px;">
              <b>{app.translator.trans('fof-seo.forum.meta_seo.note_prefix')}</b> {this.closeInfoText}
            </div>
          )}
          {this.closeDialogButton()}
        </div>
      </div>
    );
  }

  returnFoFUploadButton(onSelect: (fileUrl: string) => void): Mithril.Children {
    if (!('fof-upload' in (flarum as any).extensions) || !app.forum.attribute('fof-upload.canUpload')) {
      return null;
    }

    const {
      components: { Uploader, FileManagerModal },
    } = require('@fof-upload');

    const uploader = new Uploader();

    return (
      <Button
        class="UploadButton Button"
        onclick={async () => {
          app.modal.show(
            FileManagerModal,
            {
              uploader: uploader,
              onSelect: (files: Array<string | number>) => {
                const file = app.store.getById<any>('files', files[0] as any);

                onSelect(file.url());
                this.updateHasChanges();
              },
            },
            true
          );
        }}
      >
        {app.translator.trans('fof-seo.forum.meta_seo.image.upload')}
      </Button>
    );
  }

  returnTag(isEnabled: boolean, enabledText: Mithril.Children, disabledText: Mithril.Children) {
    return <div className={clsx('SeoTag', !isEnabled && 'SeoTagDisabled')}>{isEnabled ? enabledText : disabledText}</div>;
  }

  closeDialogButton() {
    return (
      <Button type="submit" className="Button Button--primary" loading={this.loading}>
        {this.closeText}
      </Button>
    );
  }

  updateHasChanges() {
    this.closeText = app.translator.trans(
      !this.wasManaged && this.autoUpdateData() ? 'fof-seo.forum.meta_seo.close.save_autofill' : 'fof-seo.forum.meta_seo.close.save'
    );

    if (!this.wasManaged && this.autoUpdateData()) {
      this.closeInfoText = app.translator.trans('fof-seo.forum.meta_seo.close.autofill_info');
    }

    this.hasChanges = true;
  }

  submitData(): Record<string, any> {
    const data: Record<string, any> = {};

    data.autoUpdateData = this.autoUpdateData();

    data.title = this.metaTitle();
    data.description = this.description();

    if (this.keywords() !== '') {
      data.keywords = this.keywords() ?? null;
    }

    data.robotsNoindex = this.robotsNoindex();
    data.robotsNofollow = this.robotsNofollow();
    data.robotsNoarchive = this.robotsNoarchive();
    data.robotsNoimageindex = this.robotsNoimageindex();
    data.robotsNosnippet = this.robotsNosnippet();

    if (this.twitterTitle() !== '') {
      data.twitterTitle = this.twitterTitle() ?? null;
    }

    if (this.twitterDescription() !== '') {
      data.twitterDescription = this.twitterDescription() ?? null;
    }

    if (this.twitterImage() !== '') {
      data.twitterImage = this.twitterImage();
    }

    if (this.twitterImageSource() !== 'auto') {
      data.twitterImageSource = this.twitterImageSource() ?? null;
    }

    if (this.openGraphTitle() !== '') {
      data.openGraphTitle = this.openGraphTitle() ?? null;
    }

    if (this.openGraphDescription() !== '') {
      data.openGraphDescription = this.openGraphDescription() ?? null;
    }

    if (this.openGraphImage() !== '') {
      data.openGraphImage = this.openGraphImage() ?? null;
    }

    if (this.openGraphImageSource() !== 'auto') {
      data.openGraphImageSource = this.openGraphImageSource() ?? null;
    }

    const ert = this.estimatedReadingTime();
    if (ert !== null && (ert as unknown as string) !== '') {
      data.estimatedReadingTime = ert;
    }

    return data;
  }

  onsubmit(e: SubmitEvent) {
    e.preventDefault();

    if (!this.hasChanges) {
      this.hide();
      return;
    }

    this.loading = true;

    this.meta!.save(this.submitData())
      .then(() => {
        app.alerts.show({ type: 'success' }, app.translator.trans('fof-seo.forum.meta_seo.saved'));
        this.hide();
      })
      .catch((err: Error) => {
        console.log(err);
      })
      .then(() => {
        m.redraw();
      });
  }

  onsaved() {
    this.hide();
  }
}
