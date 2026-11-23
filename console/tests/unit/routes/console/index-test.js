import { module, test } from 'qunit';
import { setupTest } from '@fleetbase/console/tests/helpers';

module('Unit | Route | console/index', function (hooks) {
    setupTest(hooks);

    test('the empty console root redirects to the dashboard', function (assert) {
        const route = this.owner.lookup('route:console/index');
        let destination;
        route.router.replaceWith = (name) => (destination = name);

        route.beforeModel();

        assert.strictEqual(destination, 'console.home');
    });
});
