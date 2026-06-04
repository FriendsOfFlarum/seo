import Page from 'flarum/common/components/Page';
import type Mithril from 'mithril';
type PassState = true | false | 'must';
export default class HealthCheck extends Page {
    settings: Record<string, string | undefined>;
    saving: boolean;
    oninit(vnode: Mithril.Vnode<{}, this>): void;
    view(): JSX.Element;
    forumDescription(): JSX.Element;
    forumKeywords(): JSX.Element;
    siteUsesSSL(): JSX.Element;
    discussionPostSet(): JSX.Element;
    socialMediaImage(): JSX.Element;
    hasSitemap(): JSX.Element;
    hasRobotsTxt(): JSX.Element;
    tagsAvailable(): JSX.Element;
    registeredSearchEngines(): JSX.Element;
    reviewAgain(): JSX.Element;
    getSettingUrl(setting?: string): string;
    passed(passed: PassState): Mithril.Children;
    notPassedError(passed: PassState, reason: Mithril.Children, buttonText?: Mithril.Children, url?: string | (() => void)): Mithril.Children;
    saveSingleSetting(setting: string, value: unknown): void;
}
export {};
