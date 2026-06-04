import app from 'flarum/admin/app';
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';
import Link from 'flarum/common/components/Link';
import saveSettings from 'flarum/admin/utils/saveSettings';
import icon from 'flarum/common/helpers/icon';
import type Mithril from 'mithril';

export default class CrawlPostModal extends Modal<IInternalModalAttrs> {
  value: string | boolean = false;
  startValue: string | boolean = false;
  closeText: Mithril.Children = app.translator.trans('fof-seo.admin.common.close');
  loading: boolean = false;

  oninit(vnode: Mithril.Vnode<IInternalModalAttrs, this>) {
    super.oninit(vnode);

    const stored = app.data.settings.seo_post_crawler;
    this.value = typeof stored === 'undefined' ? false : stored;
    this.startValue = this.value;

    if (typeof app.data.settings.seo_reviewed_post_crawler === 'undefined') {
      this.saveReviewedPostCrawler();
    }
  }

  title() {
    return app.translator.trans('fof-seo.admin.modals.crawl_post.title');
  }

  className() {
    return 'Modal';
  }

  content() {
    return (
      <div>
        <div className="Modal-body">
          <div className="Form">
            {app.translator.trans('fof-seo.admin.modals.crawl_post.intro', { b: <b /> })}
            <div style="padding: 10px 0;">
              <b style="display: block; padding-bottom: 10px;">
                <span style="display: inline-block; width: 25px;">{icon('fas fa-check')}</span>
                {app.translator.trans('fof-seo.admin.modals.crawl_post.mode_main_title')}
              </b>
              {app.translator.trans('fof-seo.admin.modals.crawl_post.mode_main_help')}
            </div>
            <div style="padding: 10px 0;">
              <b style="display: block; padding-bottom: 10px;">
                <span style="display: inline-block; width: 25px;">{icon('fas fa-check-double')}</span>{' '}
                {app.translator.trans('fof-seo.admin.modals.crawl_post.mode_all_title')}
              </b>
              {app.translator.trans('fof-seo.admin.modals.crawl_post.mode_all_help', {
                a: <Link external={true} href="https://discuss.flarum.org/d/21894-friendsofflarum-best-answer" target="_blank" />,
                b: <b />,
              })}
            </div>
          </div>
        </div>
        <div style="padding: 25px 30px; text-align: center;">
          <b style="display: block; padding-bottom: 10px;">{app.translator.trans('fof-seo.admin.modals.crawl_post.question')}</b>

          <div style="display: inline-block;">
            <Switch state={this.value == '1'} onchange={(value: boolean) => this.change(value)}>
              {app.translator.trans('fof-seo.admin.modals.crawl_post.switch_label')}
            </Switch>
          </div>
        </div>
        <div style="padding: 25px 30px; text-align: center;">{this.closeDialogButton()}</div>
      </div>
    );
  }

  change(value: boolean) {
    this.value = value;

    this.closeText = app.translator.trans(this.value !== this.startValue ? 'fof-seo.admin.common.save_changes' : 'fof-seo.admin.common.close');
  }

  closeDialogButton() {
    return (
      <Button type="submit" className="Button Button--primary" loading={this.loading}>
        {this.closeText}
      </Button>
    );
  }

  onsubmit(e: SubmitEvent) {
    e.preventDefault();

    if (this.value === this.startValue) {
      this.hide();
      return;
    }

    this.loading = true;

    saveSettings({ seo_post_crawler: this.value }).then(this.onsaved.bind(this));
  }

  saveReviewedPostCrawler() {
    this.loading = true;

    saveSettings({ seo_reviewed_post_crawler: true }).then(() => {
      this.loading = false;
      m.redraw();
    });
  }

  onsaved() {
    this.hide();
  }
}
