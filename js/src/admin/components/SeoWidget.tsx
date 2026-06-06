import app from 'flarum/admin/app';
import DashboardWidget from 'flarum/admin/components/DashboardWidget';
import LinkButton from 'flarum/common/components/LinkButton';
import Icon from 'flarum/common/components/Icon';
import type Mithril from 'mithril';

export default class SeoWidget extends DashboardWidget {
  needsReview = false;

  oninit(vnode: Mithril.Vnode<{}, this>) {
    super.oninit(vnode);

    const reviewAt = app.data.settings.seo_review_settings;

    if (typeof reviewAt === 'undefined') {
      this.needsReview = true;
    } else if (Math.floor(Date.now() / 1000) > Number(reviewAt)) {
      this.needsReview = true;
    }
  }

  className() {
    return 'SeoWidget ' + (this.needsReview ? 'needs-review' : '');
  }

  content() {
    return (
      <div>
        <Icon name="fas fa-check seo-check-icon" /> {app.translator.trans('fof-seo.admin.dashboard.widget.review_prompt')}
        <LinkButton className="" icon="far fa-thumbs-up" href={app.route('extension', { id: 'fof-seo' })}>
          {app.translator.trans('fof-seo.admin.dashboard.widget.cta')}
        </LinkButton>
      </div>
    );
  }
}
