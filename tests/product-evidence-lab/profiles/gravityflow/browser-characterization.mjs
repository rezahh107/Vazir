import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import {
  expectVazirmatn,
  expectNotVazirmatn,
  familyOf,
  pseudoFamily,
  login,
  makeRecorder,
} from '../../core/browser-helpers.mjs';

const baseUrl = process.env.VAZIR_LAB_BASE_URL || 'http://127.0.0.1:8080';
const artifactDir = process.env.VAZIR_LAB_ARTIFACT_DIR;
const user = process.env.VAZIR_LAB_ADMIN_USER || 'vazir_lab_admin';
const password = process.env.VAZIR_LAB_ADMIN_PASSWORD || 'vazir-lab-admin-password';
if (!artifactDir) throw new Error('VAZIR_LAB_ARTIFACT_DIR is required');

const manifest = JSON.parse(fs.readFileSync(path.join(artifactDir, 'gravityflow-fixture.json'), 'utf8'));
const results = { status: 'PASS', profile: 'gravityflow', scenarios: {}, claim_ceiling: {} };
const recorder = makeRecorder(results);
const browser = await chromium.launch();
const context = await browser.newContext();
const page = await context.newPage();

function notProven(name, reason) {
  results.scenarios[name] = { status: 'NOT_PROVEN', reason };
  results.claim_ceiling[name] = `NOT_PROVEN: ${reason}`;
}

async function waitForInbox(url) {
  await page.goto(url, { waitUntil: 'domcontentloaded' });
  const inbox = page.locator('.gflow-inbox').first();
  await inbox.waitFor({ state: 'visible', timeout: 30000 });
  const theme = page.locator('.gflow-grid .ag-theme-alpine').first();
  await theme.waitFor({ state: 'visible', timeout: 30000 });
  await page.locator('.ag-root-wrapper').first().waitFor({ state: 'visible', timeout: 30000 });
  await page.locator('.ag-center-cols-container .ag-row').first().waitFor({ state: 'visible', timeout: 30000 });
  return { inbox, theme };
}

async function firstTextCell() {
  const cell = page.locator('.ag-center-cols-container .ag-row .ag-cell').filter({ hasText: /\S/ }).first();
  await cell.waitFor({ state: 'visible', timeout: 30000 });
  return cell;
}

async function characterizeRequiredGrid(prefix) {
  await recorder.record(`${prefix}_ag_theme_root`, async () => {
    const family = await expectVazirmatn(page.locator('.gflow-grid .ag-theme-alpine').first(), `${prefix} AG Grid theme root`);
    return { computed_font_family: family };
  });

  await recorder.record(`${prefix}_ag_header_text`, async () => {
    const header = page.locator('.ag-header-cell-text').filter({ hasText: /\S/ }).first();
    const family = await expectVazirmatn(header, `${prefix} AG Grid header text`);
    return { computed_font_family: family, text: (await header.innerText()).trim() };
  });

  await recorder.record(`${prefix}_ag_row_cell_text`, async () => {
    const cell = await firstTextCell();
    const family = await expectVazirmatn(cell, `${prefix} AG Grid row/cell text`);
    return { computed_font_family: family, text: (await cell.innerText()).trim() };
  });
}

await login(page, baseUrl, user, password);

await recorder.record('admin_current_inbox_reachable', async () => {
  const { inbox } = await waitForInbox(manifest.admin_inbox_url);
  const family = await expectVazirmatn(inbox, 'Gravity Flow admin inbox wrapper');
  return { wrapper_font_family: family };
});
await characterizeRequiredGrid('admin');

await recorder.record('admin_search_control', async () => {
  const search = page.locator('input.gflow-inbox__search, .gravityflow-entry-table__header-search-input').first();
  if (!(await search.count())) {
    notProven('admin_search_control', 'current rendered Inbox did not expose a search input');
    return { status: 'NOT_PROVEN' };
  }
  const family = await expectVazirmatn(search, 'Gravity Flow Inbox search control');
  return { computed_font_family: family };
});

await recorder.record('admin_pagination_text_and_dynamic_rerender', async () => {
  const panel = page.locator('.ag-paging-panel').first();
  await panel.waitFor({ state: 'visible', timeout: 30000 });
  const summary = panel.locator('.ag-paging-row-summary-panel, .ag-paging-page-summary-panel').filter({ hasText: /\S/ }).first();
  const family = await expectVazirmatn(summary, 'AG Grid pagination text');
  const before = (await (await firstTextCell()).innerText()).trim();
  const next = panel.locator('.ag-paging-button[ref="btNext"]').first();
  await next.waitFor({ state: 'visible', timeout: 30000 });
  assert.equal(await next.isDisabled(), false, 'Fixture must make the next pagination control available.');
  await next.click();
  await page.waitForTimeout(150);
  const afterCell = await firstTextCell();
  const after = (await afterCell.innerText()).trim();
  assert.notEqual(after, before, 'Pagination should rerender a different representative row.');
  const rerenderFamily = await expectVazirmatn(afterCell, 'AG Grid rerendered row/cell text after pagination');
  const previous = panel.locator('.ag-paging-button[ref="btPrevious"]').first();
  await previous.click();
  return { computed_font_family: family, rerendered_font_family: rerenderFamily, before, after };
});

await recorder.record('admin_aggrid_icon_family', async () => {
  const icon = page.locator('.ag-paging-button[ref="btNext"] .ag-icon').first();
  await icon.waitFor({ state: 'visible', timeout: 30000 });
  const family = await familyOf(icon);
  assert.match(family, /agGridAlpine/i, `AG Grid icon must retain agGridAlpine; got ${family}`);
  assert.doesNotMatch(family, /Vazirmatn/i, `AG Grid icon must not resolve to Vazirmatn; got ${family}`);
  return { computed_font_family: family };
});

await recorder.record('admin_gravityflow_icon_family', async () => {
  const icon = page.locator('.gflow-icon--search').first();
  if (!(await icon.count())) {
    notProven('admin_gravityflow_icon_family', 'current rendered Inbox did not expose .gflow-icon--search');
    return { status: 'NOT_PROVEN' };
  }
  const family = await pseudoFamily(icon);
  assert.match(family, /gflow-icons-common/i, `Gravity Flow search icon must retain gflow-icons-common; got ${family}`);
  assert.doesNotMatch(family, /Vazirmatn/i, `Gravity Flow search icon must not resolve to Vazirmatn; got ${family}`);
  return { computed_font_family: family };
});

await recorder.record('admin_wordpress_icon_family', async () => {
  const icon = page.locator('#adminmenu .dashicons-before').first();
  if (!(await icon.count())) {
    notProven('admin_wordpress_icon_family', 'wp-admin menu did not expose a dashicons-before surface');
    return { status: 'NOT_PROVEN' };
  }
  const family = await pseudoFamily(icon);
  assert.match(family, /dashicons/i, `WordPress icon must retain dashicons; got ${family}`);
  assert.doesNotMatch(family, /Vazirmatn/i, `WordPress icon must not resolve to Vazirmatn; got ${family}`);
  return { computed_font_family: family };
});

await recorder.record('admin_date_filter_input', async () => {
  const header = page.locator('.ag-header-cell[col-id="date_created"]').first();
  if (!(await header.count())) {
    notProven('admin_date_filter_input', 'date_created header is not rendered in the current Inbox configuration');
    return { status: 'NOT_PROVEN' };
  }
  await header.hover();
  const menuButton = header.locator('.ag-header-cell-menu-button').first();
  await menuButton.click({ force: true });
  const filterTab = page.locator('.ag-menu .ag-tab[ref="eFilterTab"]').first();
  if (await filterTab.count()) await filterTab.click({ force: true });
  const input = page.locator('.ag-menu .ag-input-wrapper.custom-date-filter input, .ag-filter .ag-input-wrapper.custom-date-filter input').first();
  try {
    await input.waitFor({ state: 'visible', timeout: 5000 });
  } catch {
    notProven('admin_date_filter_input', 'AG Grid date filter UI was not deterministically reachable from the rendered date_created header');
    await page.keyboard.press('Escape').catch(() => {});
    return { status: 'NOT_PROVEN' };
  }
  const family = await expectVazirmatn(input, 'Gravity Flow AG Grid date-filter input');
  return { computed_font_family: family };
});

await recorder.record('admin_date_picker_text', async () => {
  const input = page.locator('.ag-menu .ag-input-wrapper.custom-date-filter input, .ag-filter .ag-input-wrapper.custom-date-filter input').first();
  if (!(await input.count()) || !(await input.isVisible())) {
    notProven('admin_date_picker_text', 'date-filter input was not open, so the bound Flatpickr calendar could not be exercised');
    return { status: 'NOT_PROVEN' };
  }
  const toggle = page.locator('.ag-menu .ag-grid__date-toggle, .ag-filter .ag-grid__date-toggle').first();
  if (!(await toggle.count())) {
    notProven('admin_date_picker_text', 'date-filter UI did not expose the Gravity Flow date-picker toggle');
    return { status: 'NOT_PROVEN' };
  }
  await toggle.click({ force: true });
  const calendar = page.locator('.flatpickr-calendar.ag-custom-component-popup.open, .flatpickr-calendar.ag-custom-component-popup').filter({ visible: true }).first();
  try {
    await calendar.waitFor({ state: 'visible', timeout: 5000 });
  } catch {
    notProven('admin_date_picker_text', 'Gravity Flow Flatpickr calendar did not become visible after the native date-filter toggle');
    return { status: 'NOT_PROVEN' };
  }
  const family = await expectVazirmatn(calendar, 'Gravity Flow date-filter Flatpickr calendar');
  await page.keyboard.press('Escape').catch(() => {});
  return { computed_font_family: family };
});

await recorder.record('frontend_inbox_reachable_and_exclusion', async () => {
  const { inbox } = await waitForInbox(manifest.frontend_inbox_url);
  const family = await expectVazirmatn(inbox, 'Gravity Flow frontend inbox wrapper');
  const excluded = await expectNotVazirmatn(page.locator('#vf-flow-excluded'), 'Gravity Flow profile exclusion fixture', /monospace/i);
  return { wrapper_font_family: family, excluded_font_family: excluded };
});
await characterizeRequiredGrid('frontend');

await recorder.record('gravity_forms_prerequisite_still_operational', async () => {
  await page.goto(manifest.gravity_forms_url, { waitUntil: 'networkidle' });
  const wrapper = page.locator(`#gform_wrapper_${manifest.form_id}`);
  await wrapper.waitFor({ state: 'visible', timeout: 30000 });
  const wrapperFamily = await expectVazirmatn(wrapper, 'Gravity Forms prerequisite with Gravity Flow active');
  const controlFamily = await expectVazirmatn(page.locator(`#input_${manifest.form_id}_1`), 'Gravity Forms prerequisite control with Gravity Flow active');
  return { wrapper_font_family: wrapperFamily, control_font_family: controlFamily };
});

fs.writeFileSync(path.join(artifactDir, 'gravityflow-browser-results.json'), `${JSON.stringify(results, null, 2)}\n`);
if (recorder.failed()) {
  try { await page.screenshot({ path: path.join(artifactDir, 'gravityflow-browser-failure.png'), fullPage: true }); } catch {}
}
await browser.close();
if (recorder.failed()) process.exit(1);
console.log(JSON.stringify(results, null, 2));
