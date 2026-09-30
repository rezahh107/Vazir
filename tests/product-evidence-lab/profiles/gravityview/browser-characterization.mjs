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

const manifest = JSON.parse(fs.readFileSync(path.join(artifactDir, 'gravityview-fixture.json'), 'utf8'));
const runtime = JSON.parse(fs.readFileSync(path.join(artifactDir, 'gravityview-runtime-results.json'), 'utf8'));
const results = {
  status: 'PASS',
  profile: 'gravityview',
  scenarios: {},
  dispositions: {},
  repair_seams: {},
  claim_ceiling: {
    ajax_dynamic_output: 'NOT_PROVEN unless the installed View uses an observable AJAX path',
  },
};
const recorder = makeRecorder(results);
const browser = await chromium.launch();
const context = await browser.newContext();
const page = await context.newPage();

const isVazirmatn = family => /Vazirmatn/i.test(family || '');
const typographyDisposition = family => (isVazirmatn(family) ? 'ALREADY_VAZIRMATN' : 'FAIL');

function notProven(name, reason, status = 'NOT_PROVEN') {
  results.scenarios[name] = { status, reason };
  results.dispositions[name] = status;
  results.claim_ceiling[name] = `${status}: ${reason}`;
}

async function textAndFamily(locator) {
  await locator.waitFor({ state: 'visible', timeout: 30000 });
  return {
    text: (await locator.innerText()).trim(),
    computed_font_family: await familyOf(locator),
  };
}

async function recordMeasuredTypography(name, locator, extra = {}) {
  const measurement = await textAndFamily(locator);
  const disposition = typographyDisposition(measurement.computed_font_family);
  results.dispositions[name] = disposition;
  return { ...measurement, disposition, ...extra };
}

async function ensureGravityViewInspector() {
  await page.goto(manifest.editor_url, { waitUntil: 'domcontentloaded' });
  await page.locator('body.block-editor-page').waitFor({ state: 'visible', timeout: 30000 });
  await page.waitForFunction(
    blockName => {
      const select = window.wp?.data?.select('core/block-editor');
      return Boolean(select?.getBlocks?.().some(block => block.name === blockName));
    },
    manifest.block_name,
    { timeout: 30000 },
  );
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
    if (await blockTab.count()) await blockTab.click();
  }
  await inspector.waitFor({ state: 'visible', timeout: 30000 });
  return inspector;
}

async function expandPanelIfPresent(name) {
  const button = page.getByRole('button', { name, exact: true }).first();
  if (!(await button.count())) return false;
  try {
    await button.waitFor({ state: 'visible', timeout: 3000 });
  } catch {
    return false;
  }
  const expanded = await button.getAttribute('aria-expanded');
  if ('false' === expanded) await button.click();
  return true;
}

await recorder.record('frontend_modern_view_inner_typography', async () => {
  await page.goto(manifest.frontend_url, { waitUntil: 'networkidle' });
  const container = page.locator('.gv-container').first();
  await container.waitFor({ state: 'visible', timeout: 30000 });
  const themed = page.locator('.gv-themed.gv-theme-vantage').first();
  await themed.waitFor({ state: 'visible', timeout: 30000 });

  const effectiveToken = (await themed.evaluate(el => getComputedStyle(el).getPropertyValue('--gv-font-family'))).trim();
  const rootFamily = await expectVazirmatn(themed, 'GravityView Vantage themed root');
  const header = page.locator('.gv-table-view-content thead th, .gv-table-view thead th').filter({ hasText: /\S/ }).first();
  const cell = page.locator('.gv-table-view-content tbody td, .gv-table-view tbody td').filter({ hasText: /\S/ }).first();
  const headerFamily = await expectVazirmatn(header, 'GravityView Vantage table header text');
  const cellFamily = await expectVazirmatn(cell, 'GravityView Vantage entry cell text');

  const searchForm = page.locator('form.gv-widget-search').first();
  await searchForm.waitFor({ state: 'visible', timeout: 30000 });
  const searchLabel = searchForm.locator('label').filter({ hasText: /\S/ }).first();
  let labelFamily = null;
  if (await searchLabel.count()) labelFamily = await expectVazirmatn(searchLabel, 'GravityView search label');
  const search = searchForm.locator('input[type="search"], input[type="text"]').first();
  const searchFamily = await expectVazirmatn(search, 'GravityView search input');
  const submit = searchForm.locator('.gv-search-button').first();
  const submitFamily = await expectVazirmatn(submit, 'GravityView search button');

  const pagination = page.locator('.gv-widget-page-links').first();
  let paginationFamily = null;
  if (await pagination.count() && await pagination.isVisible()) {
    paginationFamily = await expectVazirmatn(pagination, 'GravityView pagination');
  } else {
    results.claim_ceiling.pagination = 'NOT_PROVEN: configured initial result state did not render pagination';
  }

  const excludedFamily = await expectNotVazirmatn(page.locator('#vf-view-excluded'), 'GravityView profile exclusion fixture', /monospace/i);
  results.dispositions.frontend_modern_view = 'ALREADY_VAZIRMATN';
  results.repair_seams.frontend_modern_view = 'NO_REPAIR_NEEDED: Vantage uses --gv-font-family: inherit and the real inner surfaces resolved to Vazirmatn.';

  return {
    view_theme: manifest.expected_view_theme,
    gv_font_family_token: effectiveToken,
    root_font_family: rootFamily,
    table_header_font_family: headerFamily,
    entry_cell_font_family: cellFamily,
    search_label_font_family: labelFamily,
    search_input_font_family: searchFamily,
    search_button_font_family: submitFamily,
    pagination_font_family: paginationFamily,
    excluded_font_family: excludedFamily,
  };
});

await recorder.record('frontend_real_search_interaction', async () => {
  const searchForm = page.locator('form.gv-widget-search').first();
  const search = searchForm.locator('input[type="search"], input[type="text"]').first();
  const submit = searchForm.locator('.gv-search-button').first();
  await search.fill('آلفا');
  await Promise.all([page.waitForLoadState('domcontentloaded'), submit.click()]);
  const filtered = page.locator('.gv-container').first();
  await filtered.waitFor({ state: 'visible', timeout: 30000 });
  const family = await expectVazirmatn(filtered, 'GravityView filtered result state');
  assert.match(await filtered.innerText(), /آلفا/, 'Filtered View should contain the matching synthetic entry');
  return { computed_font_family: family, native_search_result: 'PASS' };
});

await login(page, baseUrl, user, password);

await recorder.record('admin_view_configuration_and_icon_family', async () => {
  await page.goto(manifest.admin_view_url, { waitUntil: 'domcontentloaded' });
  await page.locator('body.post-type-gravityview').waitFor({ state: 'visible', timeout: 30000 });
  const bodyFamily = await expectVazirmatn(page.locator('body.post-type-gravityview'), 'GravityView admin editor');
  const icon = page.locator('.gv_tooltip, [data-gv-icon], .gv-icon__before, [class^="gv-icon-"]').first();
  if (await icon.count()) {
    const family = await pseudoFamily(icon);
    assert.match(family, /gravityview/i, `GravityView icon must retain its icon font; got ${family}`);
    assert.doesNotMatch(family, /Vazirmatn/i, `GravityView icon must not resolve to Vazirmatn; got ${family}`);
    return { body_font_family: bodyFamily, icon_family: family };
  }
  results.claim_ceiling.icon_family = 'NOT_PROVEN: no representative GravityView icon node rendered on the configured View editor';
  return { body_font_family: bodyFamily, icon_family: 'NOT_PROVEN' };
});

await recorder.record('gutenberg_fixture_and_assets', async () => {
  await ensureGravityViewInspector();
  const topDocumentUrl = page.url();
  const canvas = page.locator('iframe[name="editor-canvas"], iframe[title="Editor canvas"]').first();
  const hasCanvasIframe = Boolean(await canvas.count());
  let canvasInfo = { present: false };
  if (hasCanvasIframe) {
    canvasInfo = await canvas.evaluate(el => ({
      present: true,
      name: el.getAttribute('name'),
      title: el.getAttribute('title'),
      src: el.getAttribute('src'),
    }));
  }

  const assets = await page.evaluate(() => ({
    scripts: [...document.scripts].map(node => node.src).filter(src => /gravityview.*PageBuilder\/Gutenberg\/build\/view\.js/i.test(src)),
    styles: [...document.querySelectorAll('link[rel="stylesheet"]')].map(node => node.href).filter(href => /gravityview.*PageBuilder\/Gutenberg\/build\/(?:style-)?view(?:-rtl)?\.css/i.test(href)),
  }));
  assert.ok(assets.scripts.length > 0, 'Exact GravityView View block editor script must load in the real editor.');
  assert.ok(assets.styles.length > 0, 'Exact GravityView View block editor style must load in the real editor.');
  assert.ok((runtime.block_assets?.editor_script_handles || []).length > 0, 'Runtime contract must expose the registered editor script handle.');
  assert.ok((runtime.block_assets?.editor_style_handles || []).length > 0, 'Runtime contract must expose the registered editor style handle.');

  return {
    editor_url: topDocumentUrl,
    block_name: manifest.block_name,
    top_level_document: 'wp-admin post editor',
    canvas_iframe: canvasInfo,
    loaded_gravityview_assets: assets,
    registered_block_assets: runtime.block_assets,
  };
});

await recorder.record('gutenberg_react_select_control', async () => {
  const inspector = page.locator('.gk-gravityview-blocks').first();
  const root = inspector.locator('.view-selector').first();
  await root.waitFor({ state: 'visible', timeout: 10000 });
  const control = root.locator('[class$="-control"]').first();
  const input = root.locator('input[role="combobox"]').first();
  await control.waitFor({ state: 'visible', timeout: 10000 });
  await input.waitFor({ state: 'visible', timeout: 10000 });

  const value = root.locator('[class$="-singleValue"], [class$="-placeholder"]').filter({ hasText: /\S/ }).first();
  const valueMeasurement = await recordMeasuredTypography('gutenberg_react_select_value', value);
  const inputFamily = await familyOf(input);
  const controlFamily = await familyOf(control);
  const classEvidence = await control.evaluate(el => ({ control_class: el.className, input_class: el.querySelector('input')?.className || null }));
  const emotionOwnership = await page.locator('style[data-emotion*="gk-select"]').evaluateAll(nodes => nodes.map(node => node.getAttribute('data-emotion')));

  results.dispositions.gutenberg_react_select_control = typographyDisposition(valueMeasurement.computed_font_family);
  results.repair_seams.gutenberg_react_select_control = 'Candidate: stable GravityView .gk-gravityview-blocks .view-selector scope plus the host registered View block editor-style handle; do not target the generated Emotion hash.';

  return {
    ...valueMeasurement,
    input_font_family: inputFamily,
    control_font_family: controlFamily,
    semantic_root: '.gk-gravityview-blocks .view-selector',
    actual_dom_classes: classEvidence,
    emotion_style_ownership: emotionOwnership,
  };
});

await recorder.record('gutenberg_react_select_portaled_menu', async () => {
  const root = page.locator('.gk-gravityview-blocks .view-selector').first();
  const input = root.locator('input[role="combobox"]').first();
  await input.click();
  await input.press('ArrowDown');
  const listbox = page.locator('body > [class$="-menuPortal"] [role="listbox"]').last();
  try {
    await listbox.waitFor({ state: 'visible', timeout: 5000 });
  } catch {
    notProven('gutenberg_react_select_portaled_menu', 'The authentic GravityView react-select menu did not become visible after opening the View selector.');
    return { status: 'NOT_PROVEN' };
  }
  const portal = listbox.locator('xpath=ancestor::*[contains(@class,"-menuPortal")][1]');
  const option = listbox.locator('[role="option"]').filter({ hasText: /\S/ }).first();
  const optionMeasurement = await recordMeasuredTypography('gutenberg_react_select_menu_option', option);
  const portalFamily = await familyOf(portal);
  const portalEvidence = await portal.evaluate(el => ({
    class_name: el.className,
    parent_tag: el.parentElement?.tagName || null,
    parent_is_body: el.parentElement === el.ownerDocument.body,
    owner_document_is_top_level: el.ownerDocument.defaultView === window,
  }));
  const emotionOwnership = await page.locator('style[data-emotion*="gk-select"]').evaluateAll(nodes => nodes.map(node => node.getAttribute('data-emotion')));

  results.dispositions.gutenberg_react_select_portaled_menu = typographyDisposition(optionMeasurement.computed_font_family);
  results.repair_seams.gutenberg_react_select_portaled_menu = 'UNCERTAIN: the menu is detached under document.body. The source-owned Emotion key gk-select is observable, but the existing exclusion boundary cannot associate a detached portal with its source .view-selector by ancestry. Validate a stable gk-select portal selector before production repair; no JS mutation.';
  await page.keyboard.press('Escape').catch(() => {});

  return {
    ...optionMeasurement,
    portal_font_family: portalFamily,
    actual_portal: portalEvidence,
    emotion_style_ownership: emotionOwnership,
    source_control_semantic_root: '.gk-gravityview-blocks .view-selector',
    detached_from_source_control: true,
  };
});

await recorder.record('gutenberg_datepicker', async () => {
  const panelPresent = await expandPanelIfPresent('Entries Settings');
  if (!panelPresent) {
    notProven('gutenberg_datepicker', 'Exact GravityView 3.3.4 did not render an Entries Settings panel on the authentic View block inspector; source-level Datepicker CSS risk remains unpromoted.');
    return { status: 'NOT_PROVEN' };
  }

  const input = page.locator('.gk-gravityview-blocks .react-datepicker-wrapper input').first();
  try {
    await input.waitFor({ state: 'visible', timeout: 5000 });
  } catch {
    notProven('gutenberg_datepicker', 'The authentic GravityView View block did not expose its date input in the expanded Entries Settings panel.');
    return { status: 'NOT_PROVEN' };
  }
  const inputFamily = await familyOf(input);
  await input.click();
  const picker = page.locator('.react-datepicker').filter({ visible: true }).first();
  try {
    await picker.waitFor({ state: 'visible', timeout: 5000 });
  } catch {
    notProven('gutenberg_datepicker', 'The authentic GravityView react-datepicker did not open from the real View block date control.');
    return { status: 'NOT_PROVEN' };
  }
  const month = picker.locator('.react-datepicker__current-month').first();
  const day = picker.locator('.react-datepicker__day:not(.react-datepicker__day--outside-month)').filter({ hasText: /\S/ }).first();
  const pickerFamily = await familyOf(picker);
  const monthMeasurement = await recordMeasuredTypography('gutenberg_datepicker_month', month);
  const dayMeasurement = await recordMeasuredTypography('gutenberg_datepicker_day', day);
  const location = await picker.evaluate(el => ({
    owner_document_is_top_level: el.ownerDocument.defaultView === window,
    in_gravityview_inspector: Boolean(el.closest('.gk-gravityview-blocks')),
    popper_class: el.closest('.react-datepicker-popper')?.className || null,
  }));

  results.dispositions.gutenberg_datepicker = typographyDisposition(pickerFamily);
  results.repair_seams.gutenberg_datepicker = 'Candidate: .gk-gravityview-blocks .react-datepicker on the GravityView View block registered editor-style/enqueue_block_editor_assets seam. This is a normal descendant in the observed runtime, so existing exclusion semantics can remain ancestry-based.';
  await page.keyboard.press('Escape').catch(() => {});

  return {
    disposition: typographyDisposition(pickerFamily),
    picker_font_family: pickerFamily,
    input_font_family: inputFamily,
    month: monthMeasurement,
    day: dayMeasurement,
    actual_location: location,
  };
});

await recorder.record('gutenberg_wordpress_dashicons_preserved', async () => {
  const icon = page.locator('#adminmenu .dashicons-before').first();
  if (!(await icon.count())) {
    notProven('gutenberg_wordpress_dashicons_preserved', 'The exact editor page did not expose a Dashicons admin-menu surface.');
    return { status: 'NOT_PROVEN' };
  }
  const family = await pseudoFamily(icon);
  assert.match(family, /dashicons/i, `WordPress icon must retain Dashicons; got ${family}`);
  assert.doesNotMatch(family, /Vazirmatn/i, `WordPress icon must not resolve to Vazirmatn; got ${family}`);
  return { computed_font_family: family };
});

await recorder.record('gutenberg_gform_icon_family_if_rendered', async () => {
  const icon = page.locator('[class*="gform-icon"], [class*="gform-icon-"]').first();
  if (!(await icon.count())) {
    notProven('gutenberg_gform_icon_family_if_rendered', 'No gform-icons-admin text-bearing/icon node rendered on the exact GravityView View block editor path.');
    return { status: 'NOT_PROVEN' };
  }
  const direct = await familyOf(icon);
  const before = await pseudoFamily(icon);
  assert.doesNotMatch(`${direct} ${before}`, /Vazirmatn/i, `Gravity Forms icon family must not be replaced by Vazirmatn; got direct=${direct}; before=${before}`);
  return { direct_font_family: direct, before_font_family: before };
});

await recorder.record('gravityview_oembed_admin_placeholder_via_core_parse_embed', async () => {
  await ensureGravityViewInspector();
  const response = await page.evaluate(async fixture => {
    const body = new URLSearchParams({
      action: 'parse-embed',
      shortcode: `[embed]${fixture.oembed_entry_url}[/embed]`,
      post_ID: String(fixture.editor_page_id),
      type: 'embed',
    });
    const request = await fetch(fixture.admin_ajax_url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body,
    });
    return { status: request.status, json: await request.json() };
  }, manifest);

  assert.equal(response.status, 200, 'WordPress parse-embed AJAX route must return HTTP 200.');
  if (!response.json?.success || !response.json?.data?.body?.includes('loading-placeholder')) {
    notProven('gravityview_oembed_admin_placeholder_via_core_parse_embed', 'The authentic WordPress parse-embed AJAX route did not return GravityView\'s admin loading-placeholder body.', 'NOT_REACHABLE');
    return { status: 'NOT_REACHABLE', ajax_response: response.json };
  }

  const measurement = await page.evaluate(html => {
    const wrapper = document.createElement('div');
    wrapper.id = 'vazir-gravityview-oembed-evidence';
    wrapper.setAttribute('data-source', 'authentic-wordpress-parse-embed-response');
    wrapper.innerHTML = html;
    document.body.appendChild(wrapper);
    const placeholder = wrapper.querySelector('.loading-placeholder');
    const heading = placeholder?.querySelector('h3');
    const paragraph = placeholder?.querySelector('p');
    const font = node => node ? getComputedStyle(node).fontFamily : null;
    return {
      placeholder_font_family: font(placeholder),
      heading_font_family: font(heading),
      paragraph_font_family: font(paragraph),
      surrounding_admin_font_family: getComputedStyle(document.body).fontFamily,
      heading_inline_style: heading?.getAttribute('style') || null,
      paragraph_inline_style: paragraph?.getAttribute('style') || null,
    };
  }, response.json.data.body);

  const headingDisposition = typographyDisposition(measurement.heading_font_family);
  const paragraphDisposition = typographyDisposition(measurement.paragraph_font_family);
  results.dispositions.gravityview_oembed_admin_placeholder = headingDisposition === 'FAIL' || paragraphDisposition === 'FAIL' ? 'FAIL' : 'ALREADY_VAZIRMATN';
  results.repair_seams.gravityview_oembed_admin_placeholder = 'UNCERTAIN: exact 3.3.4 emits only the generic .loading-placeholder with inline font-family. A CSS override would require !important, but no GravityView-specific wrapper is present in the returned fragment. Do not ship a broad .loading-placeholder override without a stable insertion-context selector.';

  return {
    route: 'WordPress authenticated admin-ajax.php action=parse-embed using [embed]GravityView-entry-URL[/embed]',
    route_reachability: 'PASS',
    presentation_measurement_context: 'Authentic AJAX response body mounted unchanged into the current authenticated wp-admin document solely for computed-style measurement.',
    disposition: results.dispositions.gravityview_oembed_admin_placeholder,
    ...measurement,
  };
});

fs.writeFileSync(path.join(artifactDir, 'gravityview-browser-results.json'), `${JSON.stringify(results, null, 2)}\n`);
if (recorder.failed()) {
  try { await page.screenshot({ path: path.join(artifactDir, 'gravityview-browser-failure.png'), fullPage: true }); } catch {}
}
await browser.close();
if (recorder.failed()) process.exit(1);
console.log(JSON.stringify(results, null, 2));
