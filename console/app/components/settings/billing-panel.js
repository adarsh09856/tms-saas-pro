import Component from '@glimmer/component';
import { action } from '@ember/object';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { task } from 'ember-concurrency';
import window from 'ember-window-mock';

const CURRENCY_DIVISOR = 100;

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
        this.plans = (plans?.plans || []).map((plan) => {
            const requiresInrSetup = String(plan.currency || 'INR').toUpperCase() !== 'INR';

            return {
                ...plan,
                requiresInrSetup,
                formattedPrice: requiresInrSetup ? null : new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(Number(plan.amount || 0) / CURRENCY_DIVISOR),
                entitlementsText: Object.entries(plan.entitlements || {})
                    .map(([key, value]) => `${key.replaceAll('_', ' ')}: ${Array.isArray(value) ? value.join(', ') : value}`)
                    .join(' · '),
            };
        });
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
