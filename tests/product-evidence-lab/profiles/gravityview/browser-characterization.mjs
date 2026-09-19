import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { expectVazir, expectNotVazir, pseudoFamily, login, makeRecorder } from '../../core/browser-helpers.mjs';
const baseUrl = process.env.VAZIR_LAB_BASE_URL || 'http://127.0.0.1:8080';
const artifactDir = process.env.VAZIR_LAB_ARTIFACT_DIR;
const user = process.env.VAZIR_LAB_ADMIN_USER || 'vazir_lab_admin';
const password = process.env.VAZIR_LAB_ADMIN_PASSWORD || 'vazir-lab-admin-password';
if (!artifactDir) throw new Error('VAZIR_LAB_ARTIFACT_DIR is required');
const manifest = JSON.parse(fs.readFileSync(path.join(artifactDir, 'gravityview-fixture.json'), 'utf8'));
const results = { status: 'PASS', profile: 'gravityview', scenarios: {}, claim_ceiling: { ajax_dynamic_output: 'NOT_PROVEN unless the installed View uses an observable AJAX path' } };
const recorder = makeRecorder(results);
const browser = await chromium.launch(); const context = await browser.newContext(); const page = await context.newPage();
await recorder.record('frontend_view_table_search_pagination', async () => {
  await page.goto(manifest.frontend_url, { waitUntil: 'networkidle' });
  const container = page.locator('.gv-container').first(); await container.waitFor({ state: 'visible', timeout: 30000 });
  await expectVazir(container, 'GravityView frontend container'); await expectNotVazir(page.locator('#vf-view-excluded'), 'GravityView profile exclusion fixture', /monospace/i);
  const search = page.locator('.gv-widget-search input[type="search"][name="gv_search"]').first(); await expectVazir(search, 'GravityView search input');
  const submit = page.locator('.gv-widget-search .gv-search-button').first(); await expectVazir(submit, 'GravityView search button');
  await search.fill('آلفا'); await Promise.all([page.waitForLoadState('domcontentloaded'), submit.click()]);
  await page.locator('.gv-container').first().waitFor({ state: 'visible' }); await expectVazir(page.locator('.gv-container').first(), 'GravityView filtered result state');
  assert.match(await page.locator('.gv-container').first().innerText(), /آلفا/, 'Filtered View should contain the matching synthetic entry');
  const pagination = page.locator('.gv-widget-page-links').first();
  if (await pagination.count()) await expectVazir(pagination, 'GravityView pagination'); else results.claim_ceiling.pagination = 'NOT_PROVEN: configured result state did not render pagination';
});
await login(page, baseUrl, user, password);
await recorder.record('admin_view_configuration_and_icon_family', async () => {
  await page.goto(manifest.admin_view_url, { waitUntil: 'domcontentloaded' }); await page.locator('body.post-type-gravityview').waitFor({ state: 'visible', timeout: 30000 });
  await expectVazir(page.locator('body.post-type-gravityview'), 'GravityView admin editor');
  const icon = page.locator('.gv_tooltip, [data-gv-icon], .gv-icon__before, [class^="gv-icon-"]').first();
  if (await icon.count()) { const family = await pseudoFamily(icon); assert.match(family, /gravityview/i, `GravityView icon must retain its icon font; got ${family}`); return { icon_family: family }; }
  results.claim_ceiling.icon_family = 'NOT_PROVEN: no representative GravityView icon node rendered on the configured View editor'; return {};
});
fs.writeFileSync(path.join(artifactDir, 'gravityview-browser-results.json'), `${JSON.stringify(results, null, 2)}\n`);
if (recorder.failed()) { try { await page.screenshot({ path: path.join(artifactDir, 'gravityview-browser-failure.png'), fullPage: true }); } catch {} }
await browser.close(); if (recorder.failed()) process.exit(1); console.log(JSON.stringify(results, null, 2));
