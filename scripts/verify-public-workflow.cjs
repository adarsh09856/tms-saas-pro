const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    for (const viewport of [{ width: 1440, height: 900 }, { width: 390, height: 844 }]) {
        const page = await browser.newPage({ viewport });
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        const response = await page.goto('https://tms.touratbhutan.info/services', { waitUntil: 'networkidle' });
        if (response.status() !== 200) throw new Error(`Services returned ${response.status()}`);
        for (const stage of ['Booking', 'Dispatch', 'Delivery', 'Finance']) {
            await page.getByRole('tab', { name: stage }).click();
            const panel = page.getByRole('tabpanel', { name: stage });
            if (!(await panel.isVisible())) throw new Error(`${stage} panel is hidden`);
        }
        if (await page.evaluate(() => document.documentElement.scrollWidth > innerWidth)) throw new Error('Horizontal overflow');
        if (errors.length) throw new Error(errors.join('; '));
        await page.locator('#workspace-preview').screenshot({ path: `/work/screens/workflow-${viewport.width}.png` });
        console.log(`${viewport.width}px: four tabs, no overflow or page errors`);
        await page.close();
    }
    await browser.close();
})().catch((error) => { console.error(error); process.exitCode = 1; });
