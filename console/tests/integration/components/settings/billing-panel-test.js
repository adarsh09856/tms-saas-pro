import { module, test } from 'qunit';
import { setupRenderingTest } from '@fleetbase/console/tests/helpers';
import { render } from '@ember/test-helpers';
import { hbs } from 'ember-cli-htmlbars';
import Service from '@ember/service';

module('Integration | Component | settings/billing-panel', function (hooks) {
    setupRenderingTest(hooks);

    hooks.beforeEach(function () {
        class FetchStub extends Service {
            get(path) {
                if (path === 'billing/plans') {
                    return Promise.resolve({
                        plans: [
                            { uuid: 'legacy-plan', name: 'Legacy plan', slug: 'legacy-plan', description: 'Existing configuration', currency: 'USD', amount: 9900, interval: 'month' },
                            { uuid: 'inr-plan', name: 'India plan', slug: 'inr-plan', description: 'Current configuration', currency: 'INR', amount: 42500, interval: 'month' },
                        ],
                    });
                }

                return Promise.resolve({ subscription: null, usage: {} });
            }
        }

        class NotificationsStub extends Service {
            success() {}
            serverError() {}
        }

        this.owner.register('service:fetch', FetchStub);
        this.owner.register('service:notifications', NotificationsStub);
    });

    test('legacy plans are not relabeled as INR or offered for selection', async function (assert) {
        await render(hbs`<Settings::BillingPanel />`);

        const legacyPlan = '[data-test-plan-card="legacy-plan"]';
        assert.dom(`${legacyPlan} [data-test-legacy-plan-note]`).containsText('INR setup required');
        assert.dom(`${legacyPlan} [data-test-plan-price]`).doesNotExist();
        assert.dom(`${legacyPlan} button`).doesNotExist();
        assert.dom(legacyPlan).doesNotContainText('$');
        assert.dom('[data-test-plan-card="inr-plan"] [data-test-plan-price]').containsText('₹425.00');
    });
});
