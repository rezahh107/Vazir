import { chromium } from 'playwright';
import assert from 'node:assert/strict';

const baseUrl = process.env.VAZIR_BASE_URL || 'http://127.0.0.1:8080';
const postId = process.env.VAZIR_POST_ID;
const blockTheme = process.env.VAZIR_BLOCK_THEME === '1';

if (!postId) {
  throw new Error('VAZIR_POST_ID is required');
}

const browser = await chromium.launch();
const context = await browser.newContext();
const page = await context.newPage();
const fontRequests = [];

page.on('request', request => {
  const url = request.url();
  if (/\/assets\/fonts\/vazir-\d+\.woff2(?:\?|$)/.test(url)) {
    fontRequests.push(url);
  }
});

const familyOf = async locator => locator.evaluate(element => getComputedStyle(element).fontFamily);
const expectVazir = async (locator, label) => {
  await locator.waitFor({ state: 'visible' });
  const family = await familyOf(locator);
  assert.match(family, /Vazir/i, `${label} should resolve to Vazir; got ${family}`);
};

await page.goto(`${baseUrl}/?p=${postId}`, { waitUntil: 'networkidle' });
await expectVazir(page.locator('body'), 'frontend body');
await expectVazir(page.getByRole('heading', { name: 'عنوان فارسی Mixed Heading' }), 'frontend heading');
await expectVazir(page.getByText('متن فارسی Mixed Latin 123', { exact: true }), 'frontend paragraph');
await expectVazir(page.locator('#vf-input'), 'frontend input');
await expectVazir(page.locator('#vf-textarea'), 'frontend textarea');
await expectVazir(page.locator('#vf-select'), 'frontend select');
await expectVazir(page.locator('#vf-button'), 'frontend button');

const pluginPreloads = await page.locator('link[rel="preload"][as="font"]').evaluateAll(nodes =>
  nodes.map(node => node.href).filter(href => /\/assets\/fonts\/vazir-/i.test(href))
);
assert.equal(pluginPreloads.length, 0, 'plugin must not emit default font preloads');
console.log(`FRONTEND_FONT_REQUESTS=${new Set(fontRequests).size}`);
console.log(`FRONTEND_FONT_URLS=${[...new Set(fontRequests)].join(',')}`);

await page.goto(`${baseUrl}/wp-login.php`, { waitUntil: 'networkidle' });
await expectVazir(page.locator('body.login'), 'login body');
await expectVazir(page.locator('#user_login'), 'login input');
await expectVazir(page.locator('#wp-submit'), 'login button');

await page.fill('#user_login', 'admin');
await page.fill('#user_pass', 'admin-password');
await Promise.all([
  page.waitForURL(/wp-admin/),
  page.click('#wp-submit'),
]);

await page.goto(`${baseUrl}/wp-admin/`, { waitUntil: 'networkidle' });
await expectVazir(page.locator('body.wp-admin'), 'wp-admin body');
await expectVazir(page.locator('.wrap h1').first(), 'wp-admin heading');

const adminIcon = page.locator('#adminmenu .wp-menu-image.dashicons-before').first();
await adminIcon.waitFor({ state: 'attached' });
const dashiconsFamily = await adminIcon.evaluate(element => getComputedStyle(element, '::before').fontFamily);
assert.match(dashiconsFamily, /dashicons/i, `wp-admin icon font must remain Dashicons; got ${dashiconsFamily}`);

await page.goto(`${baseUrl}/wp-admin/post.php?post=${postId}&action=edit`, { waitUntil: 'domcontentloaded' });
const editorFrameElement = page.locator('iframe[name="editor-canvas"]');
await editorFrameElement.waitFor({ state: 'attached', timeout: 30000 });
const editor = page.frameLocator('iframe[name="editor-canvas"]');
await expectVazir(editor.locator('.editor-styles-wrapper'), 'Post Editor canvas root');
await expectVazir(editor.getByText('متن فارسی Mixed Latin 123', { exact: true }), 'Post Editor paragraph');
await expectVazir(editor.getByRole('heading', { name: 'عنوان فارسی Mixed Heading' }), 'Post Editor heading');

if (blockTheme) {
  await page.goto(`${baseUrl}/wp-admin/site-editor.php`, { waitUntil: 'domcontentloaded' });
  const siteEditorFrame = page.locator('iframe[name="editor-canvas"]');
  await siteEditorFrame.waitFor({ state: 'attached', timeout: 30000 });
  const siteEditor = page.frameLocator('iframe[name="editor-canvas"]');
  await expectVazir(siteEditor.locator('.editor-styles-wrapper'), 'Site Editor canvas root');
}

console.log('BROWSER CHARACTERIZATION PASSED');
await browser.close();
