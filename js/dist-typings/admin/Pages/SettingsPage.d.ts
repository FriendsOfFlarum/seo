import ExtensionPage, { type ExtensionPageAttrs } from 'flarum/admin/components/ExtensionPage';
import type { TabItem } from 'flarum/common/components/Tabs';
import type ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';
export default class SettingsPage extends ExtensionPage {
    oninit(vnode: Mithril.Vnode<ExtensionPageAttrs, this>): void;
    /**
     * Leaves the forum description, which belongs to the Basics page, out of the reset.
     */
    settingsFormControls(): ItemList<Mithril.Children>;
    pageTabItems(): ItemList<TabItem>;
    pageTabContent(tab: string, vnode: Mithril.VnodeDOM<ExtensionPageAttrs, this>): Mithril.Children;
    infoPages(): Record<string, () => Mithril.Children>;
}
