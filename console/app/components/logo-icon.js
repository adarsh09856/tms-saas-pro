import LogoIconComponent from '@fleetbase/ember-ui/components/logo-icon';

const DEFAULT_DIARY_ICON = '/images/transport-diary-icon.svg?v=20261125-mark4';

function isSavedProductIcon(url) {
    if (!url) {
        return false;
    }

    try {
        const parsedUrl = new URL(url, window.location.origin);
        return parsedUrl.origin === window.location.origin && /^\/images\/(?:kodewaves|transport-diary)-icon\.svg$/i.test(parsedUrl.pathname);
    } catch {
        return false;
    }
}

export default class TransportDiaryLogoIconComponent extends LogoIconComponent {
    get iconUrl() {
        const configuredUrl = this.brand?.icon_url;

        // Migrate only the legacy Fleetbase/Kodewaves mark. Preserve a configured
        // company mark so vendor workspaces can keep their own branding.
        if (!configuredUrl || isSavedProductIcon(configuredUrl)) {
            return DEFAULT_DIARY_ICON;
        }

        return configuredUrl;
    }

    get logoAlt() {
        return this.iconUrl === DEFAULT_DIARY_ICON ? 'Transport Diary' : 'Company logo';
    }
}
