import app from 'flarum/admin/app';
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import saveSettings from 'flarum/common/utils/saveSettings';
import type Mithril from 'mithril';

export default class RobotsModal extends Modal<IInternalModalAttrs> {
  value: string = '';
  startValue: string = '';
  closeText: string = 'Close';
  loading: boolean = false;

  oninit(vnode: Mithril.Vnode<IInternalModalAttrs, this>) {
    super.oninit(vnode);

    const stored = app.data.settings.seo_robots_text;
    this.value = typeof stored === 'undefined' ? '' : stored;
    this.startValue = this.value;
  }

  title() {
    return 'Custom robots.txt';
  }

  className() {
    return 'Modal';
  }

  content() {
    return (
      <div>
        <div className="Modal-body">
          {m('textarea', {
            className: 'FormControl',
            value: this.value,
            placeholder: 'Add text to the robots.txt',
            rows: 15,
            oninput: (event: InputEvent) => {
              this.change((event.target as HTMLTextAreaElement).value);
            },
          })}
        </div>
        <div style="padding: 25px 30px; text-align: center;">{this.closeDialogButton()}</div>
      </div>
    );
  }

  change(value: string) {
    this.value = value;

    this.closeText = this.value !== this.startValue ? 'Save changes' : 'Close';
  }

  closeDialogButton() {
    return (
      <Button type="submit" className="Button Button--primary" loading={this.loading}>
        {this.closeText}
      </Button>
    );
  }

  onsubmit(e: SubmitEvent) {
    e.preventDefault();

    if (this.value === this.startValue) {
      this.hide();
      return;
    }

    this.loading = true;

    saveSettings({ seo_robots_text: this.value }).then(this.onsaved.bind(this));
  }

  onsaved() {
    this.hide();
  }
}
