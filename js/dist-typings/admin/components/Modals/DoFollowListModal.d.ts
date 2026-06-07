import { IFormModalAttrs } from 'flarum/common/components/FormModal';
import FormModal from 'flarum/common/components/FormModal';
import Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';
export default class DoFollowListModal extends FormModal<IFormModalAttrs> {
    domainDoFollowList: Stream<string[]>;
    startValue: Stream<string[]>;
    newDomain: Stream<string>;
    baseUrl: string;
    hasChanges: boolean;
    loading: boolean;
    oninit(vnode: Mithril.Vnode<IFormModalAttrs, this>): void;
    title(): string | any[];
    className(): string;
    getDomainFromBase(): string;
    content(): JSX.Element;
    addDomain(): void;
    removeDomain(key: number): void;
    updateDomain(key: number, value: string): void;
    onsubmit(e: SubmitEvent): void;
    onsaved(): void;
}
