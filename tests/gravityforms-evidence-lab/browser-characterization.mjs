import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { observeVazirFontRequests } from '../product-evidence-lab/core/browser-helpers.mjs';

const baseUrl = process.env.VAZIR_GF_BASE_URL || 'http://127.0.0.1:8080';
const artifactDir = process.env.VAZIR_GF_ARTIFACT_DIR;
const adminUser = process.env.VAZIR_GF_ADMIN_USER || 'vazir_lab_admin';
const adminPassword = process.env.VAZIR_GF_ADMIN_PASSWORD || 'vazir-lab-admin-password';

if (!artifactDir) throw new Error('VAZIR_GF_ARTIFACT_DIR is required');
const manifest = JSON.parse(fs.readFileSync(path.join(artifactDir, 'fixture-manifest.json'), 'utf8'));
const results = { status: 'PASS', scenarios: {}, browser: {} };
let failed = false;

const record = async (name, fn) => {
  try {
    const detail = await fn();
    results.scenarios[name] = { status: 'PASS', ...(detail || {}) };
  } catch (error) {
    failed = true;
    results.status = 'FAIL';
    results.scenarios[name] = { status: 'FAIL', reason: error instanceof Error ? error.message : String(error) };
  }
};

const browser = await chromium.launch();
results.browser.version = browser.version();
const context = await browser.newContext();
const page = await context.newPage();
const dynamicRequests = [];
page.on('request', request => {
  if (['xhr', 'fetch'].includes(request.resourceType())) dynamicRequests.push(request.url());
});

const familyOf = locator => locator.evaluate(el => getComputedStyle(el).fontFamily);
const expectVazir = async (locator, label) => {
  await locator.waitFor({ state: 'visible', timeout: 30000 });
  const family = await familyOf(locator);
  assert.match(family, /Vazir/i, `${label} should resolve to Vazir; got ${family}`);
  return family;
};
const expectNotVazir = async (locator, label, expected) => {
  await locator.waitFor({ state: 'visible', timeout: 30000 });
  const family = await familyOf(locator);
  assert.doesNotMatch(family, /Vazir/i, `${label} must not resolve to Vazir; got ${family}`);
  if (expected) assert.match(family, expected, `${label} should retain ${expected}; got ${family}`);
  return family;
};
const pseudoFamily = (locator, pseudo = '::before') => locator.evaluate((el, p) => getComputedStyle(el, p).fontFamily, pseudo);

const login = async () => {
  await page.goto(`${baseUrl}/wp-login.php`, { waitUntil: 'domcontentloaded' });
  await page.fill('#user_login', adminUser);
  await page.fill('#user_pass', adminPassword);
  await Promise.all([
    page.waitForURL(/wp-admin\//, { timeout: 30000 }),
    page.click('#wp-submit'),
  ]);
};

await record('frontend_orbital_theme_framework', async () => {
  await page.goto(manifest.orbital_url, { waitUntil: 'networkidle' });
  const wrapper = page.locator(`#gform_wrapper_${manifest.orbital_form_id}`);
  await wrapper.waitFor({ state: 'visible' });
  await expectVazir(wrapper, 'Orbital wrapper');
  await expectVazir(page.locator(`#field_${manifest.orbital_form_id}_1 .gfield_label`), 'Orbital label');
  await expectVazir(page.locator(`#field_${manifest.orbital_form_id}_1 .gfield_description`), 'Orbital description');
  await expectVazir(page.locator(`#input_${manifest.orbital_form_id}_1`), 'Orbital text input');
  await expectVazir(page.locator(`#input_${manifest.orbital_form_id}_2`), 'Orbital textarea');
  await expectVazir(page.locator(`#input_${manifest.orbital_form_id}_3`), 'Orbital select');
  await expectVazir(page.locator(`#gform_submit_button_${manifest.orbital_form_id}`), 'Orbital submit button');

  const className = await wrapper.getAttribute('class');
  assert.match(className || '', /gform-theme--framework/, 'Orbital wrapper must use Theme Framework');
  assert.match(className || '', /gform-theme--orbital/, 'Orbital wrapper must use Orbital theme');
  const frameworkFamily = await wrapper.evaluate(el => getComputedStyle(el).getPropertyValue('--gf-font-family-base'));
  assert.match(frameworkFamily, /Vazir/i, `--gf-font-family-base should contain Vazir; got ${frameworkFamily}`);
  return { theme_framework_custom_property: frameworkFamily };
});

await record('frontend_exclusions_and_icons', async () => {
  await page.goto(manifest.dynamic_url, { waitUntil: 'networkidle' });
  await expectVazir(page.locator(`#input_${manifest.dynamic_form_id}_1`), 'dynamic text input');
  await expectVazir(page.getByRole('button', { name: 'بعدی' }), 'dynamic next button');
  await expectNotVazir(page.locator('#vf-gf-excluded-text'), 'excluded Gravity Forms descendant', /monospace/i);

  const icon = page.locator(`#field_${manifest.dynamic_form_id}_5 .dashicons`).first();
  await icon.waitFor({ state: 'attached', timeout: 30000 });
  const iconFamily = await pseudoFamily(icon);
  assert.match(iconFamily, /dashicons/i, `Gravity Forms password icon must remain Dashicons; got ${iconFamily}`);
  return { password_icon_family: iconFamily };
});

await record('conditional_logic_transition', async () => {
  await page.goto(manifest.dynamic_url, { waitUntil: 'networkidle' });
  const conditional = page.locator(`#field_${manifest.dynamic_form_id}_3`);
  await conditional.waitFor({ state: 'attached' });
  assert.equal(await conditional.isVisible(), false, 'conditional field should start hidden');
  await page.getByLabel('نمایش بده').check();
  await conditional.waitFor({ state: 'visible', timeout: 10000 });
  await expectVazir(page.locator(`#input_${manifest.dynamic_form_id}_3`), 'conditional field after show transition');
  await page.getByLabel('پنهان بمان').check();
  await conditional.waitFor({ state: 'hidden', timeout: 10000 });
});

await record('ajax_validation_and_multipage_rerenders', async () => {
  await page.goto(manifest.dynamic_url, { waitUntil: 'networkidle' });
  const requestBaseline = dynamicRequests.length;
  await page.getByRole('button', { name: 'بعدی' }).click();
  await page.locator('.gform_validation_errors').waitFor({ state: 'visible', timeout: 30000 });
  await expectVazir(page.locator(`#input_${manifest.dynamic_form_id}_1`), 'required field after validation rerender');
  await expectNotVazir(page.locator('#vf-gf-excluded-text'), 'excluded descendant after validation rerender', /monospace/i);

  await page.locator(`#input_${manifest.dynamic_form_id}_1`).fill('رضا Runtime');
  await page.getByRole('button', { name: 'بعدی' }).click();
  const pageTwo = page.locator(`#input_${manifest.dynamic_form_id}_7`);
  await pageTwo.waitFor({ state: 'visible', timeout: 30000 });
  await expectVazir(pageTwo, 'page-two input after AJAX transition');
  await expectVazir(page.getByRole('button', { name: 'قبلی' }), 'previous button after AJAX transition');

  await page.getByRole('button', { name: 'قبلی' }).click();
  await page.locator(`#input_${manifest.dynamic_form_id}_1`).waitFor({ state: 'visible', timeout: 30000 });
  await expectVazir(page.locator(`#input_${manifest.dynamic_form_id}_1`), 'page-one input after previous transition');

  const observed = dynamicRequests.slice(requestBaseline);
  assert.ok(observed.length > 0, 'AJAX form transitions should produce at least one fetch/XHR request');
  return { dynamic_request_count: observed.length };
});

await record('legacy_markup_frontend', async () => {
  await page.goto(manifest.legacy_url, { waitUntil: 'networkidle' });
  const wrapper = page.locator(`#gform_wrapper_${manifest.legacy_form_id}`);
  await wrapper.waitFor({ state: 'visible' });
  const className = await wrapper.getAttribute('class');
  assert.match(className || '', /gform_legacy_markup_wrapper/, 'legacy fixture must use supported Legacy Markup wrapper');
  assert.doesNotMatch(className || '', /gform-theme--framework/, 'legacy fixture must not masquerade as Theme Framework markup');
  await expectVazir(wrapper, 'Legacy wrapper');
  await expectVazir(page.locator(`#input_${manifest.legacy_form_id}_1`), 'Legacy text input');
  await expectVazir(page.locator(`#input_${manifest.legacy_form_id}_2`), 'Legacy select');
});

await record('font_request_deduplication_single_render', async () => {
  return observeVazirFontRequests(browser, {
    url: manifest.orbital_url,
    label: 'Gravity Forms single render',
    waitForSurface: async requestPage => {
      await requestPage.locator(`#gform_wrapper_${manifest.orbital_form_id}`).waitFor({ state: 'visible', timeout: 30000 });
    },
  });
});

await login();

await record('gravity_forms_preview', async () => {
  await page.goto(manifest.preview_url, { waitUntil: 'networkidle' });
  const wrapper = page.locator(`#gform_wrapper_${manifest.dynamic_form_id}`);
  await wrapper.waitFor({ state: 'visible', timeout: 30000 });
  await expectVazir(page.locator(`#input_${manifest.dynamic_form_id}_1`), 'Preview input');
  assert.equal(await page.locator('#vazir-font-gravity-forms-inline-css').count(), 1, 'Preview must print the registered Vazir Gravity Forms style handle');
  await expectNotVazir(page.locator('#vf-gf-excluded-text'), 'Preview excluded descendant', /monospace/i);
  const icon = page.locator(`#field_${manifest.dynamic_form_id}_5 .dashicons`).first();
  await icon.waitFor({ state: 'attached', timeout: 30000 });
  const iconFamily = await pseudoFamily(icon);
  assert.match(iconFamily, /dashicons/i, `Preview password icon must remain Dashicons; got ${iconFamily}`);
});

await record('form_editor_and_no_conflict_mode', async () => {
  await page.goto(manifest.form_editor_url, { waitUntil: 'domcontentloaded' });
  await page.locator('.gform_editor').waitFor({ state: 'visible', timeout: 30000 });
  await expectVazir(page.locator('.gform_editor .gfield_label').first(), 'Form Editor field label');
  assert.equal(await page.locator('#vazir-font-gravity-forms-inline-css').count(), 1, 'GF style handle must survive No Conflict Mode');
  assert.equal(await page.locator('#vazir-font-admin-runtime-inline-css').count(), 1, 'Vazir admin handle must survive No Conflict Mode');

  const gfIcon = page.locator('.gform-icon').first();
  await gfIcon.waitFor({ state: 'attached', timeout: 30000 });
  const gfIconFamily = await familyOf(gfIcon);
  assert.match(gfIconFamily, /gform-icons-admin/i, `Gravity Forms admin icon must retain gform-icons-admin; got ${gfIconFamily}`);
  return { gravity_forms_admin_icon_family: gfIconFamily };
});

await record('normal_wp_admin_under_gf_noconflict_setting', async () => {
  await page.goto(`${baseUrl}/wp-admin/`, { waitUntil: 'networkidle' });
  await expectVazir(page.locator('body.wp-admin'), 'normal wp-admin body');
  const dashicon = page.locator('#adminmenu .wp-menu-image.dashicons-before').first();
  await dashicon.waitFor({ state: 'attached', timeout: 30000 });
  const family = await pseudoFamily(dashicon);
  assert.match(family, /dashicons/i, `normal wp-admin Dashicons must remain protected; got ${family}`);
});

fs.writeFileSync(path.join(artifactDir, 'browser-results.json'), `${JSON.stringify(results, null, 2)}\n`);

if (failed) {
  try { await page.screenshot({ path: path.join(artifactDir, 'browser-failure.png'), fullPage: true }); } catch {}
}
await context.close();
await browser.close();

if (failed) {
  console.error(JSON.stringify(results, null, 2));
  process.exit(1);
}
console.log('LICENSED GRAVITY FORMS BROWSER CHARACTERIZATION PASSED');
console.log(JSON.stringify(results, null, 2));
