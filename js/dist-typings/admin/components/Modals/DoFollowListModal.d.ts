/// <reference types="flarum/@types/translator-icu-rich" />
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';
export default class DoFollowListModal extends Modal<IInternalModalAttrs> {
    domainDoFollowList: Stream<string[]>;
    startValue: Stream<string[]>;
    newDomain: Stream<string>;
    baseUrl: string;
    hasChanges: boolean;
    loading: boolean;
    oninit(vnode: Mithril.Vnode<IInternalModalAttrs, this>): void;
    title(): import("@askvortsov/rich-icu-message-formatter").NestedStringArray;
    className(): string;
    getDomainFromBase(): string;
    content(): JSX.Element;
    addDomain(): void;
    removeDomain(key: number): void;
    updateDomain(key: number, value: string): void;
    onsubmit(e: SubmitEvent): void;
    onsaved(): void;
}
