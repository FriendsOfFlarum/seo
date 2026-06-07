import { IFormModalAttrs } from 'flarum/common/components/FormModal';
import FormModal from 'flarum/common/components/FormModal';
import Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';
import SeoMeta, { SeoImageSource } from '../Models/SeoMeta';
export interface MetaSeoModalAttrs extends IFormModalAttrs {
    object?: {
        seoMeta?: () => SeoMeta;
    };
    objectType?: string;
    objectId?: string | number;
}
export default class MetaSeoModal extends FormModal<MetaSeoModalAttrs> {
    initialized: boolean;
    initialLoading: boolean;
    loading: boolean;
    hasChanges: boolean;
    closeText: Mithril.Children;
    closeInfoText: Mithril.Children;
    enableCustomTwitter: boolean;
    enableCustomOpenGraph: boolean;
    wasManaged: boolean;
    seoTagsOpened: boolean;
    fofUpload: {
        Uploader: any;
        FileManagerModal: any;
    } | null;
    meta?: SeoMeta;
    autoUpdateData: Stream<boolean>;
    metaTitle: Stream<string | null>;
    description: Stream<string | null>;
    keywords: Stream<string | null>;
    robotsNoindex: Stream<boolean>;
    robotsNofollow: Stream<boolean>;
    robotsNoarchive: Stream<boolean>;
    robotsNoimageindex: Stream<boolean>;
    robotsNosnippet: Stream<boolean>;
    twitterTitle: Stream<string | null>;
    twitterDescription: Stream<string | null>;
    twitterImage: Stream<string | null>;
    twitterImageSource: Stream<SeoImageSource>;
    openGraphTitle: Stream<string | null>;
    openGraphDescription: Stream<string | null>;
    openGraphImage: Stream<string | null>;
    openGraphImageSource: Stream<SeoImageSource>;
    estimatedReadingTime: Stream<number | null>;
    createdAt: Stream<Date | null>;
    updatedAt: Stream<Date | null>;
    oninit(vnode: Mithril.Vnode<MetaSeoModalAttrs, this>): void;
    initializeData(): void;
    title(): string | any[];
    className(): string;
    initializeLoad(): void;
    content(): JSX.Element;
    /**
     * Lazily load the fof/upload modules when that (optional) extension is enabled.
     *
     * Flarum 2.0 exposes other extensions' modules through the `ext:` scheme, which
     * resolves to async (externalised) imports — so we load them up front and redraw
     * once they're available rather than `require()`-ing synchronously during render.
     */
    loadFoFUpload(): void;
    returnFoFUploadButton(onSelect: (fileUrl: string) => void): Mithril.Children;
    returnTag(isEnabled: boolean, enabledText: Mithril.Children, disabledText: Mithril.Children): JSX.Element;
    closeDialogButton(): JSX.Element;
    updateHasChanges(): void;
    submitData(): Record<string, any>;
    onsubmit(e: SubmitEvent): void;
    onsaved(): void;
}
