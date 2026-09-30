import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { expectVazirmatn, expectNotVazirmatn, familyOf, pseudoFamily, login, makeRecorder } from '../../core/browser-helpers.mjs';

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
  dispositions: {},
  claim_ceiling: {
    ajax_dynamic_output: 'NOT_PROVEN unless the installed View uses an observable AJAX path',
    oembed_admin_placeholder: 'NOT_PROVEN unless an authentic WordPress 7.1 editor route renders GravityView .loading-placeholder',
  },
};
const recorder = makeRecorder(results);

const classifyFont = family => (/Vazirmatn/i.test(family) ? 'ALREADY_VAZIRMATN' : 'FAIL');
const measureFont = async (locator, label) => {
  await locator.waitFor({ state: 'visible', timeout: 30000 });
  const family = await familyOf(locator);
  return { label, font_family: family, disposition: classifyFont(family) };
};
const optionalFont = async (locator, label) => {
  if (!(await locator.count())) return { label, disposition: 'NOT_PROVEN', reason: 'target did not render in the authentic fixture' };
  return measureFont(locator.first(), label);
};
const classesOf = locator => locator.evaluate(el => el.getAttribute('class') || '');

async function dismissEditorWelcomeGuide(page) {
  const overlay = page.locator('.components-modal__screen-overlay').first();
  if (!(await overlay.count())) return false;
  try {
    await overlay.waitFor({ state: 'visible', timeout: 1500 });
  } catch {
    return false;
  }
  const close = overlay.getByRole('button', { name: 'Close', exact: true }).first();
  if (await close.count()) {
    await close.click();
  } else {
    await page.keyboard.press('Escape');
  }
  try {
    await overlay.waitFor({ state: 'hidden', timeout: 5000 });
  } catch {}
  return true;
}

async function ensureEditorSettingsSidebar(page) {
  await dismissEditorWelcomeGuide(page);
  if (!(await page.locator('.interface-interface-skeleton__sidebar').count())) {
    const settingsButton = page.getByRole('button', { name: 'Settings', exact: true });
    if (await settingsButton.count()) await settingsButton.first().click();
  }
  const tab = page.locator('[role="tab"]').filter({ hasText: /^Block$/ }).first();
  if (await tab.count()) {
    if ('true' !== await tab.getAttribute('aria-selected')) await tab.click();
    return;
  }
  const blockButton = page.getByRole('button', { name: 'Block', exact: true }).first();
  if (await blockButton.count()) await blockButton.click();
}

async function selectGravityViewBlock(page, index) {
  await page.waitForFunction(
    ({ name, wantedIndex }) => {
      const selector = window.wp?.data?.select?.('core/block-editor');
      if (!selector) return false;
      const matches = selector.getBlocks().filter(block => block.name === name);
      return Boolean(matches[wantedIndex]);
    },
    { name: manifest.gutenberg_block_name, wantedIndex: index },
    { timeout: 30000 },
  );
  await page.evaluate(({ name, wantedIndex }) => {
    const selector = window.wp.data.select('core/block-editor');
    const block = selector.getBlocks().filter(candidate => candidate.name === name)[wantedIndex];
    window.wp.data.dispatch('core/block-editor').selectBlock(block.clientId);
  }, { name: manifest.gutenberg_block_name, wantedIndex: index });
  await ensureEditorSettingsSidebar(page);
}

async function editorDocumentFacts(page) {
  const top = await page.evaluate(() => ({
    url: location.href,
    title: document.title,
    body_class: document.body.className,
    emotion_gk_select_nodes: [...document.querySelectorAll('style[data-emotion]')]
      .map(node => node.getAttribute('data-emotion'))
      .filter(value => value && value.startsWith('gk-select')),
  }));
  const frames = page.frames().filter(frame => frame !== page.mainFrame()).map(frame => ({ url: frame.url(), name: frame.name() }));
  return { top, child_frames: frames };
}

async function findLoadingPlaceholder(page) {
  for (const frame of page.frames()) {
    const placeholder = frame.locator('.loading-placeholder').first();
    if (await placeholder.count()) {
      try {
        await placeholder.waitFor({ state: 'visible', timeout: 5000 });
        return { frame, placeholder };
      } catch {}
    }
  }
  return null;
}

const browser = await chromium.launch();
const context = await browser.newContext();
const page = await context.newPage();

await recorder.record('frontend_modern_vantage_typography', async () => {
  await page.goto(manifest.frontend_url, { waitUntil: 'networkidle' });
  const container = page.locator('.gv-container.gv-themed').first();
  await container.waitFor({ state: 'visible', timeout: 30000 });
  const className = await classesOf(container);
  assert.match(className, /\bgv-theme-vantage\b/, `Expected explicit Vantage View class; got ${className}`);

  const cssVar = await container.evaluate(el => getComputedStyle(el).getPropertyValue('--gv-font-family').trim());
  const surfaces = {
    container: await measureFont(container, 'modern GravityView container'),
    header_text: await optionalFont(page.locator('.gv-container th').first(), 'modern GravityView table header text'),
    entry_value: await optionalFont(page.locator('.gv-container td').first(), 'modern GravityView entry cell text'),
    search_label: await optionalFont(page.locator('form.gv-widget-search label').first(), 'modern GravityView search label'),
    search_input: await measureFont(page.locator('form.gv-widget-search input[type="search"], form.gv-widget-search input[type="text"]').first(), 'modern GravityView search input'),
    search_button: await measureFont(page.locator('form.gv-widget-search .gv-search-button').first(), 'modern GravityView search button'),
    pagination: await optionalFont(page.locator('.gv-widget-page-links').first(), 'modern GravityView pagination text'),
    status_or_notice: await optionalFont(page.locator('.gv-container .gv-status, .gv-container .gv-notice').first(), 'modern GravityView status/notice'),
  };
  for (const [name, measurement] of Object.entries(surfaces)) results.dispositions[`frontend.${name}`] = measurement.disposition;
  const material = Object.values(surfaces).filter(item => !['NOT_PROVEN', 'NOT_REACHABLE'].includes(item.disposition));
  assert.ok(material.length >= 5, 'Modern GravityView frontend did not render enough representative material text surfaces.');
  assert.ok(material.every(item => item.disposition === 'ALREADY_VAZIRMATN'), `Modern GravityView frontend contains a non-Vazirmatn material surface: ${JSON.stringify(surfaces)}`);

  await expectNotVazirmatn(page.locator('#vf-view-excluded'), 'GravityView profile exclusion fixture', /monospace/i);
  const searchForm = page.locator('form.gv-widget-search').first();
  const search = searchForm.locator('input[type="search"], input[type="text"]').first();
  const submit = searchForm.locator('.gv-search-button').first();
  await search.fill('آلفا');
  await Promise.all([page.waitForLoadState('domcontentloaded'), submit.click()]);
  await page.locator('.gv-container.gv-themed').first().waitFor({ state: 'visible' });
  await expectVazirmatn(page.locator('.gv-container.gv-themed').first(), 'GravityView filtered Vantage result state');
  assert.match(await page.locator('.gv-container').first().innerText(), /آلفا/, 'Filtered View should contain the matching synthetic entry');

  return {
    view_theme: 'vantage',
    container_classes: className,
    computed_gv_font_family_token: cssVar,
    surfaces,
    exclusion: { selector: manifest.exclude_selector, disposition: 'PASS_NON_VAZIRMATN' },
  };
});

await login(page, baseUrl, user, password);

await recorder.record('admin_view_configuration_and_icon_ownership', async () => {
  await page.goto(manifest.admin_view_url, { waitUntil: 'domcontentloaded' });
  await page.locator('body.post-type-gravityview').waitFor({ state: 'visible', timeout: 30000 });
  await expectVazirmatn(page.locator('body.post-type-gravityview'), 'GravityView admin editor');
  const evidence = {};
  const icon = page.locator('.gv_tooltip, [data-gv-icon], .gv-icon__before, [class^="gv-icon-"]').first();
  if (await icon.count()) {
    const family = await pseudoFamily(icon);
    assert.match(family, /gravityview/i, `GravityView icon must retain its icon font; got ${family}`);
    evidence.gravityview_icon_family = family;
    results.dispositions['icons.gravityview'] = 'PASS';
  } else {
    evidence.gravityview_icon_family = 'NOT_PROVEN';
    results.dispositions['icons.gravityview'] = 'NOT_PROVEN';
  }
  const dashicon = page.locator('#adminmenu .dashicons-before, #adminmenu .wp-menu-image').first();
  if (await dashicon.count()) {
    const family = await pseudoFamily(dashicon);
    assert.match(family, /dashicons/i, `Dashicons must retain Dashicons; got ${family}`);
    evidence.dashicons_family = family;
    results.dispositions['icons.dashicons'] = 'PASS';
  } else {
    evidence.dashicons_family = 'NOT_PROVEN';
    results.dispositions['icons.dashicons'] = 'NOT_PROVEN';
  }
  return evidence;
});

await recorder.record('gutenberg_react_select_and_portal', async () => {
  await page.goto(manifest.gutenberg_editor_url, { waitUntil: 'domcontentloaded' });
  await page.locator('body.block-editor-page').waitFor({ state: 'visible', timeout: 30000 });
  await dismissEditorWelcomeGuide(page);
  await selectGravityViewBlock(page, 0);
  const wrapper = page.locator('.gk-gravityview-blocks .view-selector').first();
  await wrapper.waitFor({ state: 'visible', timeout: 30000 });

  const reactContainer = wrapper.locator('[class$="-container"]').first();
  const control = wrapper.locator('[class$="-control"]').first();
  const value = wrapper.locator('[class$="-singleValue"]').first();
  const input = wrapper.locator('input').first();
  const configured = {
    container: await measureFont(reactContainer, 'GravityView React Select container'),
    control: await measureFont(control, 'GravityView React Select control'),
    selected_value: await measureFont(value, 'GravityView React Select selected value'),
    input: await measureFont(input, 'GravityView React Select input/search'),
    classes: {
      container: await classesOf(reactContainer),
      control: await classesOf(control),
      selected_value: await classesOf(value),
    },
  };
  for (const [name, measurement] of Object.entries(configured)) {
    if (measurement && typeof measurement === 'object' && measurement.disposition) results.dispositions[`gutenberg.react_select.${name}`] = measurement.disposition;
  }

  await control.click();
  const portal = page.locator('[class$="-menuPortal"]').last();
  await portal.waitFor({ state: 'visible', timeout: 30000 });
  const option = portal.locator('[role="option"]').first();
  await option.waitFor({ state: 'visible', timeout: 30000 });
  const menu = portal.locator('[class$="-menu"]').first();
  const portalFacts = await portal.evaluate(el => ({
    class_name: el.getAttribute('class') || '',
    parent_is_document_body: el.parentElement === el.ownerDocument.body,
    owner_document_url: el.ownerDocument.location.href,
    inside_gravityview_semantic_wrapper: Boolean(el.closest('.gk-gravityview-blocks')),
  }));
  const portalEvidence = {
    portal: await measureFont(portal, 'GravityView React Select menu portal'),
    menu: await measureFont(menu, 'GravityView React Select menu'),
    option: await measureFont(option, 'GravityView React Select option text'),
    facts: portalFacts,
    menu_class: await classesOf(menu),
    option_class: await classesOf(option),
  };
  results.dispositions['gutenberg.react_select.portal'] = portalEvidence.portal.disposition;
  results.dispositions['gutenberg.react_select.menu'] = portalEvidence.menu.disposition;
  results.dispositions['gutenberg.react_select.option'] = portalEvidence.option.disposition;
  await page.keyboard.press('Escape');

  await selectGravityViewBlock(page, 1);
  const unconfigured = page.locator('.gk-gravityview-blocks .view-selector').first();
  await unconfigured.waitFor({ state: 'visible', timeout: 30000 });
  const placeholder = unconfigured.locator('[class$="-placeholder"]').first();
  const placeholderEvidence = await measureFont(placeholder, 'GravityView React Select placeholder');
  results.dispositions['gutenberg.react_select.placeholder'] = placeholderEvidence.disposition;

  const docs = await editorDocumentFacts(page);
  assert.ok(docs.top.emotion_gk_select_nodes.length > 0, 'GravityView React Select Emotion cache ownership was not observed in the editor document.');
  const assetUrls = await page.evaluate(() => performance.getEntriesByType('resource').map(entry => entry.name).filter(url => /gravityview\/src\/PageBuilder\/Gutenberg\/build\/view\.(?:js|css)/.test(url)));
  assert.ok(assetUrls.some(url => /view\.js/.test(url)), 'GravityView View block editor script was not observed in the authentic editor document.');
  assert.ok(assetUrls.some(url => /view\.css/.test(url)), 'GravityView View block editor stylesheet was not observed in the authentic editor document.');

  return {
    block_name: manifest.gutenberg_block_name,
    editor_url: manifest.gutenberg_editor_url,
    documents: docs,
    loaded_view_assets: assetUrls,
    configured_control: configured,
    placeholder: placeholderEvidence,
    portaled_menu: portalEvidence,
    portal_boundary: portalFacts.inside_gravityview_semantic_wrapper ? 'DESCENDANT' : 'DETACHED_FROM_GRAVITYVIEW_WRAPPER',
  };
});

await recorder.record('gutenberg_datepicker', async () => {
  await selectGravityViewBlock(page, 0);
  const panelButton = page.getByRole('button', { name: 'Entries Settings', exact: true }).first();
  if (!(await panelButton.count())) {
    results.dispositions['gutenberg.datepicker'] = 'NOT_PROVEN';
    return { disposition: 'NOT_PROVEN', reason: 'Entries Settings panel did not render for the authentic GravityView View block after selecting the Block inspector tab' };
  }
  const expanded = await panelButton.getAttribute('aria-expanded');
  if ('true' !== expanded) await panelButton.click();
  const input = page.locator('.interface-interface-skeleton__sidebar .react-datepicker-wrapper input, .block-editor-block-inspector .react-datepicker-wrapper input').first();
  if (!(await input.count())) {
    results.dispositions['gutenberg.datepicker'] = 'NOT_PROVEN';
    return { disposition: 'NOT_PROVEN', reason: 'Authentic GravityView date control did not expose a react-datepicker input in the selected block inspector' };
  }
  const inputEvidence = await measureFont(input, 'GravityView Datepicker input');
  await input.click();
  const root = page.locator('.react-datepicker').first();
  if (!(await root.count())) {
    results.dispositions['gutenberg.datepicker'] = 'NOT_PROVEN';
    return { disposition: 'NOT_PROVEN', input: inputEvidence, reason: 'Date control rendered, but the authentic picker popup did not become reachable' };
  }
  await root.waitFor({ state: 'visible', timeout: 30000 });
  const header = root.locator('.react-datepicker__current-month').first();
  const day = root.locator('.react-datepicker__day:not(.react-datepicker__day--outside-month):not(.react-datepicker__day--disabled)').first();
  const rootEvidence = await measureFont(root, 'GravityView Datepicker root');
  const headerEvidence = await measureFont(header, 'GravityView Datepicker current month');
  const dayEvidence = await measureFont(day, 'GravityView Datepicker day text');
  const location = await root.evaluate(el => ({
    owner_document_url: el.ownerDocument.location.href,
    inside_gravityview_semantic_wrapper: Boolean(el.closest('.gk-gravityview-blocks')),
    inside_block_inspector: Boolean(el.closest('.block-editor-block-inspector')),
    parent_class: el.parentElement?.getAttribute('class') || '',
    ancestor_classes: [el.parentElement, el.parentElement?.parentElement, el.parentElement?.parentElement?.parentElement]
      .filter(Boolean)
      .map(node => node.getAttribute('class') || ''),
  }));
  results.dispositions['gutenberg.datepicker.input'] = inputEvidence.disposition;
  results.dispositions['gutenberg.datepicker.root'] = rootEvidence.disposition;
  results.dispositions['gutenberg.datepicker.header'] = headerEvidence.disposition;
  results.dispositions['gutenberg.datepicker.day'] = dayEvidence.disposition;
  results.dispositions['gutenberg.datepicker'] = rootEvidence.disposition;
  await page.keyboard.press('Escape');
  return { disposition: rootEvidence.disposition, input: inputEvidence, root: rootEvidence, current_month: headerEvidence, day: dayEvidence, location };
});

await recorder.record('gutenberg_icon_ownership', async () => {
  const evidence = {};
  const dashicon = page.locator('#adminmenu .dashicons-before, #adminmenu .wp-menu-image').first();
  if (await dashicon.count()) {
    const family = await pseudoFamily(dashicon);
    assert.match(family, /dashicons/i, `Block editor Dashicons must remain protected; got ${family}`);
    evidence.dashicons = { disposition: 'PASS', font_family: family };
  } else {
    evidence.dashicons = { disposition: 'NOT_PROVEN' };
  }
  const gformIcon = page.locator('[class*="gform-icon"], [class*="gform-icon-"]').first();
  if (await gformIcon.count()) {
    const family = await pseudoFamily(gformIcon);
    evidence.gform_icons_admin = { disposition: /gform|gravity/i.test(family) ? 'PASS' : 'FAIL', font_family: family };
    if ('FAIL' === evidence.gform_icons_admin.disposition) throw new Error(`Gravity Forms icon family was overwritten: ${family}`);
  } else {
    evidence.gform_icons_admin = { disposition: 'NOT_PROVEN', reason: 'exact GravityView Gutenberg route rendered no representative gform-icons-admin node' };
  }
  results.dispositions['icons.gutenberg_dashicons'] = evidence.dashicons.disposition;
  results.dispositions['icons.gform_icons_admin'] = evidence.gform_icons_admin.disposition;
  return evidence;
});

await recorder.record('oembed_admin_placeholder_reachability', async () => {
  if (!manifest.oembed_editor_url || !manifest.oembed_entry_url) {
    results.dispositions['oembed.admin_placeholder'] = 'NOT_PROVEN';
    return { disposition: 'NOT_PROVEN', reason: 'GravityView entry URL or oEmbed editor fixture could not be constructed through public runtime APIs' };
  }
  await page.goto(manifest.oembed_editor_url, { waitUntil: 'domcontentloaded' });
  await page.locator('body.block-editor-page').waitFor({ state: 'visible', timeout: 30000 });
  await dismissEditorWelcomeGuide(page);
  await page.waitForLoadState('networkidle');
  const found = await findLoadingPlaceholder(page);
  if (!found) {
    const routeEvidence = await page.evaluate(() => {
      const selector = window.wp?.data?.select?.('core/block-editor');
      return {
        block_names: selector ? selector.getBlocks().map(block => block.name) : [],
        editor_url: location.href,
      };
    });
    results.dispositions['oembed.admin_placeholder'] = 'NOT_REACHABLE';
    return {
      disposition: 'NOT_REACHABLE',
      route: 'authentic WordPress 7.1 block editor with a serialized core/embed block targeting a real GravityView entry URL',
      entry_url: manifest.oembed_entry_url,
      evidence: routeEvidence,
      limitation: 'This proves the qualified Gutenberg route did not render GravityView .loading-placeholder; it does not prove every historical wp-admin embed route is impossible.',
    };
  }
  const { frame, placeholder } = found;
  const heading = placeholder.locator('h3').first();
  const paragraph = placeholder.locator('p').first();
  const placeholderEvidence = await measureFont(placeholder, 'GravityView oEmbed loading placeholder');
  const headingEvidence = await measureFont(heading, 'GravityView oEmbed heading');
  const paragraphEvidence = await measureFont(paragraph, 'GravityView oEmbed paragraph');
  const contextFacts = await placeholder.evaluate(el => {
    const ancestors = [];
    let current = el.parentElement;
    for (let i = 0; current && i < 5; i += 1, current = current.parentElement) ancestors.push({ tag: current.tagName, id: current.id || '', class_name: current.getAttribute('class') || '' });
    return { owner_document_url: el.ownerDocument.location.href, ancestors };
  });
  results.dispositions['oembed.admin_placeholder'] = headingEvidence.disposition;
  return {
    disposition: headingEvidence.disposition,
    document_url: frame.url(),
    placeholder: placeholderEvidence,
    heading: headingEvidence,
    paragraph: paragraphEvidence,
    context: contextFacts,
  };
});

fs.writeFileSync(path.join(artifactDir, 'gravityview-browser-results.json'), `${JSON.stringify(results, null, 2)}\n`);
if (recorder.failed()) {
  try { await page.screenshot({ path: path.join(artifactDir, 'gravityview-browser-failure.png'), fullPage: true }); } catch {}
}
await browser.close();
if (recorder.failed()) process.exit(1);
console.log(JSON.stringify(results, null, 2));
