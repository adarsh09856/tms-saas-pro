import { module, test } from 'qunit';
import { setupRenderingTest } from '@fleetbase/console/tests/helpers';
import { render } from '@ember/test-helpers';
import { hbs } from 'ember-cli-htmlbars';

module('Integration | Component | logo-icon', function (hooks) {
    setupRenderingTest(hooks);

    test('it replaces a saved legacy Kodewaves mark with the Transport Diary product mark', async function (assert) {
        await render(hbs`<LogoIcon @brand={{hash icon_url="/images/kodewaves-icon.svg?v=20261108-forest"}} />`);

        assert.dom('img').hasAttribute('src', '/images/transport-diary-icon.svg?v=20261125-mark4');
        assert.dom('img').hasAttribute('alt', 'Transport Diary');
    });

    test('it refreshes a saved stale Transport Diary asset version', async function (assert) {
        await render(hbs`<LogoIcon @brand={{hash icon_url="/images/transport-diary-icon.svg?v=20261124-brand"}} />`);

        assert.dom('img').hasAttribute('src', '/images/transport-diary-icon.svg?v=20261125-mark4');
        assert.dom('img').hasAttribute('alt', 'Transport Diary');
    });

    test('it preserves a configured company icon', async function (assert) {
        await render(hbs`<LogoIcon @brand={{hash icon_url="https://cdn.example.test/company-mark.png"}} />`);

        assert.dom('img').hasAttribute('src', 'https://cdn.example.test/company-mark.png');
        assert.dom('img').hasAttribute('alt', 'Company logo');
    });
});
