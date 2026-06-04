import Model from 'flarum/common/Model';

export type SeoImageSource = 'auto' | 'custom' | 'fof-upload' | string | null;

export default class SeoMeta extends Model {
  objectType(): string {
    return this.attribute<string>('objectType');
  }
  objectId(): number {
    return this.attribute<number>('objectId');
  }

  autoUpdateData(): boolean {
    return this.attribute<boolean>('autoUpdateData');
  }

  title(): string | null {
    return this.attribute<string | null>('title');
  }
  description(): string | null {
    return this.attribute<string | null>('description');
  }
  keywords(): string | null {
    return this.attribute<string | null>('keywords');
  }

  robotsNoindex(): boolean {
    return this.attribute<boolean>('robotsNoindex');
  }
  robotsNofollow(): boolean {
    return this.attribute<boolean>('robotsNofollow');
  }
  robotsNoarchive(): boolean {
    return this.attribute<boolean>('robotsNoarchive');
  }
  robotsNoimageindex(): boolean {
    return this.attribute<boolean>('robotsNoimageindex');
  }
  robotsNosnippet(): boolean {
    return this.attribute<boolean>('robotsNosnippet');
  }

  twitterTitle(): string | null {
    return this.attribute<string | null>('twitterTitle');
  }
  twitterDescription(): string | null {
    return this.attribute<string | null>('twitterDescription');
  }
  twitterImage(): string | null {
    return this.attribute<string | null>('twitterImage');
  }
  twitterImageSource(): SeoImageSource {
    return this.attribute<SeoImageSource>('twitterImageSource');
  }

  openGraphTitle(): string | null {
    return this.attribute<string | null>('openGraphTitle');
  }
  openGraphDescription(): string | null {
    return this.attribute<string | null>('openGraphDescription');
  }
  openGraphImage(): string | null {
    return this.attribute<string | null>('openGraphImage');
  }
  openGraphImageSource(): SeoImageSource {
    return this.attribute<SeoImageSource>('openGraphImageSource');
  }

  estimatedReadingTime(): number | null {
    return this.attribute<number | null>('estimatedReadingTime');
  }

  createdAt(): Date | null {
    return this.attribute<Date | null>('createdAt');
  }
  updatedAt(): Date | null {
    return this.attribute<Date | null>('updatedAt');
  }

  apiEndpoint(): string {
    const id = this.id();
    return '/seo_meta' + (id ? '/' + id : '');
  }
}
