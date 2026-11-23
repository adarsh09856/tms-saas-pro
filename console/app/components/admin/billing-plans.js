import Component from '@glimmer/component';
import { action } from '@ember/object';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { task } from 'ember-concurrency';

const ZERO_DECIMAL_CURRENCIES = new Set(['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF']);
const THREE_DECIMAL_CURRENCIES = new Set(['BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND']);
const currencyDivisor = (currency) => 10 ** (ZERO_DECIMAL_CURRENCIES.has(String(currency).toUpperCase()) ? 0 : THREE_DECIMAL_CURRENCIES.has(String(currency).toUpperCase()) ? 3 : 2);
const EMPTY_PLAN = () => ({
    id: null,
    name: '',
    slug: '',
    description: '',
    amount: '0',
    currency: 'INR',
    interval: 'month',
    trial_days: '0',
    stripe_price_id: '',
    entitlements: '{\n  "seats": 5,\n  "drivers": 10,\n  "storage_bytes": 1073741824,\n  "api_requests": 10000,\n  "modules": ["fleet-ops", "ledger"]\n}',
    is_active: true,
});

export default class AdminBillingPlansComponent extends Component {
    @service fetch;
    @service notifications;
    @tracked plans = [];
    @tracked form = EMPTY_PLAN();

    constructor() {
        super(...arguments);
        this.load.perform();
    }

    @task *load() {
        const result = yield this.fetch.get('billing/admin/plans');
        this.plans = (result?.plans || []).map((plan) => ({
            ...plan,
            displayAmount: new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(
                Number(plan.amount || 0) / currencyDivisor(plan.currency)
            ),
            entitlementSummary: Object.entries(plan.entitlements || {})
                .map(([key, value]) => `${key.replaceAll('_', ' ')}=${Array.isArray(value) ? value.join(',') : value}`)
                .join(' · '),
        }));
    }

    @action edit(plan) {
        this.form = {
            id: plan.uuid,
            name: plan.name || '',
            slug: plan.slug || '',
            description: plan.description || '',
            amount: (Number(plan.amount || 0) / currencyDivisor(plan.currency)).toFixed(
                THREE_DECIMAL_CURRENCIES.has(String(plan.currency || '').toUpperCase()) ? 3 : ZERO_DECIMAL_CURRENCIES.has(String(plan.currency || '').toUpperCase()) ? 0 : 2
            ),
            currency: 'INR',
            interval: plan.interval || 'month',
            trial_days: String(plan.trial_days || 0),
            stripe_price_id: plan.stripe_price_id || '',
            entitlements: JSON.stringify(plan.entitlements || {}, null, 2),
            is_active: Boolean(plan.is_active),
        };
    }

    @action reset() {
        this.form = EMPTY_PLAN();
    }
    @action update(field, event) {
        this.form = { ...this.form, [field]: event.target.type === 'checkbox' ? event.target.checked : event.target.value };
    }

    @action async save(event) {
        event.preventDefault();
        let entitlements;
        try {
            entitlements = JSON.parse(this.form.entitlements || '{}');
        } catch {
            return this.notifications.error('Entitlements must be valid JSON.');
        }
        const body = {
            name: this.form.name,
            slug: this.form.slug,
            description: this.form.description,
            amount: Math.round(Number(this.form.amount || 0) * currencyDivisor(this.form.currency)),
            currency: this.form.currency.toLowerCase(),
            interval: this.form.interval,
            trial_days: Number(this.form.trial_days || 0),
            stripe_price_id: this.form.stripe_price_id || null,
            entitlements,
            is_active: this.form.is_active,
        };
        try {
            await (this.form.id ? this.fetch.put(`billing/admin/plans/${this.form.id}`, body) : this.fetch.post('billing/admin/plans', body));
            this.notifications.success(this.form.id ? 'Plan version published.' : 'Plan created.');
            this.form = EMPTY_PLAN();
            await this.load.perform();
        } catch (error) {
            this.notifications.serverError(error);
        }
    }
}
