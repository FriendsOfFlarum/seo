import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import saveSettings from 'flarum/admin/utils/saveSettings';
// @ts-ignore - resolved from flarum/tags at runtime; this component is only rendered when that extension is enabled.
import TagSelectionModal from 'ext:flarum/tags/components/TagSelectionModal';
// @ts-ignore
import tagsLabel from 'ext:flarum/tags/helpers/tagsLabel';
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
  loading: boolean = true;
  saving: boolean = false;
  selectedIds: string[] = [];
  tagsById: Record<string, any> = {};

  oninit(vnode: Mithril.Vnode<{}, this>) {
    super.oninit(vnode);

    this.selectedIds = this.parseSelected().map(String);

    (app.store.find('tags', { include: 'parent' }) as Promise<any>)
      .then((tags: any) => {
        (Array.isArray(tags) ? tags : []).forEach((tag: any) => {
          this.tagsById[String(tag.id())] = tag;
        });
        this.loading = false;
        m.redraw();
      })
      .catch(() => {
        this.loading = false;
        m.redraw();
      });
  }

  view() {
    if (this.loading) {
      return <LoadingIndicator />;
    }

    const selected = this.selectedTags();

    return (
      <div className="SeoNoindexTags">
        <div className="SeoNoindexTags-selection">
          {selected.length > 0 ? (
            tagsLabel(selected)
          ) : (
            <span className="helpText">{app.translator.trans('fof-seo.admin.settings.indexing.tags_none_selected')}</span>
          )}
        </div>
        <Button className="Button" loading={this.saving} onclick={() => this.openModal()}>
          {app.translator.trans('fof-seo.admin.settings.indexing.tags_button')}
        </Button>
      </div>
    );
  }

  selectedTags(): any[] {
    return this.selectedIds.map((id) => this.tagsById[id]).filter(Boolean);
  }

  openModal() {
    app.modal.show(TagSelectionModal, {
      selectedTags: this.selectedTags(),
      canSelect: () => true,
      onsubmit: (selected: any[]) => this.save(selected),
    });
  }

  save(selected: any[]) {
    selected.forEach((tag) => {
      this.tagsById[String(tag.id())] = tag;
    });

    this.selectedIds = selected.map((tag) => String(tag.id()));
    this.saving = true;

    const value = JSON.stringify(this.selectedIds.map((id) => parseInt(id, 10)));

    saveSettings({ seo_noindex_tags: value })
      .then(() => app.alerts.show({ type: 'success' }, app.translator.trans('core.admin.settings.saved_message')))
      .catch(() => {})
      .then(() => {
        this.saving = false;
        m.redraw();
      });
  }

  parseSelected(): Array<number | string> {
    try {
      const parsed = JSON.parse(app.data.settings.seo_noindex_tags || '[]');
      return Array.isArray(parsed) ? parsed : [];
    } catch {
      return [];
    }
  }
}
