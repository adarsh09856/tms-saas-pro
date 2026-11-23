import Component from '@glimmer/component';
import { action } from '@ember/object';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { task } from 'ember-concurrency';
import window from 'ember-window-mock';

const ZERO_DECIMAL_CURRENCIES = new Set(['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF']);
const THREE_DECIMAL_CURRENCIES = new Set(['BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND']);
const currencyDivisor = (currency) => 10 ** (ZERO_DECIMAL_CURRENCIES.has(String(currency).toUpperCase()) ? 0 : THREE_DECIMAL_CURRENCIES.has(String(currency).toUpperCase()) ? 3 : 2);

export default class SettingsBillingPanelComponent extends Component {
    @service fetch;
    @service notifications;
    @tracked plans = [];
    @tracked subscription = null;
    @tracked usage = {};

    constructor() {
        super(...arguments);
        this.load.perform();
    }

    @task *load() {
        const [plans, current] = yield Promise.all([this.fetch.get('billing/plans'), this.fetch.get('billing/subscription')]);
        this.plans = (plans?.plans || []).map((plan) => ({
            ...plan,
            formattedPrice: new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(
                Number(plan.amount || 0) / currencyDivisor(plan.currency)
            ),
            entitlementsText: Object.entries(plan.entitlements || {})
                .map(([key, value]) => `${key.replaceAll('_', ' ')}: ${Array.isArray(value) ? value.join(', ') : value}`)
                .join(' · '),
        }));
        this.subscription = current?.subscription || null;
        this.usage = current?.usage || {};
    }

    get hasActiveSubscription() {
        return ['active', 'trialing', 'past_due'].includes(this.subscription?.payment_gateway_status);
    }

    @action async choosePlan(plan) {
        try {
            const response = await this.fetch.post('billing/checkout', { plan: plan.uuid });
            if (response.checkout.free) {
                this.notifications.success('Free plan activated.');
                await this.load.perform();
            } else {
                window.location.assign(response.checkout.url);
            }
        } catch (error) {
            this.notifications.serverError(error);
        }
    }

    @action async openPortal() {
        try {
            const response = await this.fetch.post('billing/portal');
            window.location.assign(response.portal.url);
        } catch (error) {
            this.notifications.serverError(error);
        }
    }

    @action async cancelLocalPlan() {
        try {
            await this.fetch.post('billing/cancel');
            this.notifications.success('Plan cancelled.');
            await this.load.perform();
        } catch (error) {
            this.notifications.serverError(error);
        }
    }
}
