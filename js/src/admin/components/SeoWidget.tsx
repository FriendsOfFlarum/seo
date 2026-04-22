import app from 'flarum/admin/app';
import DashboardWidget from 'flarum/admin/components/DashboardWidget';
import Button from 'flarum/common/components/Button';
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
        <i className="fas fa-check seo-check-icon"></i> It's time to review your SEO settings!
        {Button.component(
          {
            className: '',
            icon: 'far fa-thumbs-up',
            onclick: () => m.route.set('extension/fof-seo'),
          },
          'Do the health-check!'
        )}
      </div>
    );
  }
}
