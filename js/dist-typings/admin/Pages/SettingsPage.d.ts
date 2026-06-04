import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';
export default class SettingsPage extends ExtensionPage {
    content(): JSX.Element;
    menuButtons(page: string): ItemList<Mithril.Children>;
    pages(): ItemList<Mithril.Children>;
    pageContent(page: string): Mithril.Children;
}
