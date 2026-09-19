import { chromium } from 'playwright';
import assert from 'node:assert/strict';

const baseUrl = process.env.VAZIR_BASE_URL || 'http://127.0.0.1:8080';
const postId = process.env.VAZIR_POST_ID;
const blockTheme = process.env.VAZIR_BLOCK_THEME === '1';
const fontRequestRe = /\/assets\/fonts\/vazirmatn-(\d+)\.woff2(?:\?|$)/;
const selectedWeights = new Set(['400', '700']);

if (!postId) {
  throw new Error('VAZIR_POST_ID is required');
}

const browser = await chromium.launch();
const context = await browser.newContext();
const page = await context.newPage();
const fontRequests = [];
const fontResponseTasks = [];
const fontBytes = new Map();

page.on('request', request => {
  const url = request.url();
  if (fontRequestRe.test(url)) fontRequests.push(url);
});

page.on('response', response => {
  const url = response.url();
  if (!fontRequestRe.test(url)) return;
  fontResponseTasks.push((async () => {
    const body = await response.body();
    fontBytes.set(url, body.length);
  })());
});

const familyOf = async locator => locator.evaluate(element => getComputedStyle(element).fontFamily);
const expectVazirmatn = async (locator, label) => {
  await locator.waitFor({ state: 'visible' });
  const family = await familyOf(locator);
  assert.match(family, /Vazirmatn/i, `${label} should resolve to Vazirmatn; got ${family}`);
};

const measure = locator => locator.evaluate(element => {
  const style = getComputedStyle(element);
  const rect = element.getBoundingClientRect();
  return {
    width: rect.width,
    height: rect.height,
    lineHeight: Number.parseFloat(style.lineHeight),
    clientWidth: element.clientWidth,
    clientHeight: element.clientHeight,
    scrollWidth: element.scrollWidth,
    scrollHeight: element.scrollHeight,
  };
});

const assertSingleLineControlLayout = async (locator, label) => {
  const metrics = await measure(locator);
  assert.ok(metrics.width > 0 && metrics.height > 0, `${label} must have non-zero dimensions`);
  if (Number.isFinite(metrics.lineHeight)) {
    assert.ok(metrics.height + 0.5 >= metrics.lineHeight, `${label} height must accommodate line-height: ${JSON.stringify(metrics)}`);
  }
  assert.ok(metrics.scrollHeight <= metrics.clientHeight + 2, `${label} must not vertically clip after font migration: ${JSON.stringify(metrics)}`);
  return metrics;
};

await page.goto(`${baseUrl}/?p=${postId}`, { waitUntil: 'networkidle' });
await expectVazirmatn(page.locator('body'), 'frontend body');
await expectVazirmatn(page.getByRole('heading', { name: 'عنوان فارسی Mixed Heading' }), 'frontend heading');
await expectVazirmatn(page.getByText('متن فارسی Mixed Latin 123', { exact: true }), 'frontend paragraph');
await expectVazirmatn(page.locator('#vf-input'), 'frontend input');
await expectVazirmatn(page.locator('#vf-textarea'), 'frontend textarea');
await expectVazirmatn(page.locator('#vf-select'), 'frontend select');
await expectVazirmatn(page.locator('#vf-button'), 'frontend button');

const layoutMetrics = {
  input: await assertSingleLineControlLayout(page.locator('#vf-input'), 'frontend input'),
  select: await assertSingleLineControlLayout(page.locator('#vf-select'), 'frontend select'),
  button: await assertSingleLineControlLayout(page.locator('#vf-button'), 'frontend button'),
};
const cardMetrics = await measure(page.locator('#vf-layout-card'));
const tableMetrics = await measure(page.locator('#vf-layout-table'));
assert.ok(cardMetrics.width > 0 && tableMetrics.width > 0, 'layout card/table must render with non-zero dimensions');
assert.ok(tableMetrics.scrollWidth <= tableMetrics.clientWidth + 2, `layout table must not horizontally overflow after font migration: ${JSON.stringify(tableMetrics)}`);
layoutMetrics.card = cardMetrics;
layoutMetrics.table = tableMetrics;

const excludedText = page.locator('#vf-excluded-text');
await excludedText.waitFor({ state: 'visible' });
const excludedFamily = await familyOf(excludedText);
assert.doesNotMatch(excludedFamily, /Vazirmatn/i, `excluded component text must not resolve to Vazirmatn; got ${excludedFamily}`);
assert.match(excludedFamily, /monospace/i, `excluded component text must retain its explicit non-Vazirmatn family; got ${excludedFamily}`);

const pluginPreloads = await page.locator('link[rel="preload"][as="font"]').evaluateAll(nodes =>
  nodes.map(node => node.href).filter(href => /\/assets\/fonts\/vazirmatn-/i.test(href))
);
assert.equal(pluginPreloads.length, 0, 'plugin must not emit default font preloads');
await page.evaluate(async () => { if (document.fonts?.ready) await document.fonts.ready; });
await page.waitForLoadState('networkidle');
await Promise.all(fontResponseTasks);

const counts = new Map();
for (const url of fontRequests) counts.set(url, (counts.get(url) || 0) + 1);
const duplicates = [...counts.entries()].filter(([, count]) => count > 1);
assert.deepEqual(duplicates, [], `frontend must not duplicate bundled Vazirmatn font URL requests: ${JSON.stringify(duplicates)}`);
assert.ok(counts.size > 0, 'frontend must request at least one bundled Vazirmatn font');
const observedWeights = [...counts.keys()].map(url => {
  const match = url.match(fontRequestRe);
  return match ? match[1] : '';
});
assert.ok(observedWeights.every(weight => selectedWeights.has(weight)), `frontend requested an unselected weight: ${observedWeights.join(',')}`);
for (const url of counts.keys()) {
  assert.ok((fontBytes.get(url) || 0) > 0, `font response must have measured bytes for ${url}`);
}
const totalFontBytes = [...fontBytes.values()].reduce((sum, value) => sum + value, 0);
console.log(`FRONTEND_FONT_REQUESTS=${counts.size}`);
console.log(`FRONTEND_FONT_URLS=${[...counts.keys()].join(',')}`);
console.log(`FRONTEND_FONT_BYTES=${totalFontBytes}`);
console.log(`FRONTEND_LAYOUT_METRICS=${JSON.stringify(layoutMetrics)}`);

await page.goto(`${baseUrl}/wp-login.php`, { waitUntil: 'networkidle' });
await expectVazirmatn(page.locator('body.login'), 'login body');
await expectVazirmatn(page.locator('#user_login'), 'login input');
await expectVazirmatn(page.locator('#wp-submit'), 'login button');

await page.fill('#user_login', 'admin');
await page.fill('#user_pass', 'admin-password');
await Promise.all([
  page.waitForURL(/wp-admin/),
  page.click('#wp-submit'),
]);

await page.goto(`${baseUrl}/wp-admin/`, { waitUntil: 'networkidle' });
await expectVazirmatn(page.locator('body.wp-admin'), 'wp-admin body');
await expectVazirmatn(page.locator('.wrap h1').first(), 'wp-admin heading');

const adminIcon = page.locator('#adminmenu .wp-menu-image.dashicons-before').first();
await adminIcon.waitFor({ state: 'attached' });
const dashiconsFamily = await adminIcon.evaluate(element => getComputedStyle(element, '::before').fontFamily);
assert.match(dashiconsFamily, /dashicons/i, `wp-admin icon font must remain Dashicons; got ${dashiconsFamily}`);

await page.goto(`${baseUrl}/wp-admin/post.php?post=${postId}&action=edit`, { waitUntil: 'domcontentloaded' });
const editorFrameElement = page.locator('iframe[name="editor-canvas"]');
await editorFrameElement.waitFor({ state: 'attached', timeout: 30000 });
const editor = page.frameLocator('iframe[name="editor-canvas"]');
await expectVazirmatn(editor.locator('.editor-styles-wrapper'), 'Post Editor canvas root');
await expectVazirmatn(editor.getByText('متن فارسی Mixed Latin 123', { exact: true }).first(), 'Post Editor paragraph');
await expectVazirmatn(editor.getByText('عنوان فارسی Mixed Heading', { exact: true }).first(), 'Post Editor heading');

if (blockTheme) {
  await page.goto(`${baseUrl}/wp-admin/site-editor.php`, { waitUntil: 'domcontentloaded' });
  const siteEditorFrame = page.locator('iframe[name="editor-canvas"]');
  await siteEditorFrame.waitFor({ state: 'attached', timeout: 30000 });
  const siteEditor = page.frameLocator('iframe[name="editor-canvas"]');
  await expectVazirmatn(siteEditor.locator('.editor-styles-wrapper'), 'Site Editor canvas root');
}

console.log('BROWSER CHARACTERIZATION PASSED');
await browser.close();
