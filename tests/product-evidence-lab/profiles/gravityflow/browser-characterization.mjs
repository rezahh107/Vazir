import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { expectVazirmatn, expectNotVazirmatn, pseudoFamily, login, makeRecorder } from '../../core/browser-helpers.mjs';
const baseUrl = process.env.VAZIR_LAB_BASE_URL || 'http://127.0.0.1:8080';
const artifactDir = process.env.VAZIR_LAB_ARTIFACT_DIR;
const user = process.env.VAZIR_LAB_ADMIN_USER || 'vazir_lab_admin';
const password = process.env.VAZIR_LAB_ADMIN_PASSWORD || 'vazir-lab-admin-password';
if (!artifactDir) throw new Error('VAZIR_LAB_ARTIFACT_DIR is required');
const manifest = JSON.parse(fs.readFileSync(path.join(artifactDir, 'gravityflow-fixture.json'), 'utf8'));
const results = { status: 'PASS', profile: 'gravityflow', scenarios: {}, claim_ceiling: {} };
const recorder = makeRecorder(results);
const browser = await chromium.launch(); const context = await browser.newContext(); const page = await context.newPage();
await login(page, baseUrl, user, password);
await recorder.record('admin_current_inbox', async () => {
  await page.goto(manifest.admin_inbox_url, { waitUntil: 'domcontentloaded' });
  const inbox = page.locator('.gflow-inbox').first(); await inbox.waitFor({ state: 'visible', timeout: 30000 });
  await page.locator('.ag-root-wrapper, .gravityflow-entry-table').first().waitFor({ state: 'visible', timeout: 30000 });
  await expectVazirmatn(inbox, 'Gravity Flow admin inbox');
  const search = page.locator('input.gflow-inbox__search, .gravityflow-entry-table__header-search-input').first(); if (await search.count()) await expectVazirmatn(search, 'Gravity Flow inbox search control');
  const searchIcon = page.locator('.gflow-icon--search').first();
  if (await searchIcon.count()) { const family = await pseudoFamily(searchIcon); assert.match(family, /gflow-icons-common/i, `Gravity Flow search icon must retain gflow-icons-common; got ${family}`); return { icon_family: family }; }
  results.claim_ceiling.icon_family = 'NOT_PROVEN: current rendered inbox did not expose .gflow-icon--search'; return {};
});
await recorder.record('frontend_inbox_shortcode', async () => {
  await page.goto(manifest.frontend_inbox_url, { waitUntil: 'domcontentloaded' });
  const inbox = page.locator('.gflow-inbox, #gravityflow-inbox').first(); await inbox.waitFor({ state: 'visible', timeout: 30000 });
  await expectVazirmatn(inbox, 'Gravity Flow frontend inbox'); await expectNotVazirmatn(page.locator('#vf-flow-excluded'), 'Gravity Flow profile exclusion fixture', /monospace/i);
});
await recorder.record('gravity_forms_prerequisite_still_operational', async () => {
  await page.goto(manifest.gravity_forms_url, { waitUntil: 'networkidle' });
  const wrapper = page.locator(`#gform_wrapper_${manifest.form_id}`); await wrapper.waitFor({ state: 'visible', timeout: 30000 });
  await expectVazirmatn(wrapper, 'Gravity Forms prerequisite with Gravity Flow active'); await expectVazirmatn(page.locator(`#input_${manifest.form_id}_1`), 'Gravity Forms prerequisite control with Gravity Flow active');
});
fs.writeFileSync(path.join(artifactDir, 'gravityflow-browser-results.json'), `${JSON.stringify(results, null, 2)}\n`);
if (recorder.failed()) { try { await page.screenshot({ path: path.join(artifactDir, 'gravityflow-browser-failure.png'), fullPage: true }); } catch {} }
await browser.close(); if (recorder.failed()) process.exit(1); console.log(JSON.stringify(results, null, 2));
