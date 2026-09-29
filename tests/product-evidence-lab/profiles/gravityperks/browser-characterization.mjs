import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import { login } from '../../core/browser-helpers.mjs';

const baseUrl = process.env.VAZIR_LAB_BASE_URL || 'http://127.0.0.1:8080';
const artifactDir = process.env.VAZIR_LAB_ARTIFACT_DIR;
const adminUser = process.env.VAZIR_LAB_ADMIN_USER || 'vazir_lab_admin';
const adminPassword = process.env.VAZIR_LAB_ADMIN_PASSWORD || 'vazir-lab-admin-password';
if (!artifactDir) throw new Error('VAZIR_LAB_ARTIFACT_DIR is required');

const manifest = JSON.parse(fs.readFileSync(path.join(artifactDir, 'fixture-manifest.json'), 'utf8'));
const dispatch = JSON.parse(fs.readFileSync(path.join(artifactDir, 'dispatch-contract.json'), 'utf8'));
if (dispatch.documentation_route_reachable !== false || dispatch.view_requests_dispatch_to !== 'GWPerksPage::load_perk_settings') {
  throw new Error('Exact Gravity Perks dispatch contract changed; browser qualification model must be revisited.');
}

const expectedWeights = (manifest.configured_font_weights || []).map(String).sort();
const vazirRequestRe = /\/assets\/fonts\/vazirmatn-(\d+)\.woff2(?:\?|$)/i;
const results = {
  status: 'PASS',
  product_status: 'QUALIFICATION_EXECUTED',
  profile: 'gravityperks',
  gravity_perks_version: manifest.gravity_perks_version,
  gravity_forms_version: manifest.gravity_forms_version,
  exact_version_evidence_only: true,
  dispatch_contract: dispatch,
  scenarios: {},
  browser: {},
};

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

function isVazirmatn(family) {
  return /Vazirmatn/i.test(family || '');
}

async function inspectNode(page, selector, label, { required = true } = {}) {
  const locator = page.locator(selector).first();
  if (await locator.count() === 0) {
    if (required) throw new Error(`${label} selector did not render: ${selector}`);
    return { status: 'NOT_PROVEN', rendered: false, selector, reason: `${label} did not render.` };
  }
  await locator.waitFor({ state: 'visible', timeout: 15000 });
  const detail = await locator.evaluate(el => {
    const style = getComputedStyle(el);
    return {
      tag: el.tagName,
      id: el.id || '',
      className: typeof el.className === 'string' ? el.className : '',
      text: (el.textContent || el.getAttribute('value') || '').trim().replace(/\s+/g, ' ').slice(0, 240),
      computed_family: style.fontFamily,
    };
  });
  return { status: isVazirmatn(detail.computed_family) ? 'PASS' : 'FAIL', rendered: true, selector, ...detail };
}

async function waitForFonts(page) {
  await page.waitForTimeout(1200);
  await page.evaluate(async () => {
    if (document.fonts?.ready) await Promise.race([document.fonts.ready, new Promise(resolve => setTimeout(resolve, 1800))]);
  }).catch(() => {});
}

async function scanProtectedFamilies(page) {
  return page.evaluate(() => {
    const matches = [];
    const seen = new Set();
    for (const el of Array.from(document.querySelectorAll('*')).slice(0, 3000)) {
      for (const pseudo of [null, '::before', '::after']) {
        let family = '';
        try { family = getComputedStyle(el, pseudo).fontFamily || ''; } catch { continue; }
        if (!/(?:dashicons|gffontawesome|fontawesome)/i.test(family)) continue;
        const key = `${pseudo || 'element'}::${family}`;
        if (seen.has(key)) continue;
        seen.add(key);
        matches.push({ surface: pseudo || 'element', family, tag: el.tagName, id: el.id || '', className: typeof el.className === 'string' ? el.className : '' });
      }
    }
    return matches;
  });
}

async function captureRoute(context, url, ready) {
  const page = await context.newPage();
  const requests = [];
  const responses = [];
  const failures = [];
  page.on('request', request => requests.push(request.url()));
  page.on('response', response => responses.push({ url: response.url(), status: response.status() }));
  page.on('requestfailed', request => failures.push({ url: request.url(), error: request.failure()?.errorText || 'unknown' }));
  const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await ready(page);
  await waitForFonts(page);
  return {
    page,
    requests,
    navigation: { final_url: page.url(), status: response ? response.status() : null },
    network: {
      vazirmatn_requests: requests.filter(url => vazirRequestRe.test(url)),
      googleapis_requests: requests.filter(url => /fonts\.googleapis\.com/i.test(url)),
      gstatic_requests: requests.filter(url => /fonts\.gstatic\.com/i.test(url)),
      google_responses: responses.filter(item => /fonts\.(?:googleapis|gstatic)\.com/i.test(item.url)),
      google_failures: failures.filter(item => /fonts\.(?:googleapis|gstatic)\.com/i.test(item.url)),
    },
  };
}

function assertNoGoogle(network, label) {
  assert(network.googleapis_requests.length === 0, `${label} attempted fonts.googleapis.com.`);
  assert(network.gstatic_requests.length === 0, `${label} attempted fonts.gstatic.com.`);
}

const browser = await chromium.launch();
results.browser.version = browser.version();
const context = await browser.newContext();
const authPage = await context.newPage();
await login(authPage, baseUrl, adminUser, adminPassword);
await authPage.close();

try {
  // Ordinary wp-admin was already healthy before this repair. Prove it stays
  // healthy and does not receive the standalone Settings correction.
  {
    const captured = await captureRoute(context, manifest.normal_admin_url, async page => {
      await page.locator('body.wp-admin').waitFor({ state: 'visible', timeout: 15000 });
      await page.locator('text=GP Vazir Evidence').first().waitFor({ state: 'visible', timeout: 15000 });
    });
    const { page, network, navigation } = captured;
    const nodes = {
      body: await inspectNode(page, 'body.wp-admin', 'normal admin body'),
      heading: await inspectNode(page, '.wrap h1, .wrap h2, h1.wp-heading-inline', 'normal admin heading', { required: false }),
      perk_listing: await inspectNode(page, 'text=GP Vazir Evidence', 'real Perk listing'),
      action_link: await inspectNode(page, 'a:has-text("Settings"), .actions a, .button', 'normal admin action link', { required: false }),
    };
    for (const [name, node] of Object.entries(nodes)) {
      if (node.rendered) assert(node.status === 'PASS', `Normal admin ${name} lost Vazirmatn.`);
    }
    const standaloneRepairStyles = await page.locator('style').evaluateAll(styles => styles
      .filter(style => /body\.perk-iframe\s+\.perk-settings/.test(style.textContent || ''))
      .map(style => style.id || '(no-id)'));
    assert(standaloneRepairStyles.length === 0, 'Standalone Gravity Perks repair CSS leaked into ordinary wp-admin.');
    assertNoGoogle(network, 'Normal Gravity Perks admin');
    results.scenarios.normal_admin = {
      disposition: 'PASS', navigation, nodes, network,
      standalone_repair_styles: standaloneRepairStyles,
      protected_families: await scanProtectedFamilies(page),
    };
    await page.close();
  }

  // Exact 2.3.16 legacy Documentation URL remains an alias to Settings.
  {
    const captured = await captureRoute(context, manifest.documentation_url, async page => {
      await page.locator('body.perk-iframe.wp-core-ui').waitFor({ state: 'visible', timeout: 15000 });
      await page.locator('.page-title').first().waitFor({ state: 'visible', timeout: 15000 });
    });
    const { page, network, navigation } = captured;
    const title = ((await page.locator('.page-title').first().textContent()) || '').trim();
    assert(/GP Vazir Evidence Settings/i.test(title), `Documentation URL no longer aliases to Settings: ${JSON.stringify(title)}`);
    assert(await page.locator('label:has-text("Vazir Evidence Text")').count() > 0, 'Documentation alias did not render Settings controls.');
    assertNoGoogle(network, 'Documentation alias');
    results.scenarios.documentation = {
      execution_status: 'PASS',
      requested_view: 'documentation',
      observed_document: 'standalone_settings',
      documentation_availability: 'NOT_REACHABLE_AS_DOCUMENTATION',
      disposition: 'NOT_PROVEN',
      typography_not_attributed_to_documentation: true,
      navigation, network,
    };
    await page.screenshot({ path: path.join(artifactDir, 'gravityperks-documentation-route-alias.png'), fullPage: true });
    await page.close();
  }

  // Authentic standalone Settings document: typography, exclusion, resource
  // delivery, host ownership, protected glyphs, and real save interaction.
  {
    const captured = await captureRoute(context, manifest.settings_url, async page => {
      await page.locator('body.perk-iframe.wp-core-ui').waitFor({ state: 'visible', timeout: 15000 });
      await page.locator('.page-title').first().waitFor({ state: 'visible', timeout: 15000 });
      await page.locator('label:has-text("Vazir Evidence Text")').waitFor({ state: 'visible', timeout: 15000 });
    });
    const { page, requests, network, navigation } = captured;
    const nodes = {
      page_title: await inspectNode(page, '.page-title', 'Settings page title'),
      text_label: await inspectNode(page, 'label:has-text("Vazir Evidence Text")', 'Settings label'),
      text_description: await inspectNode(page, 'p.description:has-text("Vazir evidence text description")', 'Settings description'),
      text_control: await inspectNode(page, 'input[type="text"]', 'Settings text input'),
      select_control: await inspectNode(page, 'select', 'Settings select'),
      save_button: await inspectNode(page, '#gwp_save_settings', 'Settings save button'),
      textarea: await inspectNode(page, 'textarea', 'Settings textarea', { required: false }),
      checkbox: await inspectNode(page, 'input[type="checkbox"]', 'Settings checkbox', { required: false }),
    };
    for (const requiredName of ['page_title', 'text_label', 'text_description', 'text_control', 'select_control', 'save_button']) {
      assert(nodes[requiredName].status === 'PASS', `Standalone Settings ${requiredName} did not resolve to Vazirmatn.`);
    }
    if (nodes.textarea.rendered) assert(nodes.textarea.status === 'PASS', 'Rendered Settings textarea did not resolve to Vazirmatn.');

    const excluded = await inspectNode(page, '.vazir-gp-evidence-excluded', 'excluded evidence description');
    assert(!isVazirmatn(excluded.computed_family), 'Configured exclusion still received the Perks Vazirmatn correction.');
    assert(nodes.text_description.status === 'PASS', 'Non-excluded sibling did not retain Vazirmatn beside exclusion.');

    const resources = await page.evaluate(() => ({
      host_stylesheet: Boolean(document.querySelector('#gwp-admin-css')),
      host_stylesheet_href: document.querySelector('#gwp-admin-css')?.href || '',
      host_inline_css: document.querySelector('#gwp-admin-inline-css')?.textContent || '',
      style_loader_probe: document.querySelector('#gwp-admin-css')?.getAttribute('data-vazir-gp-style-loader-probe') || '',
      extra_vazir_links: Array.from(document.querySelectorAll('link[rel~="stylesheet"]')).filter(link => /vazir-font/i.test(`${link.id} ${link.href}`)).map(link => ({ id: link.id, href: link.href })),
      seam_probe: getComputedStyle(document.documentElement).getPropertyValue('--vazir-gravityperks-print-styles-array-probe').trim(),
      todo_probe: getComputedStyle(document.documentElement).getPropertyValue('--vazir-gravityperks-gwp-admin-in-todo').trim(),
      registered_probe: getComputedStyle(document.documentElement).getPropertyValue('--vazir-gravityperks-gwp-admin-registered').trim(),
    }));
    assert(resources.host_stylesheet, 'gwp-admin-css is no longer the printed host stylesheet.');
    assert(resources.style_loader_probe === 'gwp-admin', 'gwp-admin did not traverse the supported WordPress style-loader pipeline.');
    assert(resources.seam_probe === '1' && resources.todo_probe === '1' && resources.registered_probe === '1', 'print_styles_array seam sentinel did not prove selected+registered gwp-admin capability.');
    assert(/body\.perk-iframe\s+\.perk-settings/.test(resources.host_inline_css), 'Production repair CSS was not attached to gwp-admin-inline-css.');
    assert(resources.extra_vazir_links.length === 0, 'Standalone Settings introduced an unnecessary standalone Vazir stylesheet link.');

    const cssWeights = Array.from(resources.host_inline_css.matchAll(/font-weight:\s*(300|400|500|700|900)\s*;/g), match => match[1]).sort();
    assert(JSON.stringify(cssWeights) === JSON.stringify(expectedWeights), `@font-face weights ${JSON.stringify(cssWeights)} do not match configured Loader weights ${JSON.stringify(expectedWeights)}.`);

    const vazirRequests = requests.filter(url => vazirRequestRe.test(url));
    const uniqueVazirRequests = [...new Set(vazirRequests)];
    assert(vazirRequests.length > 0, 'No bundled Vazirmatn WOFF2 request occurred inside standalone Settings.');
    assert(vazirRequests.length === uniqueVazirRequests.length, `Duplicate Vazirmatn font URL delivery observed: ${JSON.stringify(vazirRequests)}`);
    assertNoGoogle(network, 'Standalone Settings');

    const protectedFamilies = await scanProtectedFamilies(page);
    const protectedEvidence = protectedFamilies.length > 0
      ? { disposition: 'PASS', rendered: true, families: protectedFamilies }
      : { disposition: 'NOT_EXERCISED', rendered: false, reason: 'Exact fixture rendered no Dashicons/GFFontAwesome/FontAwesome computed family.' };

    const textInput = page.locator('input[type="text"]').first();
    await textInput.fill('Saved by Vazir repair evidence');
    const select = page.locator('select').first();
    const optionCount = await select.locator('option').count();
    if (optionCount > 1) await select.selectOption({ index: 1 });
    const selectedValue = await select.inputValue();
    const checkbox = page.locator('input[type="checkbox"]').first();
    if (await checkbox.count()) await checkbox.check();
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 15000 }).catch(() => null),
      page.locator('#gwp_save_settings').click(),
    ]);
    await page.locator('body.perk-iframe.wp-core-ui').waitFor({ state: 'visible', timeout: 15000 });
    const savedText = await page.locator('input[type="text"]').first().inputValue();
    const savedSelect = await page.locator('select').first().inputValue();
    const savedCheckbox = await page.locator('input[type="checkbox"]').first().isChecked();
    const noticeLocator = page.locator('.updated, .notice, .error').first();
    const noticeRendered = await noticeLocator.count() > 0;
    const noticeText = noticeRendered ? ((await noticeLocator.textContent()) || '').trim().replace(/\s+/g, ' ') : '';
    assert(savedText === 'Saved by Vazir repair evidence', 'Text value did not persist through real Gravity Perks Settings save.');
    assert(savedSelect === selectedValue, 'Select value did not persist through real Gravity Perks Settings save.');
    assert(savedCheckbox, 'Checkbox state did not persist through real Gravity Perks Settings save.');
    assert(noticeRendered, 'Gravity Perks Settings save did not produce its resulting notice/page lifecycle.');

    results.scenarios.settings = {
      disposition: 'PASS', navigation, nodes, excluded,
      resources,
      configured_font_weights: expectedWeights,
      css_font_face_weights: cssWeights,
      network: { ...network, unique_vazirmatn_requests: uniqueVazirRequests },
      protected_icon_glyph_evidence: protectedEvidence,
      save_interaction: {
        disposition: 'PASS',
        text_value_persisted: true,
        select_value_persisted: true,
        checkbox_state_persisted: true,
        notice_rendered: noticeRendered,
        notice_text: noticeText,
      },
    };
    await page.screenshot({ path: path.join(artifactDir, 'gravityperks-settings.png'), fullPage: true });
    await page.close();
  }

  results.repair_seam_evidence = {
    filter: 'print_styles_array',
    exact_settings_seam_runtime_proven: true,
    gwp_admin_selected_and_registered: true,
    inline_css_attached_to_host_handle: true,
    handle_list_mutation: false,
    extra_stylesheet_link: false,
  };
  results.product_status = 'ADMITTED_VERIFIED / REPAIR_PASS';
} catch (error) {
  results.status = 'FAIL';
  results.product_status = 'REPAIR_NOT_VERIFIED';
  results.error = error instanceof Error ? error.stack || error.message : String(error);
} finally {
  fs.writeFileSync(path.join(artifactDir, 'browser-results.json'), `${JSON.stringify(results, null, 2)}\n`);
  await context.close();
  await browser.close();
}

console.log(JSON.stringify(results, null, 2));
if (results.status !== 'PASS') process.exit(1);
