/// <reference types="flarum/@types/translator-icu-rich" />
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import type Mithril from 'mithril';
export default class CrawlPostModal extends Modal<IInternalModalAttrs> {
    value: string | boolean;
    startValue: string | boolean;
    closeText: Mithril.Children;
    loading: boolean;
    oninit(vnode: Mithril.Vnode<IInternalModalAttrs, this>): void;
    title(): import("@askvortsov/rich-icu-message-formatter").NestedStringArray;
    className(): string;
    content(): JSX.Element;
    change(value: boolean): void;
    closeDialogButton(): JSX.Element;
    onsubmit(e: SubmitEvent): void;
    saveReviewedPostCrawler(): void;
    onsaved(): void;
}
