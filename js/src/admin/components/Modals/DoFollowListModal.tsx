import app from 'flarum/admin/app';
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import saveSettings from 'flarum/common/utils/saveSettings';
import Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';

export default class DoFollowListModal extends Modal<IInternalModalAttrs> {
  domainDoFollowList!: Stream<string[]>;
  startValue!: Stream<string[]>;
  newDomain!: Stream<string>;
  baseUrl: string = '';
  hasChanges: boolean = false;
  loading: boolean = false;

  oninit(vnode: Mithril.Vnode<IInternalModalAttrs, this>) {
    super.oninit(vnode);

    this.baseUrl = this.getDomainFromBase();

    const stored = app.data.settings.seo_dofollow_domains;
    this.domainDoFollowList = typeof stored === 'undefined' ? Stream<string[]>([]) : Stream<string[]>(JSON.parse(stored));

    this.startValue = this.domainDoFollowList;

    this.newDomain = Stream('');
  }

  title() {
    return 'Do-follow list';
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
          <p>
            Enter the <b>hostnames</b> of the domains you want to add to the do-follow list.
          </p>

          <p>The domain you use for your Flarum instance is added to the list by default.</p>

          <p style={{ marginBottom: '15px' }}>
            <a href={'https://community.v17.dev/knowledgebase/36'} target={'_blank'}>
              Learn more
            </a>{' '}
            about the do-follow list.
          </p>

          <div className={'FlarumSEO-DoFollowList'}>
            <input type="text" value={this.baseUrl} readonly className={'FormControl'} />
            <Button className={'Button'} icon={'fas fa-times'} disabled />
          </div>

          {this.domainDoFollowList().map((domain, key) => (
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
              placeholder={'Allow a domain'}
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
            {this.hasChanges ? 'Save changes' : 'Close'}
          </Button>
        </div>
      </div>
    );
  }

  addDomain() {
    if (this.domainDoFollowList().indexOf(this.newDomain()) >= 0) {
      alert('This domain is already present in your do-follow list.');
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
      seo_dofollow_domains: JSON.stringify(this.domainDoFollowList().filter((val) => val !== '')),
    }).then(this.onsaved.bind(this));
  }

  onsaved() {
    this.hide();
  }
}
