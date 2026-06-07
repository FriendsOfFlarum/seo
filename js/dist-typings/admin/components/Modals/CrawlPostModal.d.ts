import { IFormModalAttrs } from 'flarum/common/components/FormModal';
import FormModal from 'flarum/common/components/FormModal';
import type Mithril from 'mithril';
export default class CrawlPostModal extends FormModal<IFormModalAttrs> {
    value: string | boolean;
    startValue: string | boolean;
    closeText: Mithril.Children;
    loading: boolean;
    oninit(vnode: Mithril.Vnode<IFormModalAttrs, this>): void;
    title(): string | any[];
    className(): string;
    content(): JSX.Element;
    change(value: boolean): void;
    closeDialogButton(): JSX.Element;
    onsubmit(e: SubmitEvent): void;
    saveReviewedPostCrawler(): void;
    onsaved(): void;
}
