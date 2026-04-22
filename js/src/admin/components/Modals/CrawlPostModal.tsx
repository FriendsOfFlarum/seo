import app from 'flarum/admin/app';
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';
import saveSettings from 'flarum/admin/utils/saveSettings';
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
            <b>Read this dialog carefully.</b> This function will only be executed on a page refresh on a discussion. You can always change this
            option later.
            <div style="padding: 10px 0;">
              <b style="display: block; padding-bottom: 10px;">
                <span style="display: inline-block; width: 25px;">
                  <i className="fas fa-check"></i>
                </span>
                {app.translator.trans('fof-seo.admin.modals.crawl_post.mode_main_title')}
              </b>
              Search engine will only show the main post in the search results. It won't affect loading speed when you navigate to it via forum links.
            </div>
            <div style="padding: 10px 0;">
              <b style="display: block; padding-bottom: 10px;">
                <span style="display: inline-block; width: 25px;">
                  <i className="fas fa-check-double"></i>
                </span>{' '}
                {app.translator.trans('fof-seo.admin.modals.crawl_post.mode_all_title')}
              </b>
              Search engines will understand the discussions and are even able to show some relevant posts underneath the search results. When you
              have the extension '
              <a href="https://discuss.flarum.org/d/21894-friendsofflarum-best-answer" target="_blank">
                best answer
              </a>
              ' installed and enabled on your forum, it will mark the discussion as 'answered' on the search results and redirect the user to that
              specific post. <b>However, depending on your server settings, this can be heavier</b>. It may cost some performance, so it depends on
              how fast your server is to enable this feature.
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

    this.closeText = app.translator.trans(
      this.value !== this.startValue ? 'fof-seo.admin.common.save_changes' : 'fof-seo.admin.common.close'
    );
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
