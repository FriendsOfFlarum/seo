import app from 'flarum/admin/app';
import { IFormModalAttrs } from 'flarum/common/components/FormModal';
import FormModal from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import saveSettings from 'flarum/admin/utils/saveSettings';
import Stream from 'flarum/common/utils/Stream';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';

export default class DoFollowListModal extends FormModal<IFormModalAttrs> {
  domainDoFollowList!: Stream<string[]>;
  startValue!: Stream<string[]>;
  newDomain!: Stream<string>;
  baseUrl: string = '';
  hasChanges: boolean = false;
  loading: boolean = false;

  oninit(vnode: Mithril.Vnode<IFormModalAttrs, this>) {
    super.oninit(vnode);

    this.baseUrl = this.getDomainFromBase();

    const stored = app.data.settings.seo_dofollow_domains;
    this.domainDoFollowList = typeof stored === 'undefined' ? Stream<string[]>([]) : Stream<string[]>(JSON.parse(stored));

    this.startValue = this.domainDoFollowList;

    this.newDomain = Stream('');
  }

  title() {
    return app.translator.trans('fof-seo.admin.modals.dofollow.title');
  }

  className() {
    return 'Modal';
  }

  getDomainFromBase(): string {
    const url = new URL(app.forum.attribute<string>('baseUrl'));

    const hostname = url.hostname.split('.');

    return hostname.slice(Math.max(hostname.length - 2, 0)).join('.');
  }

  content() {
    return (
      <div>
        <div className="Modal-body">
          <p>{app.translator.trans('fof-seo.admin.modals.dofollow.intro', { b: <b /> })}</p>

          <p>{app.translator.trans('fof-seo.admin.modals.dofollow.default_note')}</p>

          <p style={{ marginBottom: '15px' }}>
            {app.translator.trans('fof-seo.admin.modals.dofollow.learn_more_line', {
              a: <Link external={true} href="https://community.v17.dev/knowledgebase/36" target="_blank" />,
            })}
          </p>

          <div className={'FlarumSEO-DoFollowList'}>
            <input type="text" value={this.baseUrl} readonly className={'FormControl'} />
            <Button className={'Button'} icon={'fas fa-times'} disabled />
          </div>

          {this.domainDoFollowList().map((domain: string, key: number) => (
            <div className={'FlarumSEO-DoFollowList'}>
              <input
                type="text"
                value={domain}
                onkeyup={(e: KeyboardEvent) => this.updateDomain(key, (e.target as HTMLInputElement).value)}
                className={'FormControl'}
              />
              <Button className={'Button'} icon={'fas fa-times'} onclick={() => this.removeDomain(key)} />
            </div>
          ))}

          <div className={'FlarumSEO-DoFollowList'}>
            <input
              type="text"
              bidi={this.newDomain}
              placeholder={extractText(app.translator.trans('fof-seo.admin.modals.dofollow.add_placeholder'))}
              onkeydown={(e: KeyboardEvent) => {
                if (e.keyCode === 13 && this.newDomain() !== '') {
                  e.preventDefault();
                  this.addDomain();
                }
              }}
              className={'FormControl'}
            />
            <Button
              className={`Button ${this.newDomain() !== '' ? 'Button--primary' : ''}`}
              icon={'fas fa-plus'}
              onclick={this.addDomain.bind(this)}
            />
          </div>
        </div>
        <div style="padding: 25px 30px; text-align: center;">
          <Button type="submit" className="Button Button--primary" loading={this.loading}>
            {app.translator.trans(this.hasChanges ? 'fof-seo.admin.common.save_changes' : 'fof-seo.admin.common.close')}
          </Button>
        </div>
      </div>
    );
  }

  addDomain() {
    if (this.domainDoFollowList().indexOf(this.newDomain()) >= 0) {
      alert(extractText(app.translator.trans('fof-seo.admin.modals.dofollow.duplicate_error')));
      this.newDomain('');
      return;
    }

    const updatedData = [...this.domainDoFollowList(), this.newDomain()];

    this.domainDoFollowList(updatedData);
    this.newDomain('');
    this.hasChanges = true;
  }

  removeDomain(key: number) {
    const updatedData = [...this.domainDoFollowList()];
    updatedData.splice(key, 1);

    this.domainDoFollowList(updatedData);
    this.hasChanges = true;
  }

  updateDomain(key: number, value: string) {
    const updatedData = [...this.domainDoFollowList()];
    updatedData[key] = value;

    this.domainDoFollowList(updatedData);
    this.hasChanges = true;
  }

  onsubmit(e: SubmitEvent) {
    e.preventDefault();

    if (!this.hasChanges) {
      this.hide();
      return;
    }

    this.loading = true;

    saveSettings({
      seo_dofollow_domains: JSON.stringify(this.domainDoFollowList().filter((val: string) => val !== '')),
    }).then(this.onsaved.bind(this));
  }

  onsaved() {
    this.hide();
  }
}
