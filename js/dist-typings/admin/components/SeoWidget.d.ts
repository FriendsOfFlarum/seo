import DashboardWidget from 'flarum/admin/components/DashboardWidget';
import type Mithril from 'mithril';
export default class SeoWidget extends DashboardWidget {
    needsReview: boolean;
    oninit(vnode: Mithril.Vnode<{}, this>): void;
    className(): string;
    content(): JSX.Element;
}
