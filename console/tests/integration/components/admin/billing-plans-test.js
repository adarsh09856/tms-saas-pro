import { module, test } from 'qunit';
import { setupRenderingTest } from '@fleetbase/console/tests/helpers';
import { render } from '@ember/test-helpers';
import { hbs } from 'ember-cli-htmlbars';
import Service from '@ember/service';
import AdminBillingPlansComponent from '@fleetbase/console/components/admin/billing-plans';
import { captureComponent } from '@fleetbase/console/tests/helpers/capture-component';

module('Integration | Component | admin/billing-plans', function (hooks) {
    setupRenderingTest(hooks);

    hooks.beforeEach(function () {
        this.plans = [
            { uuid: 'legacy-plan', name: 'Legacy USD', slug: 'legacy-usd', currency: 'USD', amount: 9900, interval: 'month', is_active: true },
            { uuid: 'inr-plan', name: 'India Plan', slug: 'india-plan', currency: 'INR', amount: 39900, interval: 'month', is_active: true },
        ];
        this.errors = [];
        const context = this;

        class FetchStub extends Service {
            get() {
                return Promise.resolve({ plans: context.plans });
            }
        }

        class NotificationsStub extends Service {
            error(message) {
                context.errors.push(message);
            }
        }

        this.owner.register('service:fetch', FetchStub);
        this.owner.register('service:notifications', NotificationsStub);
        const captured = captureComponent(this.owner, 'admin/billing-plans', AdminBillingPlansComponent);
        this.build = async () => {
            await render(hbs`<Admin::BillingPlans />`);
            return captured.instance;
        };
    });

    test('legacy currency plans stay read-only while INR plans remain editable', async function (assert) {
        const component = await this.build();

        assert.dom('[data-test-edit-plan="legacy-usd"]').isDisabled();
        assert.dom('[data-test-legacy-plan-note]').containsText('Existing currency and amounts are preserved');
        assert.dom('[data-test-edit-plan="india-plan"]').isNotDisabled();

        component.edit(component.plans.find((plan) => plan.slug === 'legacy-usd'));

        assert.strictEqual(component.form.id, null, 'the old amount cannot be opened in an INR edit form');
        assert.deepEqual(this.errors, ['This plan uses USD and is read-only. Create a new INR plan to replace it.']);

        component.edit(component.plans.find((plan) => plan.slug === 'india-plan'));
        assert.strictEqual(component.form.amount, '399.00', 'INR plans retain their saved price when edited');
        assert.strictEqual(component.form.currency, 'INR');
    });
});
