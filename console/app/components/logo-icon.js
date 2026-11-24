import LogoIconComponent from '@fleetbase/ember-ui/components/logo-icon';

const DEFAULT_DIARY_ICON = '/images/transport-diary-icon.svg?v=20261124-brand';

export default class TransportDiaryLogoIconComponent extends LogoIconComponent {
    get iconUrl() {
        const configuredUrl = this.brand?.icon_url;

        // Existing installations may still have the old Kodewaves asset saved in Brand settings.
        // Map only that legacy product asset; vendor-uploaded logos continue to render unchanged.
        if (!configuredUrl || configuredUrl.includes('kodewaves-icon.svg')) {
            return DEFAULT_DIARY_ICON;
        }

        return configuredUrl;
    }
}
