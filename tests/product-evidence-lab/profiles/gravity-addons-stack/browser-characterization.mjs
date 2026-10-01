import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';
import { expectVazirmatn, makeRecorder, observeVazirmatnFontRequests } from '../../core/browser-helpers.mjs';

const artifactDir = process.env.VAZIR_LAB_ARTIFACT_DIR;
if (!artifactDir) throw new Error('VAZIR_LAB_ARTIFACT_DIR is required');
const manifest = JSON.parse(fs.readFileSync(path.join(artifactDir, 'gravity-addons-stack-fixture.json'), 'utf8'));
const results = {
  status: 'PASS', profile: 'gravity-addons-stack', scenarios: {},
  claim_ceiling: 'Representative coexistence with all exact admitted Gravity packages active; not exhaustive compatibility.',
};
const recorder = makeRecorder(results);
const browser = await chromium.launch();
const context = await browser.newContext();
const page = await context.newPage();
const advancedRoot = `#field_${manifest.form_id}_${manifest.advanced_select_field_id}`;
const fileRoot = `#field_${manifest.form_id}_${manifest.file_upload_field_id}`;

await recorder.record('both_addons_render_and_remain_interactive', async () => {
  await page.goto(manifest.frontend_url, { waitUntil: 'networkidle' });
  const search = page.locator(`${advancedRoot} .ts-control input`).first();
  await search.waitFor({ state: 'visible', timeout: 30000 });
  const uploadButton = page.locator(`${fileRoot} .gpfup__select-files`).first();
  await uploadButton.waitFor({ state: 'visible', timeout: 30000 });
  const families = {
    advanced_select_input: await expectVazirmatn(search, 'combined add-on Advanced Select inner input'),
    file_upload_button: await expectVazirmatn(uploadButton, 'combined add-on File Upload Pro button'),
  };
  await search.fill('Combined Beta');
  await page.locator(`${advancedRoot} .ts-dropdown .option:visible`).first().waitFor({ state: 'visible', timeout: 10000 });
  await page.keyboard.press('ArrowDown');
  await page.keyboard.press('Enter');
  return { families, advanced_select_value: await page.locator(`#input_${manifest.form_id}_${manifest.advanced_select_field_id}`).inputValue(), upload_button_visible: true };
});

await recorder.record('combined_single_authority_font_delivery', async () => observeVazirmatnFontRequests(browser, {
  url: manifest.frontend_url,
  label: 'Combined Gravity add-ons same-page render',
  waitForSurface: async requestPage => {
    await requestPage.locator(`${advancedRoot} .ts-control`).waitFor({ state: 'visible', timeout: 30000 });
    await requestPage.locator(`${fileRoot} .gpfup__droparea`).waitFor({ state: 'visible', timeout: 30000 });
  },
}));

fs.writeFileSync(path.join(artifactDir, 'gravity-addons-stack-browser-results.json'), `${JSON.stringify(results, null, 2)}\n`);
if (recorder.failed()) {
  try { await page.screenshot({ path: path.join(artifactDir, 'gravity-addons-stack-browser-failure.png'), fullPage: true }); } catch {}
}
await context.close();
await browser.close();
if (recorder.failed()) process.exit(1);
console.log(JSON.stringify(results, null, 2));
