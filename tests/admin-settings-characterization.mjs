import { chromium } from 'playwright';
import assert from 'node:assert/strict';

const baseUrl = process.env.VAZIR_BASE_URL || 'http://127.0.0.1:8080';
const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
const page = await context.newPage();
const pageErrors = [];

page.on('pageerror', error => pageErrors.push(error.message));

const pluginStyle = 'link[href*="/assets/css/admin.css"]';
const pluginScript = 'script[src*="/assets/js/admin.js"]';
const settingsPath = '/wp-admin/options-general.php?page=vazir-font-settings';
const root = () => page.locator('.vazir-font-settings');
const advanced = () => page.locator('#vazir-font-advanced');
const advancedSummary = () => advanced().locator('summary');
const selectorTextarea = () => page.locator('#vazir-font-exclude_selectors');
const weight = value => page.locator(`input[name="vazir_font_options[font_weights][]"][value="${value}"]`);

const saveSettings = async () => {
  await Promise.all([
    page.waitForURL(/settings-updated=true/),
    page.locator('#submit').click(),
  ]);
};

const assertPersistedWeights = async expected => {
  for (const candidate of ['300', '400', '500', '700', '900']) {
    assert.equal(await weight(candidate).isChecked(), expected.includes(candidate), `weight ${candidate} persisted state mismatch`);
  }
};

const assertNoPluginOverflow = async width => {
  await page.setViewportSize({ width, height: 900 });
  await page.waitForTimeout(100);
  const metrics = await root().evaluate(element => ({
    clientWidth: element.clientWidth,
    scrollWidth: element.scrollWidth,
    left: element.getBoundingClientRect().left,
    right: element.getBoundingClientRect().right,
    viewport: window.innerWidth,
  }));
  assert.ok(metrics.scrollWidth <= metrics.clientWidth + 1, `plugin root overflowed at ${width}px: ${JSON.stringify(metrics)}`);
  assert.ok(metrics.left >= -1 && metrics.right <= metrics.viewport + 1, `plugin root escaped viewport at ${width}px: ${JSON.stringify(metrics)}`);

  if (await advanced().getAttribute('open') !== null) {
    const textareaMetrics = await selectorTextarea().evaluate(element => ({
      clientWidth: element.clientWidth,
      scrollWidth: element.scrollWidth,
      left: element.getBoundingClientRect().left,
      right: element.getBoundingClientRect().right,
      viewport: window.innerWidth,
    }));
    assert.ok(textareaMetrics.scrollWidth <= textareaMetrics.clientWidth + 1, `advanced textarea overflowed at ${width}px: ${JSON.stringify(textareaMetrics)}`);
    assert.ok(textareaMetrics.left >= -1 && textareaMetrics.right <= textareaMetrics.viewport + 1, `advanced textarea escaped viewport at ${width}px: ${JSON.stringify(textareaMetrics)}`);
  }
};

await page.goto(`${baseUrl}/wp-login.php`, { waitUntil: 'networkidle' });
await page.fill('#user_login', 'admin');
await page.fill('#user_pass', 'admin-password');
await Promise.all([
  page.waitForURL(/wp-admin/),
  page.click('#wp-submit'),
]);

await page.goto(`${baseUrl}/wp-admin/`, { waitUntil: 'networkidle' });
assert.equal(await page.locator(pluginStyle).count(), 0, 'settings CSS must not load on the dashboard');
assert.equal(await page.locator(pluginScript).count(), 0, 'settings JS must not load on the dashboard');

const settingsMenu = page.locator('#menu-settings');
await settingsMenu.hover();
const pluginMenuLink = settingsMenu.locator('a[href*="page=vazir-font-settings"]');
await pluginMenuLink.waitFor({ state: 'visible' });
await Promise.all([
  page.waitForURL(new RegExp(settingsPath.replace(/[?]/g, '\\?'))),
  pluginMenuLink.click(),
]);
await page.waitForLoadState('networkidle');

assert.equal(await page.locator('html').getAttribute('dir'), 'rtl', 'Persian admin must render RTL');
assert.match(await page.locator('body').getAttribute('class') || '', /admin-color-midnight/, 'non-default admin color scheme must be active');
await page.getByRole('heading', { level: 1, name: 'تنظیمات فونت وزیر' }).waitFor();
await page.getByText(/Vazirmatn نسخه 33\.003/).waitFor();
await page.getByLabel('سایت (فرانت‌اند)').waitFor();
await page.getByLabel('مدیریت و ویرایشگر وردپرس').waitFor();
await page.getByLabel('Gravity Forms').waitFor();
await page.getByText(/Gravity Forms اکنون فعال نیست/).waitFor();
assert.equal(await page.locator(pluginStyle).count(), 1, 'settings CSS must load exactly once on the settings page');
assert.equal(await page.locator(pluginScript).count(), 1, 'settings JS must load exactly once on the settings page');

assert.equal(await advanced().getAttribute('open'), null, 'advanced settings must be collapsed initially');
assert.equal(await selectorTextarea().isVisible(), false, 'advanced textarea must not dominate the primary path');
const initialSelectors = await selectorTextarea().inputValue();
assert.match(initialSelectors, /\[class\^="dashicons-"\]/, 'default attribute selector must be present before save');
assert.match(initialSelectors, /\[data-icon\]:before/, 'default data-icon selector must be present before save');

await weight('900').focus();
await page.keyboard.press('Tab');
assert.equal(await page.evaluate(() => document.activeElement?.tagName), 'SUMMARY', 'tab order should move from weights to advanced disclosure');
assert.equal(await advancedSummary().evaluate(element => element.matches(':focus-visible')), true, 'advanced summary should expose keyboard focus visibly');
await page.keyboard.press('Enter');
assert.notEqual(await advanced().getAttribute('open'), null, 'Enter must open advanced disclosure');
await selectorTextarea().waitFor({ state: 'visible' });
await page.keyboard.press('Tab');
assert.equal(await page.evaluate(() => document.activeElement?.id), 'vazir-font-exclude_selectors', 'first focusable control inside disclosure should be the selector textarea');
assert.equal(await selectorTextarea().evaluate(element => getComputedStyle(element).direction), 'ltr', 'technical selectors must remain LTR inside RTL admin');
assert.equal(await page.locator('.vazir-font-settings__examples code').first().evaluate(element => getComputedStyle(element).direction), 'ltr', 'technical examples must remain LTR');
await advancedSummary().focus();
await page.keyboard.press('Enter');
assert.equal(await advanced().getAttribute('open'), null, 'Enter must close advanced disclosure');

const ids = await root().locator('[id]').evaluateAll(elements => elements.map(element => element.id));
const duplicateIds = ids.filter((id, index) => ids.indexOf(id) !== index);
assert.deepEqual(duplicateIds, [], `settings controls must not have duplicate IDs: ${duplicateIds.join(',')}`);

const preview400 = page.locator('.vazir-font-preview__sample[data-weight="400"]');
await preview400.waitFor({ state: 'visible' });
const previewFamily = await preview400.evaluate(element => getComputedStyle(element).fontFamily);
assert.match(previewFamily, /Vazirmatn Preview/i, `preview must use the bundled Vazirmatn preview family; got ${previewFamily}`);

for (const candidate of ['300', '500', '900']) await weight(candidate).uncheck();
assert.equal(await page.locator('.vazir-font-preview__sample[data-weight="300"]').isVisible(), false, 'preview must hide unselected weight 300');
assert.equal(await preview400.isVisible(), true, 'preview must retain selected weight 400');
assert.equal(await page.locator('.vazir-font-preview__sample[data-weight="700"]').isVisible(), true, 'preview must retain selected weight 700');

await page.getByLabel('سایت (فرانت‌اند)').uncheck();
await saveSettings();
await page.locator('.notice-success').first().waitFor({ state: 'visible' });
assert.equal(await page.getByLabel('سایت (فرانت‌اند)').isChecked(), false, 'frontend setting must persist through options.php');
await assertPersistedWeights(['400', '700']);
assert.equal(await selectorTextarea().inputValue(), initialSelectors, 'ordinary save must preserve the default selector syntax exactly');
await page.reload({ waitUntil: 'networkidle' });
assert.equal(await page.getByLabel('سایت (فرانت‌اند)').isChecked(), false, 'frontend setting must remain persisted after reload');
await assertPersistedWeights(['400', '700']);
assert.equal(await selectorTextarea().inputValue(), initialSelectors, 'selector defaults must remain intact after reload');

await weight('400').uncheck();
await weight('700').uncheck();
await saveSettings();
const weightWarning = page.locator('.notice-warning').filter({ hasText: 'وزن 400' }).first();
await weightWarning.waitFor({ state: 'visible' });
await assertPersistedWeights(['400']);
assert.equal(await preview400.isVisible(), true, 'preview must reflect the restored 400 weight');
await page.reload({ waitUntil: 'networkidle' });
await assertPersistedWeights(['400']);

await page.getByLabel('سایت (فرانت‌اند)').check();
await weight('700').check();
await saveSettings();
await page.locator('.notice-success').first().waitFor({ state: 'visible' });
await assertPersistedWeights(['400', '700']);

await advancedSummary().click();
await selectorTextarea().fill(`${initialSelectors}\n.bad{color:red;}`);
await saveSettings();
const selectorWarning = page.locator('.notice-warning').filter({ hasText: 'CSS selector' }).first();
await selectorWarning.waitFor({ state: 'visible' });
assert.equal(await selectorTextarea().inputValue(), initialSelectors, 'invalid selector line must be rejected without corrupting valid exclusions');
await page.reload({ waitUntil: 'networkidle' });
assert.equal(await selectorTextarea().inputValue(), initialSelectors, 'valid exclusion syntax must persist exactly after validation and reload');

await advancedSummary().click();
for (const width of [320, 390, 430, 1280]) {
  await assertNoPluginOverflow(width);
}

await page.setViewportSize({ width: 390, height: 900 });
await page.evaluate(() => document.documentElement.style.setProperty('font-size', '200%', 'important'));
await assertNoPluginOverflow(390);
await page.evaluate(() => document.documentElement.style.removeProperty('font-size'));

await page.emulateMedia({ forcedColors: 'active' });
await page.setViewportSize({ width: 1280, height: 900 });
await weight('900').focus();
await page.keyboard.press('Tab');
const forcedFocusOutline = await advancedSummary().evaluate(element => getComputedStyle(element).outlineStyle);
assert.notEqual(forcedFocusOutline, 'none', 'keyboard focus must remain visible in forced-colors mode');
await page.emulateMedia({ forcedColors: 'none' });

assert.deepEqual(pageErrors, [], `settings page must not emit browser page errors: ${pageErrors.join(' | ')}`);
console.log('ADMIN SETTINGS CHARACTERIZATION PASSED');
await browser.close();
