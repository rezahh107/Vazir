import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import {
  expectVazirmatn,
  expectNotVazirmatn,
  familyOf,
  login,
  makeRecorder,
  observeVazirmatnFontRequests,
} from '../../core/browser-helpers.mjs';

const baseUrl = process.env.VAZIR_LAB_BASE_URL || 'http://127.0.0.1:8080';
const artifactDir = process.env.VAZIR_LAB_ARTIFACT_DIR;
const user = process.env.VAZIR_LAB_ADMIN_USER || 'vazir_lab_admin';
const password = process.env.VAZIR_LAB_ADMIN_PASSWORD || 'vazir-lab-admin-password';
if (!artifactDir) throw new Error('VAZIR_LAB_ARTIFACT_DIR is required');

const gf = JSON.parse(fs.readFileSync(path.join(artifactDir, 'fixture-manifest.json'), 'utf8'));
const flow = JSON.parse(fs.readFileSync(path.join(artifactDir, 'gravityflow-fixture.json'), 'utf8'));
const view = JSON.parse(fs.readFileSync(path.join(artifactDir, 'gravityview-fixture.json'), 'utf8'));
const results = {
  status: 'PASS',
  profile: 'gravity-stack',
  scenarios: {},
  claim_ceiling: 'Representative coexistence only; not exhaustive product compatibility.',
};
const recorder = makeRecorder(results);
const browser = await chromium.launch();
const context = await browser.newContext();
const page = await context.newPage();

await recorder.record('representative_gravityforms_surface', async () => {
  await page.goto(gf.orbital_url, { waitUntil: 'networkidle' });
  await expectVazirmatn(page.locator(`#gform_wrapper_${gf.orbital_form_id}`), 'combined-stack Gravity Forms wrapper');
});

await recorder.record('representative_gravityview_surface', async () => {
  await page.goto(view.frontend_url, { waitUntil: 'networkidle' });
  await expectVazirmatn(page.locator('.gv-container').first(), 'combined-stack GravityView container');
  await expectNotVazirmatn(page.locator('#vf-view-excluded'), 'combined-stack GravityView exclusion', /monospace/i);
});

await login(page, baseUrl, user, password);

await recorder.record('representative_gravityflow_surface', async () => {
  await page.goto(flow.admin_inbox_url, { waitUntil: 'domcontentloaded' });
  await page.locator('.gflow-inbox').first().waitFor({ state: 'visible', timeout: 30000 });
  await expectVazirmatn(page.locator('.gflow-inbox').first(), 'combined-stack Gravity Flow inbox');
});

await recorder.record('combined_no_conflict_and_icon_family', async () => {
  await page.goto(gf.form_editor_url, { waitUntil: 'domcontentloaded' });
  await page.locator('.gform_editor').waitFor({ state: 'visible', timeout: 30000 });

  assert.equal(
    await page.locator('#vazir-font-gravity-forms-inline-css').count(),
    1,
    'combined-stack GF style handle must survive Gravity Forms No Conflict Mode exactly once',
  );
  assert.equal(
    await page.locator('#vazir-font-admin-runtime-inline-css').count(),
    1,
    'combined-stack Vazir admin style handle must survive Gravity Forms No Conflict Mode exactly once',
  );

  const gfIcon = page.locator('.gform-icon').first();
  await gfIcon.waitFor({ state: 'attached', timeout: 30000 });
  const gfIconFamily = await familyOf(gfIcon);
  assert.match(
    gfIconFamily,
    /gform-icons-admin/i,
    `combined-stack Gravity Forms admin icon must retain gform-icons-admin; got ${gfIconFamily}`,
  );

  return {
    form_editor_rendered: true,
    gravity_forms_style_handle_count: 1,
    admin_style_handle_count: 1,
    gravity_forms_admin_icon_family: gfIconFamily,
  };
});

await recorder.record('font_delivery_has_no_duplicate_url_requests', async () => {
  return observeVazirmatnFontRequests(browser, {
    url: gf.orbital_url,
    label: 'Combined stack representative render',
    waitForSurface: async requestPage => {
      await requestPage.locator(`#gform_wrapper_${gf.orbital_form_id}`).waitFor({ state: 'visible', timeout: 30000 });
    },
  });
});

fs.writeFileSync(path.join(artifactDir, 'gravity-stack-browser-results.json'), `${JSON.stringify(results, null, 2)}\n`);
if (recorder.failed()) {
  try {
    await page.screenshot({ path: path.join(artifactDir, 'gravity-stack-browser-failure.png'), fullPage: true });
  } catch {}
}
await context.close();
await browser.close();
if (recorder.failed()) process.exit(1);
console.log(JSON.stringify(results, null, 2));
