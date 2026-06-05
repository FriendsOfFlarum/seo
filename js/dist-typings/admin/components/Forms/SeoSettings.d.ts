import Component from 'flarum/common/Component';
import Stream from 'flarum/common/utils/Stream';
import ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';
declare const SETTING_FIELDS: readonly ["forum_title", "forum_description", "forum_keywords", "seo_twitter_card_size"];
type SettingField = (typeof SETTING_FIELDS)[number];
export default class SeoSettings extends Component {
    saving: boolean;
    hasChanges: boolean;
    showField: string;
    values: Record<SettingField, Stream<string>>;
    successAlert?: number;
    oninit(vnode: Mithril.Vnode<{}, this>): void;
    view(): JSX.Element;
    viewItems(): ItemList<Mithril.Children>;
    infoText(): Mithril.Children;
    changed(): boolean;
    tagsEnabled(): boolean;
    onsubmit(e: SubmitEvent): void;
    saveSingleSetting(setting: string, value: unknown): void;
}
export {};
