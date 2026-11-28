import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { later } from '@ember/runloop';
import { action } from '@ember/object';
import { isArray } from '@ember/array';
import { dasherize } from '@ember/string';
import first from '@fleetbase/ember-core/utils/first';
import window from 'ember-window-mock';

const workspaceModuleTitles = {
    'fleet-ops': 'Transport operations',
    ledger: 'Finance',
    pallet: 'Warehouse',
    iam: 'Team and access',
};

const workspaceModuleLinks = {
    pallet: [
        ['Warehouse overview', 'console.pallet.home', ''],
        ['Products', 'console.pallet.catalog.products', 'Catalog'],
        ['Suppliers', 'console.pallet.catalog.suppliers', 'Catalog'],
        ['Warehouses', 'console.pallet.facilities.warehouses', 'Facilities'],
        ['Locations', 'console.pallet.facilities.locations', 'Facilities'],
        ['Zones', 'console.pallet.facilities.zones', 'Facilities'],
        ['Inventory', 'console.pallet.inventory.index', 'Inventory'],
        ['Batches', 'console.pallet.inventory.batches', 'Inventory'],
        ['Stock Adjustments', 'console.pallet.inventory.adjustments', 'Inventory'],
        ['Low Stock', 'console.pallet.inventory.low-stock', 'Inventory'],
        ['Expired Stock', 'console.pallet.inventory.expired-stock', 'Inventory'],
        ['Purchase Orders', 'console.pallet.orders.purchase-orders', 'Orders'],
        ['Sales Orders', 'console.pallet.orders.sales-orders', 'Orders'],
        ['Transfers', 'console.pallet.operations.transfers', 'Operations'],
        ['Cycle Counts', 'console.pallet.operations.cycle-counts', 'Operations'],
        ['Pick Lists', 'console.pallet.operations.pick-lists', 'Operations'],
        ['Waves', 'console.pallet.operations.waves', 'Operations'],
        ['Reservations', 'console.pallet.operations.reservations', 'Operations'],
        ['Reports', 'console.pallet.analytics.reports', 'Analytics'],
        ['Audits', 'console.pallet.analytics.audits', 'Analytics'],
    ],
    ledger: [
        ['Invoice Templates', 'console.ledger.billing.invoice-templates.index', 'Billing'],
        ['Income Statement', 'console.ledger.reports.income-statement', 'Reports'],
        ['Balance Sheet', 'console.ledger.reports.balance-sheet', 'Reports'],
        ['Trial Balance', 'console.ledger.reports.trial-balance', 'Reports'],
        ['Cash Flow', 'console.ledger.reports.cash-flow', 'Reports'],
        ['AR Aging', 'console.ledger.reports.ar-aging', 'Reports'],
        ['Wallet Summary', 'console.ledger.reports.wallet-summary', 'Reports'],
        ['Invoice Settings', 'console.ledger.settings.invoice', 'Settings'],
        ['Payment Settings', 'console.ledger.settings.payment', 'Settings'],
        ['Accounting Settings', 'console.ledger.settings.accounting', 'Settings'],
    ],
};

const ledgerRoutePermissions = {
    'billing.invoices': 'ledger list invoice',
    'billing.invoice-templates': 'ledger list invoice-template',
    'payments.wallets': 'ledger list wallet',
    'payments.transactions': 'ledger list transaction',
    'payments.gateways': 'ledger list gateway',
    'accounting.accounts': 'ledger list account',
    'accounting.journal': 'ledger list journal',
    'accounting.general-ledger': 'ledger view-general-ledger report',
    'reports.income-statement': 'ledger view-income-statement report',
    'reports.balance-sheet': 'ledger view-balance-sheet report',
    'reports.trial-balance': 'ledger view-trial-balance report',
    'reports.cash-flow': 'ledger view-cash-flow report',
    'reports.ar-aging': 'ledger view-ar-aging report',
    'reports.wallet-summary': 'ledger view-wallet-summary report',
    'settings.invoice': 'ledger view invoice-settings',
    'settings.payment': 'ledger view payment-settings',
    'settings.accounting': 'ledger view accounting-settings',
};

export default class ConsoleController extends Controller {
    @service currentUser;
    @service modalsManager;
    @service session;
    @service fetch;
    @service notifications;
    @service router;
    @service intl;
    @service universe;
    @service abilities;
    @service sidebar;
    @service('universe/extension-manager') extensionManager;
    @tracked organizations = [];
    @tracked menuItems = [];
    @tracked userMenuItems = [];
    @tracked organizationMenuItems = [];

    get currentRouteClass() {
        return dasherize(this.router.currentRouteName.replace(/\./g, ' '));
    }

    @action expandWorkspaceNavigation() {
        this.sidebar.toggle();
    }

    @action setupWorkspaceSidebar(context) {
        if (window.matchMedia('(max-width: 767px)').matches) {
            context.hideNow();
        }
    }

    @action closeMobileWorkspaceNavigation(event) {
        if (window.matchMedia('(max-width: 767px)').matches && event.target.closest('a[href]')) {
            this.sidebar.hide();
        }
    }

    get mainNavigationItems() {
        const visibleItems = [];

        for (const item of this.universe.headerMenuItems ?? []) {
            const abilityId = item._isShortcut && item._parentId ? item._parentId : item.id;

            if (item.permission) {
                try {
                    if (this.abilities.cannot(item.permission)) {
                        continue;
                    }
                } catch (_) {
                    // Match SmartNavMenu: an unregistered item permission does not hide it.
                }
            }

            try {
                if (this.abilities.can(`${abilityId} see extension`)) {
                    // Sidebar::Navigator also applies `permission`, but its unknown-ability
                    // fallback is stricter than SmartNavMenu's. We already applied the
                    // console's established permission semantics above.
                    visibleItems.push({ ...item, permission: undefined });
                }
            } catch (_) {
                // Keep extensions visible when their ability has not been registered.
                visibleItems.push({ ...item, permission: undefined });
            }
        }

        let pinnedIds = [];
        try {
            const preferences = this.currentUser.getOption('smart-nav-menu-prefs');
            pinnedIds = Array.isArray(preferences?.pinnedIds) ? preferences.pinnedIds : [];
        } catch (_) {
            // Navigation ordering remains at the registered extension priority.
        }

        const namedItems = visibleItems.map((item) => {
            const title = workspaceModuleTitles[item.id];
            return title ? { ...item, title, label: title, text: title } : item;
        });
        const byId = new Map(namedItems.map((item) => [item.id, item]));
        const pinned = pinnedIds.map((id) => byId.get(id)).filter(Boolean);
        const pinnedSet = new Set(pinnedIds);
        const ordered = [...pinned, ...namedItems.filter((item) => !pinnedSet.has(item.id))];
        const shortcuts = new Map();

        for (const item of ordered) {
            if (item._isShortcut && byId.has(item._parentId)) {
                const children = shortcuts.get(item._parentId) ?? [];
                children.push(item);
                shortcuts.set(item._parentId, children);
            }
        }

        const directory = ordered
            .filter((item) => !item._isShortcut || !byId.has(item._parentId))
            .map((item) => {
                const extras = (workspaceModuleLinks[item.id] ?? []).map(([label, route, section]) => ({
                    id: `${item.id}-${dasherize(label)}`,
                    label,
                    title: label,
                    route,
                    section,
                    routeParams: [],
                    queryParams: {},
                }));
                const children = [...(shortcuts.get(item.id) ?? []), ...extras].filter((child) => {
                    if (item.id !== 'ledger') {
                        return true;
                    }
                    const route = child.route?.replace(/^console\.ledger\./, '').replace(/\.index$/, '');
                    const permission = ledgerRoutePermissions[route];
                    return !permission || this.abilities.can(permission);
                });
                if (!children?.length) {
                    return item;
                }

                return {
                    ...item,
                    route: undefined,
                    children: [{ ...item, id: `${item.id}-overview`, label: 'Overview', title: 'Overview' }, ...children],
                };
            });
        try {
            if (this.extensionManager.isInstalled('@fleetbase/ai-engine') && this.abilities.can('ai see extension')) {
                directory.push({
                    id: 'ai',
                    title: 'AI and automation',
                    label: 'AI and automation',
                    icon: 'wand-magic-sparkles',
                    route: 'console.ai',
                    routeParams: [],
                    queryParams: {},
                });
            }
        } catch (_) {
            // An unavailable AI ability must not prevent the rest of the workspace from rendering.
        }
        return directory;
    }

    get workspaceDirectory() {
        return this.mainNavigationItems.map((item) => {
            const sections = new Map();
            for (const child of item.children ?? []) {
                const route = child.route ?? '';
                let section = child.section ?? '';
                if (item.id === 'fleet-ops' && !child.id.endsWith('-overview')) {
                    section = route.endsWith('management.index')
                        ? 'Bookings and dispatch'
                        : {
                              management: 'Fleet and places',
                              operations: 'Bookings and dispatch',
                              connectivity: 'Tracking and devices',
                              analytics: 'Reports',
                          }[route.split('.')[2]] ?? 'Tools';
                } else if (item.id === 'ledger' && !child.id.endsWith('-overview') && !section) {
                    section = { billing: 'Billing', payments: 'Payments', accounting: 'Accounting', reports: 'Reports' }[route.split('.')[2]] ?? '';
                }
                if (!sections.has(section)) {
                    sections.set(section, []);
                }
                sections.get(section).push(child);
            }
            return { ...item, sections: Array.from(sections, ([title, links]) => ({ title, links })) };
        });
    }

    /**
     * Action handler.
     *
     * @void
     */
    @action onAction(action, ...params) {
        if (typeof this[action] === 'function') {
            this[action](...params);
        }
    }

    /**
     * Action to invalidate and log user out
     *
     * @void
     */
    @action async invalidateSession(noop, event) {
        event.preventDefault();
        await this.session.invalidateWithLoader();
    }

    /**
     * Action to create or join an organization.
     *
     * @void
     */
    @action createOrJoinOrg() {
        const currency = 'INR';
        const country = this.currentUser.company?.country ?? this.currentUser.country;
        const timezone = this.currentUser.company?.timezone ?? this.currentUser.whois('timezone');

        this.modalsManager.show('modals/create-or-join-org', {
            title: this.intl.t('console.create-or-join-organization.modal-title'),
            acceptButtonText: this.intl.t('common.confirm'),
            acceptButtonIcon: 'check',
            acceptButtonIconPrefix: 'fas',
            action: 'join',
            next: null,
            name: null,
            decription: null,
            phone: null,
            currency,
            country,
            timezone,
            changeAction: (action) => {
                this.modalsManager.setOption('action', action);
            },
            confirm: async (modal) => {
                modal.startLoading();

                const { action, next, name, description, phone, currency, country, timezone } = modal.getOptions();

                if (action === 'join') {
                    try {
                        await this.fetch.post('auth/join-organization', { next });
                        this.fetch.flushRequestCache('auth/organizations');
                        this.notifications.success(this.intl.t('console.create-or-join-organization.join-success-notification'));
                        return later(
                            this,
                            () => {
                                window.location.reload();
                            },
                            900
                        );
                    } catch (error) {
                        modal.stopLoading();
                        return this.notifications.serverError(error);
                    }
                }

                try {
                    await this.fetch.post('auth/create-organization', {
                        name,
                        description,
                        phone,
                        currency,
                        country,
                        timezone,
                    });
                    this.fetch.flushRequestCache('auth/organizations');
                    this.notifications.success(this.intl.t('console.create-or-join-organization.create-success-notification'));
                    return later(
                        this,
                        () => {
                            window.location.reload();
                        },
                        900
                    );
                } catch (error) {
                    modal.stopLoading();
                    return this.notifications.serverError(error);
                }
            },
        });
    }

    /**
     * Confirm prompt for user to switch organization
     *
     * @void
     */
    @action switchOrganization(organization) {
        if (isArray(organization)) {
            organization = first(organization);
        }

        this.modalsManager.confirm({
            title: this.intl.t('console.switch-organization.modal-title', { organizationName: organization.name }),
            body: this.intl.t('console.switch-organization.modal-body'),
            acceptButtonText: this.intl.t('console.switch-organization.modal-accept-button-text'),
            acceptButtonScheme: 'primary',
            confirm: async (modal) => {
                modal.startLoading();

                try {
                    await this.fetch.post('auth/switch-organization', { next: organization.uuid });
                    this.fetch.flushRequestCache('auth/organizations');
                    this.notifications.success(this.intl.t('console.switch-organization.success-notification'));
                    return later(
                        this,
                        () => {
                            window.location.reload();
                        },
                        900
                    );
                } catch (error) {
                    modal.stopLoading();
                    return this.notifications.serverError(error);
                }
            },
        });
    }

    @action viewChangelog() {
        this.modalsManager.show('modals/changelog', {
            title: this.intl.t('common.changelog'),
            acceptButtonText: this.intl.t('common.ok'),
            hideDeclineButton: true,
        });
    }
}
