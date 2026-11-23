import { module, test } from 'qunit';
import { setupRenderingTest } from '@fleetbase/console/tests/helpers';
import { click, render } from '@ember/test-helpers';
import { hbs } from 'ember-cli-htmlbars';

module('Integration | Component | layout/header/dropdown overlay', function (hooks) {
    setupRenderingTest(hooks);

    hooks.afterEach(function () {
        document.body.classList.remove('console-overlay-regression');
    });

    test('desktop account menus render outside the header stacking context', async function (assert) {
        this.set('items', [{ text: 'Profile', href: 'javascript:;' }]);

        await render(hbs`
            <header class="next-view-header">
                <Layout::Header::Dropdown @items={{this.items}}>
                    <span>Account</span>
                </Layout::Header::Dropdown>
            </header>
            <BasicDropdownWormhole />
        `);

        await click('.next-header-menu-item-dd-trigger');

        assert.dom('#ember-basic-dropdown-wormhole .next-header-menu-item-dd-content').exists('the menu is portaled outside the header');
        assert.dom('.next-view-header .next-header-menu-item-dd-content').doesNotExist('the menu cannot be clipped by the header');
    });

    test('console overlay and map styles match the live console body class', async function (assert) {
        document.body.classList.add('console-overlay-regression');

        await render(hbs`
            <div class="chat-inbox-overlay" data-test-chat-overlay></div>
            <div class="next-map-container-view-switch">
                <button class="active" data-test-map-mode>Map</button>
            </div>
        `);

        assert.strictEqual(window.getComputedStyle(this.element.querySelector('[data-test-chat-overlay]')).zIndex, '1200', 'chat remains above the header stacking layer');
        assert.strictEqual(window.getComputedStyle(this.element.querySelector('[data-test-map-mode]')).backgroundColor, 'rgb(66, 107, 87)', 'the selected map mode uses the forest token');
    });
});
