import app from 'flarum/admin/app';
import DashboardWidget from 'flarum/admin/components/DashboardWidget';
import LinkButton from 'flarum/common/components/LinkButton';
import icon from 'flarum/common/helpers/icon';
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
        {icon('fas fa-check seo-check-icon')} {app.translator.trans('fof-seo.admin.dashboard.widget.review_prompt')}
        <LinkButton className="Button SeoWidget-cta" icon="far fa-thumbs-up" href={app.route('extension', { id: 'fof-seo' })}>
          {app.translator.trans('fof-seo.admin.dashboard.widget.cta')}
        </LinkButton>
      </div>
    );
  }
}
