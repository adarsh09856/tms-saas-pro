const fs = require('node:fs');
const { chromium } = require('playwright');

const baseUrl = process.env.TMS_URL || 'https://tms.touratbhutan.info';
const output = process.env.TMS_SCREENSHOT_DIR || '/work/screens';
const pages = process.env.TMS_PATHS ? process.env.TMS_PATHS.split(',') : ['/', '/services', '/auth'];
const viewports = [
    { name: 'desktop', width: 1440, height: 900 },
    { name: 'mobile', width: 390, height: 844 },
];

(async () => {
    fs.mkdirSync(output, { recursive: true });
    const browser = await chromium.launch({ headless: true });
    const results = [];
    for (const viewport of viewports) {
        const context = await browser.newContext({ viewport, deviceScaleFactor: 1 });
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        for (const path of pages) {
            const response = await page.goto(new URL(path, baseUrl).toString(), { waitUntil: 'domcontentloaded', timeout: 30000 });
            try {
                await page.waitForLoadState('networkidle', { timeout: 10000 });
            } catch {}
            const name = path === '/' ? 'home' : path.slice(1).replaceAll('/', '-');
            await page.screenshot({ path: `${output}/${name}-${viewport.name}.png`, fullPage: true });
            const layout = await page.evaluate(() => {
                const selectors = ['html', 'body', '#ember-app', '.auth-shell', '.auth-shell-main', '.auth-workspace'];
                return Object.fromEntries(selectors.map((selector) => {
                    const element = document.querySelector(selector);
                    if (!element) return [selector, null];
                    const style = getComputedStyle(element);
                    const rect = element.getBoundingClientRect();
                    return [selector, { height: Math.round(rect.height), scrollHeight: element.scrollHeight, overflowY: style.overflowY, position: style.position }];
                }));
            });
            results.push({ path, viewport: viewport.name, status: response?.status(), title: await page.title(), horizontalOverflow: await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), layout: path === '/auth' ? layout : undefined, errors: [...errors] });
            errors.length = 0;
        }
        await context.close();
    }
    await browser.close();
    fs.writeFileSync(`${output}/results.json`, JSON.stringify(results, null, 2));
    console.log(JSON.stringify(results));
})().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
