import Page from 'flarum/common/components/Page';
import type Mithril from 'mithril';
export default class RegisterToSearchEngines extends Page {
    saving: boolean;
    hasConfirmed: boolean;
    oninit(vnode: Mithril.Vnode<{}, this>): void;
    view(): JSX.Element;
    confirm(): void;
    saveSingleSetting(setting: string, value: unknown): void;
}
