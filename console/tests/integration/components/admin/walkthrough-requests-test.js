import { module, test } from 'qunit';
import { setupRenderingTest } from '@fleetbase/console/tests/helpers';
import { render, select, click, settled } from '@ember/test-helpers';
import { hbs } from 'ember-cli-htmlbars';
import Service from '@ember/service';
import { captureComponent } from '@fleetbase/console/tests/helpers/capture-component';
import AdminWalkthroughRequestsComponent from '@fleetbase/console/components/admin/walkthrough-requests';

function flush() {
    return new Promise((resolve) => setTimeout(resolve, 0));
}

module('Integration | Component | admin/walkthrough-requests', function (hooks) {
    setupRenderingTest(hooks);

    hooks.beforeEach(function () {
        const context = this;
        this.requests = [];
        this.fail = false;
        this.lead = { id: 7, name: 'Avery Example', company: 'Northline Transport', email: 'avery@example.com', phone: null, created_at: '2026-11-26 10:00', status: 'new' };
        class FetchStub extends Service {
            get(path) {
                context.requests.push({ method: 'get', path });
                if (context.fail) return Promise.reject(new Error('Could not load requests'));
                const page = Number(new URLSearchParams(path.split('?')[1]).get('page') || 1);
                return Promise.resolve({ data: [{ ...context.lead }], current_page: page, last_page: 2, total: 21 });
            }
            patch(path, payload) {
                context.requests.push({ method: 'patch', path, payload });
                if (context.fail) return Promise.reject(new Error('Could not update status'));
                context.lead = { ...context.lead, status: payload.status };
                return Promise.resolve({ request: { ...context.lead } });
            }
        }
        class NotificationsStub extends Service {
            successes = [];
            errors = [];
            success(message) {
                this.successes.push(message);
            }
            serverError(error) {
                this.errors.push(error.message);
            }
        }
        this.owner.register('service:fetch', FetchStub);
        this.owner.register('service:notifications', NotificationsStub);
        this.notifications = () => this.owner.lookup('service:notifications');
        const captured = captureComponent(this.owner, 'admin/walkthrough-requests', AdminWalkthroughRequestsComponent);
        this.build = async () => {
            await render(hbs`<Admin::WalkthroughRequests />`);
            await flush();
            await settled();
            return captured.instance;
        };
    });

    test('loads paginated requests and updates status with visible confirmation', async function (assert) {
        const component = await this.build();

        assert.strictEqual(this.requests[0].path, 'platform/walkthrough-requests?page=1&per_page=20');
        assert.strictEqual(component.total, 21);
        assert.dom('[data-test-requests-table]').containsText('Avery Example');
        assert.dom('[data-test-next]').isNotDisabled();

        await select('[data-test-request-status="7"]', 'contacted');
        await flush();

        assert.deepEqual(this.requests.at(-1), {
            method: 'patch',
            path: 'platform/walkthrough-requests/7/status',
            payload: { status: 'contacted' },
        });
        assert.dom('[data-test-request-status="7"]').hasValue('contacted');
        assert.dom('[data-test-success]').containsText('Contacted');
        assert.deepEqual(this.notifications().successes, ['Request status updated to Contacted.']);
    });

    test('shows a load error and reports it through notifications', async function (assert) {
        this.fail = true;
        await this.build();

        assert.dom('[data-test-error]').containsText('Could not load requests');
        assert.deepEqual(this.notifications().errors, ['Could not load requests']);
    });

    test('filters by status and moves through result pages', async function (assert) {
        const component = await this.build();
        await select('[data-test-status-filter]', 'closed');
        await flush();
        assert.ok(this.requests.at(-1).path.includes('status=closed'), 'the selected status is sent to the API');
        assert.strictEqual(component.page, 1, 'changing the filter returns to the first page');

        await click('[data-test-next]');
        await flush();
        assert.ok(this.requests.at(-1).path.includes('page=2'), 'the next page is requested');
        assert.strictEqual(component.page, 2, 'the current page follows the API response');
    });
});
