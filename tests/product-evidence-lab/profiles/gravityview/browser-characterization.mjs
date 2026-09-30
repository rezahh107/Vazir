import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { familyOf, expectNotVazirmatn, pseudoFamily, login, makeRecorder } from '../../core/browser-helpers.mjs';

const baseUrl = process.env.VAZIR_LAB_BASE_URL || 'http://127.0.0.1:8080';
const artifactDir = process.env.VAZIR_LAB_ARTIFACT_DIR;
const user = process.env.VAZIR_LAB_ADMIN_USER || 'vazir_lab_admin';
const password = process.env.VAZIR_LAB_ADMIN_PASSWORD || 'vazir-lab-admin-password';
if (!artifactDir) throw new Error('VAZIR_LAB_ARTIFACT_DIR is required');

const manifest = JSON.parse(fs.readFileSync(path.join(artifactDir, 'gravityview-fixture.json'), 'utf8'));
const results = {
  status: 'PASS',
  profile: 'gravityview',
  scenarios: {},
  targets: {},
  claim_ceiling: {
    ajax_dynamic_output: 'NOT_PROVEN unless an observable GravityView AJAX output path is actually exercised',
    oembed_admin_placeholder: 'NOT_PROVEN unless the authentic editor/embed path renders GravityView .loading-placeholder',
  },
};
const recorder = makeRecorder(results);
const dispositionForFamily = family => /Vazirmatn/i.test(family) ? 'ALREADY_VAZIRMATN' : 'FAIL';
const documentKind = locator => locator.evaluate(el => el.ownerDocument.defaultView === el.ownerDocument.defaultView.top ? 'top' : 'iframe');

async function measureTarget(name, locator, { required = true } = {}) {
  if (!await locator.count()) {
    const result = { disposition: required ? 'NOT_REACHABLE' : 'NOT_PROVEN', reason: 'No authentic rendered node matched the bounded target.' };
    results.targets[name] = result;
    if (required) throw new Error(`${name} did not render on the authentic fixture`);
    return result;
  }
  const node = locator.first();
  if (!await node.isVisible().catch(() => false)) {
    const result = { disposition: required ? 'NOT_REACHABLE' : 'NOT_PROVEN', reason: 'Authentic node exists but is not visible.' };
    results.targets[name] = result;
    if (required) throw new Error(`${name} exists but is not visible`);
    return result;
  }
  const family = await familyOf(node);
  const result = { disposition: dispositionForFamily(family), font_family: family, document: await documentKind(node) };
  results.targets[name] = result;
  return result;
}

async function firstFrameWith(page, selector) {
  for (const frame of page.frames()) {
    const locator = frame.locator(selector).first();
    if (await locator.count()) return { frame, locator, kind: frame === page.mainFrame() ? 'top' : 'iframe' };
  }
  return null;
}

async function dismissEditorWelcome(page) {
  const overlay = page.locator('.components-modal__screen-overlay').first();
  if (!await overlay.count() || !await overlay.isVisible().catch(() => false)) return false;
  const close = overlay.locator('button[aria-label="Close"], button[aria-label="Close dialog"], .components-modal__header button').first();
  if (await close.count()) await close.click({ force: true }).catch(() => {});
  if (await overlay.isVisible().catch(() => false)) await page.keyboard.press('Escape').catch(() => {});
  await overlay.waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {});
  return true;
}

async function exposeBlockInspector(page) {
  await page.evaluate(() => {
    try {
      const dispatch = window.wp?.data?.dispatch?.('core/edit-post');
      dispatch?.openGeneralSidebar?.('edit-post/block');
    } catch {}
  });
  const inspector = page.locator('.gk-gravityview-blocks').first();
  if (await inspector.isVisible().catch(() => false)) return inspector;
  const settings = page.locator('button[aria-label="Settings"], button[aria-label="Settings sidebar"]').first();
  if (await settings.count()) await settings.click().catch(() => {});
  await inspector.waitFor({ state: 'visible', timeout: 15000 });
  return inspector;
}

async function collectGravityViewAssets(page) {
  return page.evaluate(() => {
    const normalize = value => {
      try { return new URL(value, location.href).pathname; } catch { return value || ''; }
    };
    const styles = [...document.querySelectorAll('link[rel="stylesheet"]')]
      .filter(node => /gravityview/i.test(node.href))
      .map(node => ({ id: node.id || null, path: normalize(node.href) }));
    const scripts = [...document.querySelectorAll('script[src]')]
      .filter(node => /gravityview/i.test(node.src))
      .map(node => ({ id: node.id || null, path: normalize(node.src) }));
    return { styles, scripts };
  });
}

async function findGravityViewIcon(page) {
  for (const selector of ['.gv_tooltip', '[data-gv-icon]', '.gv-icon__before', '[class^="gv-icon-"]', '[class*=" gv-icon-"]']) {
    const candidate = page.locator(selector).first();
    if (await candidate.count() && await candidate.isVisible().catch(() => false)) return candidate;
  }
  return null;
}

const browser = await chromium.launch();
const context = await browser.newContext();
const page = await context.newPage();

await recorder.record('modern_frontend_inner_typography', async () => {
  await page.goto(manifest.frontend_url, { waitUntil: 'networkidle' });
  const container = page.locator('.gv-container').first();
  await container.waitFor({ state: 'visible', timeout: 30000 });
  const classes = (await container.getAttribute('class')) || '';
  assert.match(classes, /\bgv-themed\b/, `Qualified GravityView frontend must use the modern themed surface; got ${classes}`);
  assert.match(classes, /\bgv-theme-vantage\b/, `Qualified GravityView frontend must use the Vantage theme; got ${classes}`);

  const gvFontToken = (await container.evaluate(el => getComputedStyle(el).getPropertyValue('--gv-font-family'))).trim();
  const tokenEvidence = {
    source_declared_default: 'inherit',
    computed_custom_property: gvFontToken || null,
    runtime_interpretation: gvFontToken || 'NOT_EXPOSED_BY_COMPUTED_STYLE_ON_RENDERED_CONTAINER',
  };

  const measurements = {};
  measurements.container = await measureTarget('frontend.container', container);
  measurements.table_header = await measureTarget('frontend.table_header', container.locator('thead th').first(), { required: false });
  measurements.entry_value = await measureTarget('frontend.entry_value', container.locator('tbody td').first(), { required: false });

  const searchForm = page.locator('form.gv-widget-search').first();
  await searchForm.waitFor({ state: 'visible', timeout: 30000 });
  measurements.search_label = await measureTarget('frontend.search_label', searchForm.locator('label').first(), { required: false });
  const search = searchForm.locator('input[type="search"], input[type="text"]').first();
  measurements.search_input = await measureTarget('frontend.search_input', search);
  const submit = searchForm.locator('.gv-search-button, button[type="submit"], input[type="submit"]').first();
  measurements.search_button = await measureTarget('frontend.search_button', submit);
  measurements.pagination = await measureTarget('frontend.pagination', page.locator('.gv-widget-page-links').first(), { required: false });
  measurements.status_notice = await measureTarget('frontend.status_notice', page.locator('.gv-notice, .gv-message, .gv-status').first(), { required: false });

  await expectNotVazirmatn(page.locator('#vf-view-excluded'), 'GravityView profile exclusion fixture', /monospace/i);
  results.targets['frontend.exclusion_fixture'] = { disposition: 'PASS', font_family: await familyOf(page.locator('#vf-view-excluded')) };

  await search.fill('آلفا');
  await Promise.all([page.waitForLoadState('domcontentloaded'), submit.click()]);
  const filtered = page.locator('.gv-container').first();
  await filtered.waitFor({ state: 'visible' });
  const filteredFamily = await familyOf(filtered);
  results.targets['frontend.filtered_result'] = { disposition: dispositionForFamily(filteredFamily), font_family: filteredFamily };
  assert.match(await filtered.innerText(), /آلفا/, 'Filtered View should contain the matching synthetic entry');

  const textTargets = Object.values(measurements).filter(item => ['ALREADY_VAZIRMATN', 'FAIL'].includes(item.disposition));
  const disposition = textTargets.some(item => item.disposition === 'FAIL') ? 'FAIL' : 'ALREADY_VAZIRMATN';
  return { disposition, gv_font_family_token: tokenEvidence, container_classes: classes, measurements };
});

await login(page, baseUrl, user, password);

await recorder.record('admin_view_configuration_and_icon_ownership', async () => {
  await page.goto(manifest.admin_view_url, { waitUntil: 'domcontentloaded' });
  await page.locator('body.post-type-gravityview').waitFor({ state: 'visible', timeout: 30000 });
  const adminBody = await measureTarget('admin.legacy_view_editor_body', page.locator('body.post-type-gravityview'));

  const icon = await findGravityViewIcon(page);
  let gravityViewIcon = { disposition: 'NOT_PROVEN', reason: 'No representative GravityView icon rendered on the configured View editor.' };
  if (icon) {
    const family = await pseudoFamily(icon);
    assert.match(family, /gravityview/i, `GravityView icon must retain its icon font; got ${family}`);
    gravityViewIcon = { disposition: 'PASS', font_family: family };
  }
  results.targets['icons.gravityview'] = gravityViewIcon;

  const dashicon = page.locator('.dashicons').first();
  let dashicons = { disposition: 'NOT_PROVEN', reason: 'No visible Dashicons node rendered on the configured View editor.' };
  if (await dashicon.count() && await dashicon.isVisible().catch(() => false)) {
    const family = await pseudoFamily(dashicon);
    assert.match(family, /dashicons/i, `Dashicons must retain the Dashicons family; got ${family}`);
    dashicons = { disposition: 'PASS', font_family: family };
  }
  results.targets['icons.dashicons'] = dashicons;
  return { admin_body: adminBody, gravityview_icon: gravityViewIcon, dashicons };
});

await recorder.record('gutenberg_view_block_and_assets', async () => {
  await page.goto(manifest.block_editor_url, { waitUntil: 'domcontentloaded' });
  await page.locator('body.block-editor-page').waitFor({ state: 'visible', timeout: 30000 });
  await page.waitForTimeout(1000);
  const welcome_dismissed = await dismissEditorWelcome(page);

  const blockSurface = await firstFrameWith(page, '[data-type="gk-gravityview-blocks/view"]');
  assert.ok(blockSurface, 'Authentic GravityView View block did not render in the block editor canvas');
  await blockSurface.locator.click({ force: true });
  const inspector = await exposeBlockInspector(page);
  await inspector.waitFor({ state: 'visible', timeout: 15000 });

  const assets = await collectGravityViewAssets(page);
  assert.ok(assets.styles.length > 0, 'No GravityView stylesheet was observed in the top-level block editor document');
  assert.ok(assets.scripts.length > 0, 'No GravityView script was observed in the top-level block editor document');
  const iframe = page.locator('iframe[name="editor-canvas"]').first();
  return {
    editor_url: page.url(),
    block_name: manifest.block_name,
    block_document: blockSurface.kind,
    top_level_editor_document: 'top',
    editor_canvas_iframe_present: Boolean(await iframe.count()),
    welcome_guide_dismissed: welcome_dismissed,
    gravityview_assets: assets,
  };
});

await recorder.record('gutenberg_react_select_control_and_portal', async () => {
  const wrapper = page.locator('.gk-gravityview-blocks .view-selector').first();
  await wrapper.waitFor({ state: 'visible', timeout: 15000 });
  const input = wrapper.locator('[role="combobox"]').first();
  await input.waitFor({ state: 'visible', timeout: 15000 });

  const inputMeasurement = await measureTarget('react_select.input', input);
  const selectedMeasurement = await measureTarget('react_select.selected_value', wrapper.locator('[id$="-single-value"]').first(), { required: false });
  const placeholderMeasurement = await measureTarget('react_select.placeholder', wrapper.locator('[id$="-placeholder"]').first(), { required: false });
  const wrapperMeasurement = await measureTarget('react_select.host_wrapper', wrapper);

  const containerOwnership = await input.evaluate(el => {
    let node = el;
    while (node && node !== el.ownerDocument.body) {
      const classes = [...(node.classList || [])];
      if (classes.some(name => /^gk-select-.*-container$/.test(name))) return { classes, font_family: getComputedStyle(node).fontFamily };
      node = node.parentElement;
    }
    return null;
  });

  await input.click();
  await input.press('ArrowDown').catch(() => {});
  const listbox = page.locator('[role="listbox"]').first();
  await listbox.waitFor({ state: 'visible', timeout: 15000 });
  const optionMeasurement = await measureTarget('react_select.menu_option', listbox.locator('[role="option"]').first());

  const portal = await listbox.evaluate(el => {
    const doc = el.ownerDocument;
    let node = el;
    let portalNode = null;
    while (node && node !== doc.body) {
      const classes = [...(node.classList || [])];
      if (classes.some(name => /^gk-select-.*-menuPortal$/.test(name))) { portalNode = node; break; }
      node = node.parentElement;
    }
    return {
      document: doc.defaultView === doc.defaultView.top ? 'top' : 'iframe',
      portal_classes: portalNode ? [...portalNode.classList] : [],
      portal_is_direct_body_child: Boolean(portalNode && portalNode.parentElement === doc.body),
      gravityview_semantic_ancestor: Boolean(el.closest('.gk-gravityview-blocks')),
      listbox_id: el.id || null,
      listbox_classes: [...el.classList],
    };
  });

  const emotion = await page.evaluate(() => [...document.querySelectorAll('style[data-emotion]')]
    .filter(node => /^gk-select(?:\s|$)/.test(node.getAttribute('data-emotion') || ''))
    .map(node => ({ data_emotion: node.getAttribute('data-emotion'), has_font_family_rule: /font-family/i.test(node.textContent || '') })));
  await input.press('Escape').catch(() => {});

  const textMeasurements = [inputMeasurement, selectedMeasurement, placeholderMeasurement, optionMeasurement]
    .filter(item => ['ALREADY_VAZIRMATN', 'FAIL'].includes(item.disposition));
  const disposition = textMeasurements.some(item => item.disposition === 'FAIL') ? 'FAIL' : 'ALREADY_VAZIRMATN';
  const boundedPortalSeam = portal.portal_is_direct_body_child && !portal.gravityview_semantic_ancestor
    ? 'NO_STABLE_GRAVITYVIEW_SEMANTIC_ANCESTOR_PROVEN'
    : 'REQUIRES_RUNTIME_REVIEW';
  results.targets['react_select.portal_scope'] = { disposition: boundedPortalSeam.startsWith('NO_STABLE') ? 'NOT_PROVEN' : 'PASS', association: boundedPortalSeam };

  return {
    disposition,
    wrapper: wrapperMeasurement,
    input: inputMeasurement,
    selected_value: selectedMeasurement,
    placeholder: placeholderMeasurement,
    menu_option: optionMeasurement,
    container_ownership: containerOwnership,
    portal,
    emotion_style_ownership: emotion,
    production_selector_note: 'Dynamic Emotion hash classes are evidence only and are not admitted as production selector authority.',
  };
});

await recorder.record('gutenberg_datepicker', async () => {
  const inspector = await exposeBlockInspector(page);
  const entriesButton = inspector.getByRole('button', { name: 'Entries Settings', exact: true }).first();
  if (!await entriesButton.count()) {
    results.targets['datepicker.root'] = { disposition: 'NOT_PROVEN', reason: 'Entries Settings panel was not deterministically rendered.' };
    return { disposition: 'NOT_PROVEN', reason: 'Entries Settings panel was unavailable.' };
  }
  if ((await entriesButton.getAttribute('aria-expanded')) !== 'true') await entriesButton.click();

  const startDateControl = inspector.locator('.components-base-control').filter({ hasText: 'Start Date' }).first();
  if (!await startDateControl.count()) {
    results.targets['datepicker.root'] = { disposition: 'NOT_PROVEN', reason: 'Authentic Start Date control was not deterministically rendered.' };
    return { disposition: 'NOT_PROVEN', reason: 'Start Date control was unavailable.' };
  }
  const dateInput = startDateControl.locator('input').first();
  if (!await dateInput.count()) {
    results.targets['datepicker.root'] = { disposition: 'NOT_PROVEN', reason: 'Authentic Datepicker input was not deterministically rendered.' };
    return { disposition: 'NOT_PROVEN', reason: 'Datepicker input was unavailable.' };
  }

  const inputMeasurement = await measureTarget('datepicker.input', dateInput);
  await dateInput.click();
  const root = page.locator('.react-datepicker').first();
  if (!await root.count()) {
    results.targets['datepicker.root'] = { disposition: 'NOT_PROVEN', reason: 'Clicking the authentic GravityView date input did not render .react-datepicker.' };
    return { disposition: 'NOT_PROVEN', input: inputMeasurement };
  }
  await root.waitFor({ state: 'visible', timeout: 15000 });
  const rootMeasurement = await measureTarget('datepicker.root', root);
  const monthMeasurement = await measureTarget('datepicker.current_month', root.locator('.react-datepicker__current-month').first(), { required: false });
  const dayMeasurement = await measureTarget('datepicker.day', root.locator('.react-datepicker__day:not(.react-datepicker__day--outside-month)').first(), { required: false });
  const placement = await root.evaluate(el => {
    const doc = el.ownerDocument;
    const popper = el.closest('.react-datepicker-popper');
    const gvAncestor = el.closest('.gk-gravityview-blocks');
    return {
      document: doc.defaultView === doc.defaultView.top ? 'top' : 'iframe',
      popper_present: Boolean(popper),
      popper_is_direct_body_child: Boolean(popper && popper.parentElement === doc.body),
      gravityview_semantic_ancestor: Boolean(gvAncestor),
      closest_semantic_classes: gvAncestor ? [...gvAncestor.classList] : [],
    };
  });
  const measured = [rootMeasurement, monthMeasurement, dayMeasurement, inputMeasurement]
    .filter(item => ['ALREADY_VAZIRMATN', 'FAIL'].includes(item.disposition));
  const disposition = measured.some(item => item.disposition === 'FAIL') ? 'FAIL' : 'ALREADY_VAZIRMATN';
  return { disposition, input: inputMeasurement, root: rootMeasurement, current_month: monthMeasurement, day: dayMeasurement, placement };
});

await recorder.record('gutenberg_oembed_admin_placeholder_reachability', async () => {
  if (!manifest.oembed_entry_url) {
    results.targets['oembed.loading_placeholder'] = { disposition: 'NOT_PROVEN', reason: 'Fixture could not derive an authentic GravityView entry permalink.' };
    return { disposition: 'NOT_PROVEN', reason: 'No authentic GravityView entry permalink was available.' };
  }
  const embedSurface = await firstFrameWith(page, '[data-type="core/embed"]');
  if (!embedSurface) {
    results.targets['oembed.loading_placeholder'] = { disposition: 'NOT_PROVEN', reason: 'Authentic core/embed fixture did not render in the block editor.' };
    return { disposition: 'NOT_PROVEN', reason: 'core/embed block was not reachable.' };
  }
  await embedSurface.locator.scrollIntoViewIfNeeded().catch(() => {});
  await page.waitForTimeout(1000);
  const placeholderSurface = await firstFrameWith(page, '.loading-placeholder');
  if (!placeholderSurface) {
    results.targets['oembed.loading_placeholder'] = {
      disposition: 'NOT_PROVEN',
      reason: 'The authentic WordPress block-editor embed path did not expose GravityView .loading-placeholder; source risk is not promoted to runtime FAIL.',
    };
    return { disposition: 'NOT_PROVEN', entry_url_path: (() => { try { return new URL(manifest.oembed_entry_url).pathname; } catch { return ''; } })(), editor_embed_document: embedSurface.kind };
  }

  const placeholder = placeholderSurface.locator;
  const placeholderMeasurement = await measureTarget('oembed.loading_placeholder', placeholder);
  const headingMeasurement = await measureTarget('oembed.heading', placeholder.locator('h1, h2, h3, h4, h5, h6').first(), { required: false });
  const paragraphMeasurement = await measureTarget('oembed.paragraph', placeholder.locator('p').first(), { required: false });
  const surrounding = await measureTarget('oembed.surrounding_editor', page.locator('body.block-editor-page'));
  const measured = [headingMeasurement, paragraphMeasurement].filter(item => ['ALREADY_VAZIRMATN', 'FAIL'].includes(item.disposition));
  const disposition = measured.some(item => item.disposition === 'FAIL') ? 'FAIL' : (measured.length ? 'ALREADY_VAZIRMATN' : 'NOT_PROVEN');
  return { disposition, placeholder: placeholderMeasurement, heading: headingMeasurement, paragraph: paragraphMeasurement, surrounding_editor: surrounding, document: placeholderSurface.kind };
});

await recorder.record('gutenberg_optional_gform_icon_ownership', async () => {
  const icon = page.locator('[class*="gform-icon"], [class*="gform-icons-admin"]').first();
  if (!await icon.count() || !await icon.isVisible().catch(() => false)) {
    results.targets['icons.gform_admin'] = { disposition: 'NOT_PROVEN', reason: 'No representative visible gform-icons-admin surface rendered on the exact GravityView editor fixture.' };
    return { disposition: 'NOT_PROVEN' };
  }
  const family = await pseudoFamily(icon);
  assert.match(family, /gform|gravity/i, `Gravity Forms admin icon must retain its glyph family; got ${family}`);
  results.targets['icons.gform_admin'] = { disposition: 'PASS', font_family: family };
  return { disposition: 'PASS', font_family: family };
});

fs.writeFileSync(path.join(artifactDir, 'gravityview-browser-results.json'), `${JSON.stringify(results, null, 2)}\n`);
if (recorder.failed()) {
  try { await page.screenshot({ path: path.join(artifactDir, 'gravityview-browser-failure.png'), fullPage: true }); } catch {}
}
await browser.close();
if (recorder.failed()) process.exit(1);
console.log(JSON.stringify(results, null, 2));
