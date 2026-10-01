import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';
import { expectVazirmatn, makeRecorder, observeVazirmatnFontRequests } from '../../core/browser-helpers.mjs';

const artifactDir = process.env.VAZIR_LAB_ARTIFACT_DIR;
if (!artifactDir) throw new Error('VAZIR_LAB_ARTIFACT_DIR is required');
const manifest = JSON.parse(fs.readFileSync(path.join(artifactDir, 'gp-advanced-select-fixture.json'), 'utf8'));
const results = {
  status: 'PASS',
  profile: 'gp-advanced-select',
  exact_package_version: manifest.gp_advanced_select_version,
  disposition: 'NATIVE_INHERITANCE',
  production_repair: 'NOT_REQUIRED',
  scenarios: {},
  boundaries: {
    dynamically_loaded_options: 'NOT_PROVEN: deterministic GP Populate Anything lazy-load fixture is outside this profile.',
  },
};
const recorder = makeRecorder(results);
const browser = await chromium.launch();
const context = await browser.newContext();
const page = await context.newPage();

const fieldRoot = id => `#field_${manifest.form_id}_${id}`;

await recorder.record('material_inner_surfaces_and_interactions', async () => {
  await page.goto(manifest.frontend_url, { waitUntil: 'networkidle' });
  const searchRoot = fieldRoot(manifest.search_field_id);
  const initialRoot = fieldRoot(manifest.initial_field_id);
  const multiRoot = fieldRoot(manifest.multiselect_field_id);
  await page.locator(`${searchRoot} .ts-wrapper`).waitFor({ state: 'visible', timeout: 30000 });
  await page.locator(`${initialRoot} .ts-wrapper`).waitFor({ state: 'visible', timeout: 30000 });
  await page.locator(`${multiRoot} .ts-wrapper`).waitFor({ state: 'visible', timeout: 30000 });

  const families = {};
  families.control = await expectVazirmatn(page.locator(`${searchRoot} .ts-control`).first(), 'Advanced Select control');
  const search = page.locator(`${searchRoot} .ts-control input`).first();
  families.search_input = await expectVazirmatn(search, 'Advanced Select search input');
  assert.equal(await search.getAttribute('placeholder'), 'Choose a value', 'Advanced Select placeholder must remain on the authentic inner input.');
  families.initial_selected_value = await expectVazirmatn(page.locator(`${initialRoot} .ts-control .item`).first(), 'Advanced Select initial selected value');

  await search.focus();
  assert.equal(await search.evaluate(el => el === document.activeElement), true, 'Advanced Select search input must receive focus.');
  await search.fill('Beta');
  const option = page.locator(`${searchRoot} .ts-dropdown .option:visible`).first();
  await option.waitFor({ state: 'visible', timeout: 10000 });
  families.dropdown_option = await expectVazirmatn(option, 'Advanced Select dropdown option');
  await page.keyboard.press('ArrowDown');
  await page.keyboard.press('Enter');
  assert.equal(await page.locator(`#input_${manifest.form_id}_${manifest.search_field_id}`).inputValue(), 'beta', 'Keyboard selection must update the native select value.');
  families.selected_value = await expectVazirmatn(page.locator(`${searchRoot} .ts-control .item`).first(), 'Advanced Select updated selected value');

  await search.focus();
  await page.keyboard.press('Escape');
  await search.click();
  await page.locator(`${searchRoot} .ts-dropdown .option:visible`).first().waitFor({ state: 'visible', timeout: 10000 });
  families.reopened_option = await expectVazirmatn(page.locator(`${searchRoot} .ts-dropdown .option:visible`).first(), 'Advanced Select reopened dropdown option');
  await search.fill('definitely-no-match-987654');
  const noResults = page.locator(`${searchRoot} .ts-dropdown .no-results:visible`).first();
  await noResults.waitFor({ state: 'visible', timeout: 10000 });
  families.no_results = await expectVazirmatn(noResults, 'Advanced Select no-results text');

  const multiSearch = page.locator(`${multiRoot} .ts-control input`).first();
  await multiSearch.fill('Multi Alpha');
  await page.locator(`${multiRoot} .ts-dropdown .option:visible`).first().waitFor({ state: 'visible', timeout: 10000 });
  await page.keyboard.press('ArrowDown');
  await page.keyboard.press('Enter');
  const chip = page.locator(`${multiRoot} .ts-control .item`).first();
  families.multiselect_chip = await expectVazirmatn(chip, 'Advanced Select multiselect item/chip');
  assert.match((await chip.innerText()).trim(), /Multi Alpha/, 'Multiselect keyboard selection must create the expected chip.');

  return { families, selected_value: 'beta', focus_retained: true, dropdown_reopened: true, no_results_rendered: true, multiselect_item_rendered: true };
});

await recorder.record('widget_rebuild_after_page_render', async () => {
  await page.reload({ waitUntil: 'networkidle' });
  const root = fieldRoot(manifest.search_field_id);
  await page.locator(`${root} .ts-wrapper`).waitFor({ state: 'visible', timeout: 30000 });
  const family = await expectVazirmatn(page.locator(`${root} .ts-control input`).first(), 'Advanced Select rebuilt search input');
  await page.locator(`${root} .ts-control input`).first().click();
  await page.locator(`${root} .ts-dropdown .option:visible`).first().waitFor({ state: 'visible', timeout: 10000 });
  return { rebuilt_control_family: family, reopened_after_rebuild: true };
});

await recorder.record('single_authority_font_delivery', async () => observeVazirmatnFontRequests(browser, {
  url: manifest.frontend_url,
  label: 'GP Advanced Select authentic frontend',
  waitForSurface: async requestPage => {
    await requestPage.locator(`${fieldRoot(manifest.search_field_id)} .ts-control`).waitFor({ state: 'visible', timeout: 30000 });
  },
}));

if (recorder.failed()) {
  results.disposition = 'FAIL';
  results.production_repair = 'NOT_ADMITTED';
  try { await page.screenshot({ path: path.join(artifactDir, 'gp-advanced-select-browser-failure.png'), fullPage: true }); } catch {}
}
fs.writeFileSync(path.join(artifactDir, 'gp-advanced-select-browser-results.json'), `${JSON.stringify(results, null, 2)}\n`);
await context.close();
await browser.close();
if (recorder.failed()) process.exit(1);
console.log(JSON.stringify(results, null, 2));
