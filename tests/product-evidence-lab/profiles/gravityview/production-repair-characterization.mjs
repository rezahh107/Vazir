import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import {
  expectVazirmatn,
  familyOf,
  login,
} from '../../core/browser-helpers.mjs';

const baseUrl = process.env.VAZIR_LAB_BASE_URL || 'http://127.0.0.1:8080';
const artifactDir = process.env.VAZIR_LAB_ARTIFACT_DIR;
const user = process.env.VAZIR_LAB_ADMIN_USER || 'vazir_lab_admin';
const password = process.env.VAZIR_LAB_ADMIN_PASSWORD || 'vazir-lab-admin-password';
if (!artifactDir) throw new Error('VAZIR_LAB_ARTIFACT_DIR is required');

const manifestPath = path.join(artifactDir, 'gravityview-fixture.json');
const resultsPath = path.join(artifactDir, 'gravityview-browser-results.json');
const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
const results = JSON.parse(fs.readFileSync(resultsPath, 'utf8'));
const browser = await chromium.launch();
const context = await browser.newContext();
const page = await context.newPage();

async function dismissCoreEditorWelcomeGuide() {
  const overlay = page.locator('.components-modal__screen-overlay').filter({ hasText: /Welcome to the editor/i }).first();
  if (!(await overlay.count()) || !(await overlay.isVisible())) return;
  const closeButton = overlay.locator('button[aria-label="Close"]').first();
  if (await closeButton.count()) await closeButton.click();
  else await page.keyboard.press('Escape');
  await overlay.waitFor({ state: 'hidden', timeout: 5000 });
}

async function ensureGravityViewInspector() {
  await page.goto(manifest.editor_url, { waitUntil: 'domcontentloaded' });
  await page.locator('body.block-editor-page').waitFor({ state: 'visible', timeout: 30000 });
  await page.waitForFunction(
    blockName => Boolean(window.wp?.data?.select('core/block-editor')?.getBlocks?.().some(block => block.name === blockName)),
    manifest.block_name,
    { timeout: 30000 },
  );
  await dismissCoreEditorWelcomeGuide();
  await page.evaluate(blockName => {
    const blocks = window.wp.data.select('core/block-editor').getBlocks();
    const block = blocks.find(candidate => candidate.name === blockName);
    if (!block) throw new Error(`GravityView block ${blockName} is not present in the editor store.`);
    window.wp.data.dispatch('core/block-editor').selectBlock(block.clientId);
  }, manifest.block_name);

  const inspector = page.locator('.gk-gravityview-blocks').first();
  if (!(await inspector.count()) || !(await inspector.isVisible())) {
    const settingsButton = page.getByRole('button', { name: /^Settings$/ }).last();
    if (await settingsButton.count()) await settingsButton.click();
    const blockTab = page.getByRole('tab', { name: /^Block$/ }).last();
    if (await blockTab.count() && 'true' !== await blockTab.getAttribute('aria-selected')) await blockTab.click();
  }
  await inspector.waitFor({ state: 'visible', timeout: 30000 });
  return inspector;
}

async function expandPanelIfPresent(name) {
  let button = page.getByRole('button', { name, exact: true }).first();
  if (!(await button.count())) {
    button = page.locator('.gk-gravityview-blocks .components-panel__body-title button').filter({ hasText: name }).first();
  }
  if (!(await button.count())) return false;
  try {
    await button.waitFor({ state: 'visible', timeout: 3000 });
  } catch {
    return false;
  }
  if ('false' === await button.getAttribute('aria-expanded')) await button.click();
  return true;
}

function isVazirmatn(family) {
  return /Vazirmatn/i.test(family || '');
}

async function handleFamily(handle) {
  return handle.evaluate(el => getComputedStyle(el).fontFamily);
}

try {
  assert.equal(results.status, 'PASS', 'Qualification phase must have executed successfully before repair verification.');
  assert.equal(results.dispositions?.frontend_modern_view, 'ALREADY_VAZIRMATN', 'Modern Vantage frontend must remain already-correct.');
  assert.equal(results.dispositions?.gutenberg_react_select_portaled_menu, 'NOT_PROVEN', 'Detached React Select portal must remain honestly NOT_PROVEN.');
  assert.equal(results.dispositions?.gravityview_oembed_admin_placeholder, 'FAIL', 'oEmbed must remain the qualified unrepaired failure in this batch.');

  await login(page, baseUrl, user, password);
  const inspector = await ensureGravityViewInspector();

  const selectRoot = inspector.locator('.view-selector').first();
  const control = selectRoot.locator('[class$="-control"]').first();
  const input = selectRoot.locator('input[role="combobox"]').first();
  const value = selectRoot.locator('[class$="-singleValue"], [class$="-placeholder"]').filter({ hasText: /\S/ }).first();
  await control.waitFor({ state: 'visible', timeout: 10000 });
  await input.waitFor({ state: 'visible', timeout: 10000 });
  await value.waitFor({ state: 'visible', timeout: 10000 });

  const controlFamily = await expectVazirmatn(control, 'repaired GravityView React Select control');
  const valueFamily = await expectVazirmatn(value, 'repaired GravityView React Select value');
  const inputFamily = await expectVazirmatn(input, 'pre-existing GravityView React Select input');

  const comboboxSelector = '.gk-gravityview-blocks .view-selector input[role="combobox"]';
  const liveInput = page.locator(comboboxSelector).first();
  const ariaExpandedBefore = await liveInput.getAttribute('aria-expanded');
  await liveInput.focus();
  await page.keyboard.press('ArrowDown');
  await page.waitForFunction(
    selector => document.querySelector(selector)?.getAttribute('aria-expanded') === 'true',
    comboboxSelector,
    { timeout: 5000 },
  );
  const ariaExpandedOpen = await page.locator(comboboxSelector).first().getAttribute('aria-expanded');
  await page.keyboard.press('Escape');
  await page.waitForFunction(
    selector => document.querySelector(selector)?.getAttribute('aria-expanded') !== 'true',
    comboboxSelector,
    { timeout: 5000 },
  );
  const ariaExpandedClosed = await page.locator(comboboxSelector).first().getAttribute('aria-expanded');

  const exclusionClass = String(manifest.editor_exclusion_selector || '.vazir-gv-evidence-excluded').replace(/^\./, '');
  const valueHandle = await value.elementHandle();
  assert.ok(valueHandle, 'React Select value node must remain available for exclusion mutation evidence.');
  await valueHandle.evaluate((el, className) => el.classList.add(className), exclusionClass);
  const excludedControlFamily = await familyOf(control);
  const excludedValueFamily = await handleFamily(valueHandle);
  assert.ok(!isVazirmatn(excludedControlFamily), `Excluded React Select control must not receive the repair; got ${excludedControlFamily}`);
  assert.ok(!isVazirmatn(excludedValueFamily), `Excluded React Select value must not inherit the repair; got ${excludedValueFamily}`);
  const excludedInputFamily = await expectVazirmatn(input, 'non-excluded sibling React Select input while value subtree is excluded');
  await valueHandle.evaluate((el, className) => el.classList.remove(className), exclusionClass);
  await expectVazirmatn(control, 'React Select control after exclusion fixture removal');
  const restoredValueFamily = await handleFamily(valueHandle);
  assert.ok(isVazirmatn(restoredValueFamily), `React Select value must recover Vazirmatn after exclusion removal; got ${restoredValueFamily}`);

  results.scenarios.gutenberg_react_select_production_repair = {
    status: 'PASS',
    control_font_family: controlFamily,
    value_font_family: valueFamily,
    input_font_family: inputFamily,
    functional_interaction: {
      aria_expanded_before: ariaExpandedBefore,
      aria_expanded_open: ariaExpandedOpen,
      aria_expanded_closed: ariaExpandedClosed,
    },
    exclusion_probe: {
      selector: manifest.editor_exclusion_selector,
      excluded_control_font_family: excludedControlFamily,
      excluded_value_font_family: excludedValueFamily,
      non_excluded_input_font_family: excludedInputFamily,
      restored_value_font_family: restoredValueFamily,
    },
    admitted_selector: '.gk-gravityview-blocks .view-selector [class$="-control"]',
    important_required: false,
  };
  results.dispositions.gutenberg_react_select_control = 'REPAIRED_VAZIRMATN';
  results.dispositions.gutenberg_react_select_value = 'REPAIRED_VAZIRMATN';
  results.repair_seams.gutenberg_react_select_control = 'PRODUCTION: enqueue_block_editor_assets -> GravityView registered gk-gravityview-blocks-view-editor-style; semantic [class$="-control"] selector under .gk-gravityview-blocks .view-selector; no generated Emotion hash and no input rule.';

  const panelPresent = await expandPanelIfPresent('Entries Settings');
  assert.equal(panelPresent, true, 'Exact GravityView 3.3.4 must expose Entries Settings for Datepicker repair verification.');
  const dateInput = page.locator('.gk-gravityview-blocks .react-datepicker-wrapper input').first();
  await dateInput.waitFor({ state: 'visible', timeout: 5000 });
  const dateInputFamily = await expectVazirmatn(dateInput, 'pre-existing GravityView Datepicker input');
  const valueBefore = await dateInput.inputValue();
  await dateInput.click();
  const picker = page.locator('.gk-gravityview-blocks .react-datepicker:visible').first();
  await picker.waitFor({ state: 'visible', timeout: 5000 });
  const month = picker.locator('.react-datepicker__current-month').first();
  const day = picker.locator('.react-datepicker__day:not(.react-datepicker__day--outside-month):not(.react-datepicker__day--disabled)').filter({ hasText: /\S/ }).first();
  const pickerFamily = await expectVazirmatn(picker, 'repaired GravityView Datepicker root');
  const monthFamily = await expectVazirmatn(month, 'repaired GravityView Datepicker current month');
  const dayFamily = await expectVazirmatn(day, 'repaired GravityView Datepicker day');

  await day.evaluate((el, className) => el.classList.add(className), exclusionClass);
  const excludedPickerFamily = await familyOf(picker);
  const excludedDayFamily = await familyOf(day);
  assert.ok(!isVazirmatn(excludedPickerFamily), `Excluded Datepicker root must not receive the repair; got ${excludedPickerFamily}`);
  assert.ok(!isVazirmatn(excludedDayFamily), `Excluded Datepicker day must not inherit the repair; got ${excludedDayFamily}`);
  await day.evaluate((el, className) => el.classList.remove(className), exclusionClass);
  await expectVazirmatn(picker, 'Datepicker root after exclusion fixture removal');
  await expectVazirmatn(day, 'Datepicker day after exclusion fixture removal');

  const selectedDayText = (await day.innerText()).trim();
  await day.click();
  await picker.waitFor({ state: 'hidden', timeout: 5000 });
  const valueAfter = await dateInput.inputValue();
  assert.notEqual(valueAfter, valueBefore, 'Selecting a real Datepicker day must update the associated input value.');
  assert.ok(valueAfter.length > 0, 'Selecting a real Datepicker day must leave a non-empty input value.');
  await dateInput.click();
  await picker.waitFor({ state: 'visible', timeout: 5000 });
  await page.keyboard.press('Escape');
  await picker.waitFor({ state: 'hidden', timeout: 5000 });

  results.scenarios.gutenberg_datepicker_production_repair = {
    status: 'PASS',
    picker_font_family: pickerFamily,
    month_font_family: monthFamily,
    day_font_family: dayFamily,
    input_font_family: dateInputFamily,
    exclusion_probe: {
      selector: manifest.editor_exclusion_selector,
      excluded_picker_font_family: excludedPickerFamily,
      excluded_day_font_family: excludedDayFamily,
    },
    functional_interaction: {
      value_before: valueBefore,
      selected_day_text: selectedDayText,
      value_after: valueAfter,
      select_closes_picker: true,
      reopen_and_escape_closes_picker: true,
    },
    admitted_selector: '.gk-gravityview-blocks .react-datepicker',
    important_required: false,
  };
  results.dispositions.gutenberg_datepicker = 'REPAIRED_VAZIRMATN';
  results.dispositions.gutenberg_datepicker_month = 'REPAIRED_VAZIRMATN';
  results.dispositions.gutenberg_datepicker_day = 'REPAIRED_VAZIRMATN';
  results.dispositions.gravityview_oembed_admin_placeholder = 'FAIL_UNREPAIRED';
  results.repair_seams.gutenberg_datepicker = 'PRODUCTION: enqueue_block_editor_assets -> GravityView registered gk-gravityview-blocks-view-editor-style; one .gk-gravityview-blocks .react-datepicker root rule restores inherited month/day typography.';
  results.repair_seams.gravityview_oembed_admin_placeholder = `${results.repair_seams.gravityview_oembed_admin_placeholder} UNCHANGED_IN_THIS_BATCH.`;
  results.production_repair = {
    status: 'PASS',
    surfaces: ['gutenberg_react_select_control', 'gutenberg_datepicker'],
    frontend_repair_added: false,
    detached_portal_repair_added: false,
    oembed_repair_added: false,
    important_required: false,
  };

  fs.writeFileSync(resultsPath, `${JSON.stringify(results, null, 2)}\n`);
  console.log(JSON.stringify(results, null, 2));
} catch (error) {
  results.status = 'FAIL';
  results.production_repair = { status: 'FAIL', error: String(error?.stack || error) };
  fs.writeFileSync(resultsPath, `${JSON.stringify(results, null, 2)}\n`);
  try { await page.screenshot({ path: path.join(artifactDir, 'gravityview-production-repair-failure.png'), fullPage: true }); } catch {}
  throw error;
} finally {
  await browser.close();
}
