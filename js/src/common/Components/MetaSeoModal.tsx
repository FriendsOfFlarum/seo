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
  closeText: string = 'Close';
  closeInfoText: string | null = null;

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
            title: 'This object is not a supported SeoMeta object',
            controls: [
              <a class="Button Button--link" href="https://community.v17.dev/knowledgebase/46" target="_blank">
                Documentation
              </a>,
            ],
          },
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
    return 'SEO settings - Meta';
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
      return <div>{LoadingIndicator.component({})}</div>;
    }

    return (
      <div>
        <div className="Modal-body" onkeyup={() => this.updateHasChanges()}>
          <div className="Form">
            <div className="SeoItemContainer">
              <div className="SeoItemInfo">
                <div class="SeoItemInfo-title">Auto update meta tags</div>
                <div className="helpText">When enabled, this items meta tags are automatically updated when the object changes.</div>
              </div>
              <div className="SeoItemContent">
                <div className="ManagedContainer">
                  {Switch.component(
                    {
                      state: this.autoUpdateData(),
                      onchange: (value: boolean) => {
                        this.autoUpdateData(value);
                        this.updateHasChanges();
                      },
                    },
                    'Update object SEO on change'
                  )}
                </div>
              </div>
            </div>

            <div className="SeoItemContainer">
              <div className="SeoItemInfo">
                <div class="SeoItemInfo-title">Meta title</div>
                <div className="helpText">Title in search engines.</div>
              </div>

              <div className="SeoItemContent">
                <div className="ManagedContainer">
                  <input className="FormControl" bidi={this.metaTitle} placeholder="Enter page title" disabled={this.autoUpdateData()} />

                  {this.autoUpdateData() && (
                    <div className="ManagedText">
                      <i className="fas fa-check" /> Managed
                    </div>
                  )}
                </div>
              </div>
            </div>

            <div className="SeoItemContainer">
              <div className="SeoItemInfo">
                <div class="SeoItemInfo-title"> Meta description</div>
                <div className="helpText">Describes the item and shown in search engines.</div>
              </div>

              <div className="SeoItemContent">
                <div className="ManagedContainer">
                  <textarea className="FormControl" bidi={this.description} placeholder="Add a few keywords" disabled={this.autoUpdateData()} />

                  {this.autoUpdateData() && (
                    <div className="ManagedText">
                      <i className="fas fa-check" /> Managed
                    </div>
                  )}
                </div>
              </div>
            </div>

            <div className="SeoItemContainer">
              <div className="SeoItemInfo">
                <div class="SeoItemInfo-title">Keywords</div>
                <div className="helpText">Enter one or more keywords that describes this item.</div>
              </div>

              <div className="SeoItemContent">
                <textarea className="FormControl" bidi={this.keywords} placeholder="Add a few keywords" />
                <div className={clsx('SeoItemContent-helpertext', countKeywords(this.keywords() ?? '') == false && 'invalid')}>
                  <b>Note: Separate keywords with a comma.</b> Example: <i>flarum, web development, forum, apples, security</i>
                </div>
              </div>
            </div>

            <div className="SeoItemContainer">
              <div className="SeoItemInfo">
                <div class="SeoItemInfo-title">Meta image</div>
                <div className="helpText">Displays an image.</div>
              </div>

              <div className="SeoItemContent">
                <div className="ManagedContainer">
                  <input
                    className="FormControl"
                    bidi={this.openGraphImage}
                    placeholder="Enter image URL"
                    disabled={this.autoUpdateData() && this.openGraphImageSource() === 'auto'}
                  />

                  {this.autoUpdateData() && this.openGraphImageSource() !== 'custom' && (
                    <div className="ManagedText">
                      <i className="fas fa-check" /> Managed
                    </div>
                  )}

                  {!this.autoUpdateData() &&
                    this.returnFoFUploadButton((fileUrl: string) => {
                      this.openGraphImage(fileUrl);
                      this.openGraphImageSource('fof-upload');
                    })}

                  {this.openGraphImageSource() !== 'auto' && this.openGraphImageSource() !== 'custom' && (
                    <div className="SeoItemContent-helpertext">Image source managed by {this.openGraphImageSource()}</div>
                  )}
                </div>
              </div>
            </div>

            <div className="SeoItemContainer">
              <div className="SeoItemInfo">
                <div class="SeoItemInfo-title">Robots</div>
                <div className="helpText">Robot-crawling settings for this item.</div>
              </div>

              <div className="SeoItemContent">
                <div class={clsx('SeoTags-dropdown-container', this.seoTagsOpened && 'SeoTags-dropdown-open')}>
                  <div className="SeoTags" onclick={() => (this.seoTagsOpened = !this.seoTagsOpened)}>
                    {this.returnTag(!this.robotsNoindex(), 'Allow indexing page', 'Page indexing not allowed')}
                    {this.returnTag(!this.robotsNofollow(), 'Allow follow links', 'Link following not allowed')}
                    {this.robotsNoarchive() && this.returnTag(false, '', 'Archiving pages not allowed')}
                    {this.robotsNoimageindex() && this.returnTag(false, '', 'Image indexing not allowed')}
                    {this.robotsNosnippet() && this.returnTag(false, '', 'Taking text-snippets not allowed')}
                  </div>

                  <div className="SeoTags-dropdown">
                    {Switch.component(
                      {
                        state: !this.robotsNoindex(),
                        onchange: (value: boolean) => {
                          this.robotsNoindex(!value);
                          this.updateHasChanges();
                        },
                      },
                      'Allow indexing page'
                    )}
                    {Switch.component(
                      {
                        state: !this.robotsNofollow(),
                        onchange: (value: boolean) => {
                          this.robotsNofollow(!value);
                          this.updateHasChanges();
                        },
                      },
                      'Allow following links to different pages'
                    )}
                    {Switch.component(
                      {
                        state: this.robotsNoarchive(),
                        onchange: (value: boolean) => {
                          this.robotsNoarchive(value);
                          this.updateHasChanges();
                        },
                      },
                      'Disable archiving page (noarchive)'
                    )}
                    {Switch.component(
                      {
                        state: this.robotsNoimageindex(),
                        onchange: (value: boolean) => {
                          this.robotsNoimageindex(value);
                          this.updateHasChanges();
                        },
                      },
                      'Disable indexing images on this page (noimageindex)'
                    )}
                    {Switch.component(
                      {
                        state: this.robotsNosnippet(),
                        onchange: (value: boolean) => {
                          this.robotsNosnippet(value);
                          this.updateHasChanges();
                        },
                      },
                      'Disable text-snippes on page (nosnippet)'
                    )}
                  </div>
                </div>
              </div>
            </div>

            <div className="SeoItemContainer">
              <div className="SeoItemInfo">
                <div class="SeoItemInfo-title">Estimated reading time</div>
                <div className="helpText">Estimated reading time in seconds.</div>
              </div>

              <div className="SeoItemContent">
                <div className="ManagedContainer">
                  <input
                    className="FormControl"
                    bidi={this.estimatedReadingTime}
                    placeholder="Reading time in seconds"
                    type="number"
                    disabled={this.autoUpdateData()}
                  />

                  {this.autoUpdateData() && (
                    <div className="ManagedText">
                      <i className="fas fa-check" /> Managed
                    </div>
                  )}
                </div>
              </div>
            </div>

            <div className="SeoItemContainer">
              <div className="SeoItemInfo">
                <div class="SeoItemInfo-title">Twitter card</div>
              </div>

              <div className="SeoItemContent">
                <div className="ManagedContainer">
                  {Switch.component(
                    {
                      state: !this.enableCustomTwitter,
                      onchange: (value: boolean) => (this.enableCustomTwitter = !value),
                      disabled: this.autoUpdateData(),
                    },
                    'Auto generate Twitter card'
                  )}

                  {this.autoUpdateData() && (
                    <div className="ManagedText">
                      <i className="fas fa-check" /> Managed
                    </div>
                  )}
                </div>
              </div>
            </div>

            {this.enableCustomTwitter && (
              <div className="SeoItemContainer">
                <div className="SeoItemInfo">
                  <div class="SeoItemInfo-title">Twitter title</div>
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
                  <div class="SeoItemInfo-title">Twitter description</div>
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
                  <div class="SeoItemInfo-title">Twitter image</div>
                  <div className="helpText">Displays an image on Twitter.</div>
                </div>

                <div className="SeoItemContent">
                  <div className="ManagedContainer">
                    <input
                      className="FormControl"
                      bidi={this.twitterImage}
                      placeholder={this.openGraphImage() ?? 'Enter image URL'}
                      disabled={this.autoUpdateData() && this.twitterImage() === 'auto'}
                    />

                    {this.returnFoFUploadButton((fileUrl: string) => {
                      this.twitterImage(fileUrl);
                      this.twitterImageSource('fof-upload');
                    })}

                    {this.twitterImageSource() !== 'auto' && this.twitterImageSource() !== 'custom' && (
                      <div className="SeoItemContent-helpertext">
                        Image source managed by {this.twitterImageSource()} -{' '}
                        <a
                          href="#"
                          onclick={(e: Event) => {
                            e.preventDefault();

                            this.twitterImage(null);
                            this.twitterImageSource('auto');
                            this.updateHasChanges();
                          }}
                        >
                          Reset image
                        </a>
                      </div>
                    )}
                  </div>
                </div>
              </div>
            )}

            <div className="SeoItemContainer">
              <div className="SeoItemInfo">
                <div class="SeoItemInfo-title">Open Graph tags</div>
              </div>

              <div className="SeoItemContent">
                <div className="ManagedContainer">
                  {Switch.component(
                    {
                      state: !this.enableCustomOpenGraph,
                      onchange: (value: boolean) => (this.enableCustomOpenGraph = !value),
                      disabled: this.autoUpdateData(),
                    },
                    'Auto generate Open Graph tags'
                  )}

                  {this.autoUpdateData() && (
                    <div className="ManagedText">
                      <i className="fas fa-check" /> Managed
                    </div>
                  )}
                </div>
              </div>
            </div>

            {this.enableCustomOpenGraph && (
              <div className="SeoItemContainer">
                <div className="SeoItemInfo">
                  <div class="SeoItemInfo-title">Open Graph title</div>
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
                  <div class="SeoItemInfo-title">Open Graph description</div>
                </div>

                <div className="SeoItemContent">
                  <div className="ManagedContainer">
                    <textarea
                      className="FormControl"
                      bidi={this.openGraphDescription}
                      placeholder="Custom Twitter description"
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
              <b>Note:</b> {this.closeInfoText}
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
        Upload file
      </Button>
    );
  }

  returnTag(isEnabled: boolean, enabledText: string, disabledText: string) {
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
    this.closeText = !this.wasManaged && this.autoUpdateData() ? 'Save & auto-fill' : 'Save';

    if (!this.wasManaged && this.autoUpdateData()) {
      this.closeInfoText = 'This change will revert custom changes and fill the meta-tags with item-data.';
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
        app.alerts.show({ type: 'success' }, 'Saved!');
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
