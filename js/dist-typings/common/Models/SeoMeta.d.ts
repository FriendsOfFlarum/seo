import Model from 'flarum/common/Model';
export type SeoImageSource = 'auto' | 'custom' | 'fof-upload' | string | null;
export default class SeoMeta extends Model {
    objectType(): string;
    objectId(): number;
    autoUpdateData(): boolean;
    title(): string | null;
    description(): string | null;
    keywords(): string | null;
    robotsNoindex(): boolean;
    robotsNofollow(): boolean;
    robotsNoarchive(): boolean;
    robotsNoimageindex(): boolean;
    robotsNosnippet(): boolean;
    twitterTitle(): string | null;
    twitterDescription(): string | null;
    twitterImage(): string | null;
    twitterImageSource(): SeoImageSource;
    openGraphTitle(): string | null;
    openGraphDescription(): string | null;
    openGraphImage(): string | null;
    openGraphImageSource(): SeoImageSource;
    estimatedReadingTime(): number | null;
    createdAt(): Date | null;
    updatedAt(): Date | null;
    apiEndpoint(): string;
}
