import { module, test } from 'qunit';
import { setupRenderingTest } from '@fleetbase/console/tests/helpers';
import { click, find, render } from '@ember/test-helpers';
import { hbs } from 'ember-cli-htmlbars';
import Service from '@ember/service';
import Evented from '@ember/object/evented';

class MobileMediaStub extends Service.extend(Evented) {
    isMobile = true;
}

module('Integration | Component | layout/header/dropdown overlay', function (hooks) {
    setupRenderingTest(hooks);

    hooks.afterEach(function () {
        document.body.classList.remove('console-overlay-regression');
    });

    test('desktop account menus render outside the header stacking context', async function (assert) {
        document.body.classList.add('console-overlay-regression');
        this.set('items', [{ text: 'Profile', href: 'javascript:;' }]);

        await render(hbs`
            <header class="next-view-header">
                <Layout::Header::Dropdown @items={{this.items}} @contentClass="console-header-dropdown-layer">
                    <span>Account</span>
                </Layout::Header::Dropdown>
            </header>
            <BasicDropdownWormhole />
        `);

        await click('.next-header-menu-item-dd-trigger');

        const content = find('.next-header-menu-item-dd-content');
        assert.ok(content, 'the menu is rendered');
        assert.notOk(content.closest('.next-view-header'), 'the menu is portaled outside the header so it cannot be clipped');
        assert.strictEqual(window.getComputedStyle(find('.console-header-dropdown-layer')).zIndex, '1270', 'the menu sits above drawers and chat overlays');
    });

    test('mobile account menus use the same elevated portal layer', async function (assert) {
        this.owner.unregister('service:media');
        this.owner.register('service:media', MobileMediaStub);
        document.body.classList.add('console-overlay-regression');
        this.set('items', [{ text: 'Profile', href: 'javascript:;' }]);

        await render(hbs`
            <header class="next-view-header">
                <Layout::Header::Dropdown @items={{this.items}} @contentClass="console-header-dropdown-layer">
                    <span>Account</span>
                </Layout::Header::Dropdown>
            </header>
            <BasicDropdownWormhole />
        `);

        await click('.next-header-menu-item-dd-trigger');

        const content = find('.next-header-menu-item-dd-content.is-mobile');
        assert.ok(content, 'the mobile menu is rendered');
        assert.notOk(content.closest('.next-view-header'), 'the mobile menu is portaled outside the header');
        assert.strictEqual(window.getComputedStyle(find('.console-header-dropdown-layer')).zIndex, '1270', 'mobile menu stays above drawers and chat overlays');
    });

    test('the navigation customizer portal remains above the console content stack', async function (assert) {
        document.body.classList.add('console-overlay-regression');
        this.setProperties({ allItems: [], pinnedIds: [], maxVisible: 5, onApply: () => {}, onClose: () => {} });

        await render(hbs`
            <div class="console-dashboard-page" data-test-dashboard-content></div>
            <div id="application-root-wormhole"></div>
            <EmberWormhole @to="application-root-wormhole">
                <Layout::Header::SmartNavMenu::Customizer
                    @allItems={{this.allItems}}
                    @pinnedIds={{this.pinnedIds}}
                    @maxVisible={{this.maxVisible}}
                    @onApply={{this.onApply}}
                    @onClose={{this.onClose}}
                />
            </EmberWormhole>
        `);

        const backdrop = find('.snm-customizer-backdrop');
        const panel = find('.snm-customizer-panel');
        assert.ok(backdrop && panel, 'the customizer renders in its portal');
        assert.strictEqual(Number(window.getComputedStyle(backdrop).zIndex), 10000, 'the backdrop overlays page content');
        assert.strictEqual(Number(window.getComputedStyle(panel).zIndex), 10001, 'the customizer panel overlays the backdrop and page content');
    });

    test('console overlay and map styles match the live console body class', async function (assert) {
        document.body.classList.add('console-overlay-regression');

        await render(hbs`
            <div class="chat-inbox-overlay" data-test-chat-overlay></div>
            <div class="next-map-container-view-switch">
                <button type="button" class="active" data-test-map-mode>Map</button>
            </div>
        `);

        assert.strictEqual(window.getComputedStyle(this.element.querySelector('[data-test-chat-overlay]')).zIndex, '1250', 'chat remains above the header stacking layer');
        assert.strictEqual(window.getComputedStyle(this.element.querySelector('[data-test-map-mode]')).backgroundColor, 'rgb(66, 107, 87)', 'the selected map mode uses the forest token');
    });

    test('drawers and dialogs render above the fixed console header', async function (assert) {
        document.body.classList.add('console-overlay-regression');

        await render(hbs`
            <header class="next-view-header" data-test-console-header></header>
            <div class="next-content-overlay" data-test-content-overlay></div>
            <div class="modal" data-test-console-modal></div>
        `);

        const headerZIndex = Number(window.getComputedStyle(this.element.querySelector('[data-test-console-header]')).zIndex);
        const drawerZIndex = Number(window.getComputedStyle(this.element.querySelector('[data-test-content-overlay]')).zIndex);
        const modalZIndex = Number(window.getComputedStyle(this.element.querySelector('[data-test-console-modal]')).zIndex);

        assert.ok(drawerZIndex > headerZIndex, 'full-screen drawers cover the header');
        assert.ok(modalZIndex > drawerZIndex, 'dialogs opened from drawers cover the drawer');
    });
});
