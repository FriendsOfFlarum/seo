import Component from 'flarum/common/Component';
import type Mithril from 'mithril';
/**
 * Lets admins pick tags whose discussions (and the tags' own listing pages)
 * should be kept out of the search index — the `seo_noindex_tags` setting
 * consumed by the page drivers (GH #117).
 *
 * Reuses flarum/tags' own `TagSelectionModal` for the picker. The selection is
 * persisted as a JSON array of tag IDs. Only rendered when flarum/tags is
 * enabled (see SeoSettings#tagsEnabled).
 */
export default class NoindexTagsSetting extends Component {
    loading: boolean;
    saving: boolean;
    selectedIds: string[];
    tagsById: Record<string, any>;
    oninit(vnode: Mithril.Vnode<{}, this>): void;
    view(): JSX.Element;
    selectedTags(): any[];
    openModal(): void;
    save(selected: any[]): void;
    parseSelected(): Array<number | string>;
}
