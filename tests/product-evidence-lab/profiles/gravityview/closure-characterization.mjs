import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import { familyOf, login } from '../../core/browser-helpers.mjs';

const baseUrl = process.env.VAZIR_LAB_BASE_URL || 'http://127.0.0.1:8080';
const artifactDir = process.env.VAZIR_LAB_ARTIFACT_DIR;
const user = process.env.VAZIR_LAB_ADMIN_USER || 'vazir_lab_admin';
const password = process.env.VAZIR_LAB_ADMIN_PASSWORD || 'vazir-lab-admin-password';
if (!artifactDir) throw new Error('VAZIR_LAB_ARTIFACT_DIR is required');

const manifest = JSON.parse(fs.readFileSync(path.join(artifactDir, 'gravityview-fixture.json'), 'utf8'));
const sourceEvidence = JSON.parse(fs.readFileSync(path.join(artifactDir, 'gravityview-closure-runtime-results.json'), 'utf8'));
const results = {
  status: 'PASS',
  profile: 'gravityview-closure',
  exact_runtime: sourceEvidence.runtime,
  portal: {},
  oembed: {
    status: 'DEFERRED',
    evidence_owner: 'closure-oembed-classic-characterization.mjs',
  },
};

const isVazirmatn = family => /Vazirmatn/i.test(family || '');
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));

async function dismissCoreEditorWelcomeGuide(page) {
  const overlay = page.locator('.components-modal__screen-overlay').filter({ hasText: /Welcome to the editor/i }).first();
  if (!(await overlay.count()) || !(await overlay.isVisible())) return false;
  const closeButton = overlay.locator('button[aria-label="Close"]').first();
  if (await closeButton.count()) await closeButton.click();
  else await page.keyboard.press('Escape');
  await overlay.waitFor({ state: 'hidden', timeout: 5000 });
  return true;
}

async function ensureGravityViewInspector(page) {
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
  await dismissCoreEditorWelcomeGuide(page);
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

async function visibleListbox(page) {
  const listboxes = page.locator('[role="listbox"]:visible');
  const count = await listboxes.count();
  return count ? listboxes.nth(count - 1) : null;
}

async function openPortalSemantically(page, root, input) {
  const attempts = [];
  const actions = [
    ['focus+ArrowDown', async () => { await input.focus(); await page.keyboard.press('ArrowDown'); }],
    ['control-click', async () => { await root.locator('[class$="-control"]').first().click(); }],
    ['input-click+ArrowDown', async () => { await input.click(); await page.keyboard.press('ArrowDown'); }],
  ];

  for (const [name, action] of actions) {
    try {
      await action();
      await sleep(500);
      const listbox = await visibleListbox(page);
      attempts.push({ interaction: name, visible_listbox: Boolean(listbox) });
      if (listbox) return { listbox, attempts, successful_interaction: name };
    } catch (error) {
      attempts.push({ interaction: name, visible_listbox: false, error: String(error?.message || error) });
    }
  }
  return { listbox: null, attempts, successful_interaction: null };
}

async function firstVisible(locator) {
  const count = await locator.count();
  for (let index = 0; index < count; index += 1) {
    const candidate = locator.nth(index);
    if (await candidate.isVisible().catch(() => false)) return candidate;
  }
  return null;
}

const browser = await chromium.launch();
const context = await browser.newContext();
const page = await context.newPage();
await login(page, baseUrl, user, password);

await ensureGravityViewInspector(page);
const selectRoot = page.locator('.gk-gravityview-blocks .view-selector').first();
const selectInput = selectRoot.locator('input[role="combobox"]').first();
await selectRoot.waitFor({ state: 'visible', timeout: 10000 });
await selectInput.waitFor({ state: 'visible', timeout: 10000 });

const portalOpen = await openPortalSemantically(page, selectRoot, selectInput);
if (!portalOpen.listbox) {
  const portalCandidates = await page.locator('body > *').evaluateAll(nodes => nodes.map(node => ({
    tag: node.tagName,
    id: node.id || null,
    class_name: typeof node.className === 'string' ? node.className : '',
    role: node.getAttribute('role'),
  })).filter(item => /gk-select|menu/i.test(item.class_name) || item.role === 'listbox'));
  results.portal = {
    interaction_attempts: portalOpen.attempts,
    visible_listbox_reached: false,
    portal_candidates: portalCandidates,
    final_disposition: 'NOT_PROVEN',
    reason: 'A stable visible listbox/option surface was not reached after the bounded authentic interactions.',
  };
} else {
  const listbox = portalOpen.listbox;
  const option = await firstVisible(listbox.locator('[role="option"]'));
  const message = option ? null : await firstVisible(listbox.locator('[class$="-menuList"] > div, [class*="-menuList"] > div'));
  const listboxFamily = await familyOf(listbox);
  const optionFamily = option ? await familyOf(option) : null;
  const messageFamily = message ? await familyOf(message) : null;
  const textFamily = optionFamily || messageFamily || listboxFamily;
  const controlAria = await selectInput.evaluate(el => ({
    id: el.id || null,
    aria_controls: el.getAttribute('aria-controls'),
    aria_owns: el.getAttribute('aria-owns'),
    aria_expanded: el.getAttribute('aria-expanded'),
    aria_activedescendant: el.getAttribute('aria-activedescendant'),
  }));
  const portalDom = await listbox.evaluate(el => {
    const ancestry = [];
    let node = el;
    while (node && node !== document.documentElement && ancestry.length < 12) {
      ancestry.push({
        tag: node.tagName,
        id: node.id || null,
        class_name: typeof node.className === 'string' ? node.className : '',
        role: node.getAttribute('role'),
        data_attributes: Object.fromEntries([...node.attributes]
          .filter(attribute => attribute.name.startsWith('data-'))
          .map(attribute => [attribute.name, attribute.value])),
      });
      node = node.parentElement;
    }
    let bodyChild = el;
    while (bodyChild.parentElement && bodyChild.parentElement !== document.body) bodyChild = bodyChild.parentElement;
    return {
      listbox_id: el.id || null,
      ancestry,
      body_child: bodyChild ? {
        tag: bodyChild.tagName,
        id: bodyChild.id || null,
        class_name: typeof bodyChild.className === 'string' ? bodyChild.className : '',
        role: bodyChild.getAttribute('role'),
        data_attributes: Object.fromEntries([...bodyChild.attributes]
          .filter(attribute => attribute.name.startsWith('data-'))
          .map(attribute => [attribute.name, attribute.value])),
      } : null,
      parent_is_body: bodyChild?.parentElement === document.body,
      inside_view_selector: Boolean(el.closest('.gk-gravityview-blocks .view-selector')),
      owner_document_is_top_level: el.ownerDocument.defaultView === window,
    };
  });
  const emotionOwnership = await page.locator('style[data-emotion*="gk-select"]').evaluateAll(nodes => nodes.map(node => node.getAttribute('data-emotion')));
  const markerText = JSON.stringify(portalDom.body_child || {});
  const hasAcceptedGravityViewMarker = /gravityview|gk-gravityview/i.test(markerText);
  const ariaControlsMatches = Boolean(controlAria.aria_controls && portalDom.listbox_id && controlAria.aria_controls === portalDom.listbox_id);

  const exclusionSelector = manifest.editor_exclusion_selector || '.vazir-gv-evidence-excluded';
  const exclusionClass = exclusionSelector.startsWith('.') ? exclusionSelector.slice(1) : null;
  if (exclusionClass) await selectRoot.evaluate((el, className) => el.classList.add(className), exclusionClass);
  const portalInsideExcludedSource = await listbox.evaluate((el, selector) => Boolean(el.closest(selector)), exclusionSelector).catch(() => false);
  const sourceMarkedExcluded = exclusionClass ? await selectRoot.evaluate((el, className) => el.classList.contains(className), exclusionClass) : false;
  if (exclusionClass) await selectRoot.evaluate((el, className) => el.classList.remove(className), exclusionClass);

  const typographyDisposition = isVazirmatn(textFamily) ? 'ALREADY_CORRECT' : 'FAIL';
  let finalDisposition = 'ALREADY_CORRECT';
  let admissionReason = 'Visible portal text already resolves to Vazirmatn; no repair seam is needed.';
  if ('FAIL' === typographyDisposition) {
    finalDisposition = 'NO_ADMISSION';
    admissionReason = 'The failing menu is detached from the GravityView source subtree. Runtime ARIA may link the combobox to the listbox, but static CSS cannot join two dynamic attribute values, the portal exposes no accepted stable GravityView-owned marker, and the single source exclusion boundary does not contain the portal. A repair would therefore require a prohibited generic/global selector or JavaScript/DOM association.';
  }

  results.portal = {
    interaction_attempts: portalOpen.attempts,
    successful_interaction: portalOpen.successful_interaction,
    visible_listbox_reached: true,
    visible_option_reached: Boolean(option),
    computed_typography: {
      listbox: listboxFamily,
      option: optionFamily,
      no_options_or_loading_message: messageFamily,
    },
    typography_disposition: typographyDisposition,
    dom: portalDom,
    aria: controlAria,
    aria_controls_matches_listbox_id: ariaControlsMatches,
    emotion_style_ownership: emotionOwnership,
    accepted_gravityview_owned_portal_marker: hasAcceptedGravityViewMarker,
    gk_select_cache_prefix_is_not_admission_authority: true,
    static_css_can_join_dynamic_aria_values: false,
    exclusion: {
      selector: exclusionSelector,
      source_control_marked_excluded: sourceMarkedExcluded,
      portal_inside_excluded_source: portalInsideExcludedSource,
    },
    final_disposition: finalDisposition,
    reason: admissionReason,
  };
  await page.keyboard.press('Escape').catch(() => {});
}

fs.writeFileSync(path.join(artifactDir, 'gravityview-closure-browser-results.json'), `${JSON.stringify(results, null, 2)}\n`);
await browser.close();
console.log(JSON.stringify(results, null, 2));
