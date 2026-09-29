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

const results = {
  status: 'PASS',
  product_status: 'QUALIFICATION_EXECUTED',
  profile: 'gravityperks',
  gravity_perks_version: manifest.gravity_perks_version,
  gravity_forms_version: manifest.gravity_forms_version,
  dispatch_contract: dispatch,
  scenarios: {},
  browser: {},
};

const vazirRequestRe = /\/assets\/fonts\/vazirmatn-\d+\.woff2(?:\?|$)/i;
const classifyFamily = family => /Vazirmatn/i.test(family || '') ? 'PASS' : 'FAIL';

async function inspectNode(page, selector, label, { required = true, state = 'visible' } = {}) {
  const locator = page.locator(selector).first();
  if (await locator.count() === 0) {
    if (required) throw new Error(`${label} selector did not render: ${selector}`);
    return { status: 'NOT_PROVEN', rendered: false, selector, reason: `${label} selector did not render.` };
  }
  try {
    await locator.waitFor({ state, timeout: 15000 });
  } catch {
    if (required) throw new Error(`${label} did not reach ${state}: ${selector}`);
    return { status: 'NOT_PROVEN', rendered: false, selector, reason: `${label} did not reach ${state}.` };
  }
  const detail = await locator.evaluate(el => {
    const style = getComputedStyle(el);
    const rect = el.getBoundingClientRect();
    return {
      tag: el.tagName,
      id: el.id || '',
      className: typeof el.className === 'string' ? el.className : '',
      text: (el.textContent || el.getAttribute('value') || el.getAttribute('aria-label') || '').trim().replace(/\s+/g, ' ').slice(0, 240),
      computed_family: style.fontFamily,
      display: style.display,
      visibility: style.visibility,
      width: rect.width,
      height: rect.height,
    };
  });
  return { status: classifyFamily(detail.computed_family), rendered: true, selector, ...detail };
}

async function inspectControl(page, selector, label, required = true) {
  const evidence = await inspectNode(page, selector, label, { required });
  if (!evidence.rendered) return evidence;
  evidence.control = await page.locator(selector).first().evaluate(el => ({
    type: el.getAttribute('type') || el.tagName.toLowerCase(),
    name: el.getAttribute('name') || '',
    value: 'value' in el ? String(el.value) : '',
    checked: 'checked' in el ? Boolean(el.checked) : null,
  }));
  return evidence;
}

async function scanProtectedFamilies(page) {
  return page.evaluate(() => {
    const matches = [];
    const seen = new Set();
    for (const el of Array.from(document.querySelectorAll('*')).slice(0, 2500)) {
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

async function captureRoute(context, url, bodyCheck) {
  const page = await context.newPage();
  const requests = [];
  const responses = [];
  const failures = [];
  page.on('request', request => requests.push(request.url()));
  page.on('response', response => responses.push({ url: response.url(), status: response.status() }));
  page.on('requestfailed', request => failures.push({ url: request.url(), error: request.failure()?.errorText || 'unknown' }));
  const navigation = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await bodyCheck(page);
  await page.waitForTimeout(2500);
  try {
    await page.evaluate(async () => {
      if (document.fonts?.ready) await Promise.race([document.fonts.ready, new Promise(resolve => setTimeout(resolve, 1500))]);
    });
  } catch {}
  const resources = await page.evaluate(() => ({
    stylesheets: Array.from(document.querySelectorAll('link[rel~="stylesheet"]')).map(link => ({
      id: link.id || '',
      href: link.href || '',
      media: link.media || '',
      style_loader_probe: link.getAttribute('data-vazir-gp-style-loader-probe'),
    })),
    inline_styles: Array.from(document.querySelectorAll('style')).map(style => ({ id: style.id || '', text: (style.textContent || '').slice(0, 1000) })),
    body_class: document.body?.className || '',
    document_title: document.title || '',
    root_inline_seam_probe: getComputedStyle(document.documentElement).getPropertyValue('--vazir-gravityperks-gwp-admin-seam-probe').trim(),
    root_exclusion_count: getComputedStyle(document.documentElement).getPropertyValue('--vazir-gravityperks-exclusion-count').trim(),
  }));
  const network = {
    googleapis_requests: requests.filter(url => /fonts\.googleapis\.com/i.test(url)),
    gstatic_requests: requests.filter(url => /fonts\.gstatic\.com/i.test(url)),
    vazirmatn_requests: requests.filter(url => vazirRequestRe.test(url)),
    google_responses: responses.filter(item => /fonts\.(?:googleapis|gstatic)\.com/i.test(item.url)),
    google_failures: failures.filter(item => /fonts\.(?:googleapis|gstatic)\.com/i.test(item.url)),
  };
  return { page, resources, network, navigation: { final_url: page.url(), status: navigation ? navigation.status() : null } };
}

function renderedTypographyPass(nodes, ignored = []) {
  return Object.entries(nodes)
    .filter(([key, item]) => !ignored.includes(key) && item.rendered)
    .every(([, item]) => item.status === 'PASS');
}

function resourceSummary(resources) {
  return {
    gwp_admin_printed: resources.stylesheets.some(item => item.id === 'gwp-admin-css' || /gravityperks.*admin/i.test(item.href)),
    gwp_admin_style_loader_filter_observed: resources.stylesheets.some(item => item.style_loader_probe === 'gwp-admin'),
    gwp_admin_inline_css_seam_observed: resources.root_inline_seam_probe === '1',
    existing_exclusion_authority_visible_at_seam: resources.root_exclusion_count === '1',
    vazir_stylesheets_present: resources.stylesheets.filter(item => /vazir-font/i.test(`${item.id} ${item.href}`)),
    vazir_inline_styles_present: resources.inline_styles.filter(item => /vazir-font/i.test(item.id)).map(item => item.id),
  };
}

const browser = await chromium.launch();
results.browser.version = browser.version();
const context = await browser.newContext();
const authPage = await context.newPage();
await login(authPage, baseUrl, adminUser, adminPassword);
await authPage.close();

try {
  // Supported ordinary wp-admin surface.
  {
    const captured = await captureRoute(context, manifest.normal_admin_url, async page => {
      await page.locator('body.wp-admin').waitFor({ state: 'visible', timeout: 15000 });
      await page.locator('text=GP Vazir Evidence').first().waitFor({ state: 'visible', timeout: 15000 });
    });
    const { page, resources, network, navigation } = captured;
    const nodes = {
      body: await inspectNode(page, 'body.wp-admin', 'Gravity Perks normal wp-admin body'),
      heading: await inspectNode(page, '.wrap h1, .wrap h2, h1.wp-heading-inline', 'Gravity Perks normal admin heading', { required: false }),
      perk_listing: await inspectNode(page, 'text=GP Vazir Evidence', 'real test Perk listing text'),
      action_link: await inspectNode(page, 'a:has-text("Settings"), .actions a, .button', 'Gravity Perks normal admin action link', { required: false }),
    };
    results.scenarios.normal_admin = {
      execution_status: 'PASS', context: 'ordinary_wp_admin', navigation, nodes, resources, network,
      protected_families: await scanProtectedFamilies(page), ...resourceSummary(resources),
      disposition: renderedTypographyPass(nodes) ? 'PASS' : 'FAIL',
    };
    await page.close();
  }

  // Exact 2.3.16 still generates a legacy Documentation URL, but exact source
  // dispatch sends any non-empty Perks `view` request to load_perk_settings().
  // Browser evidence must therefore prove the alias, not mislabel it as docs.
  {
    const captured = await captureRoute(context, manifest.documentation_url, async page => {
      await page.locator('body.perk-iframe.wp-core-ui').waitFor({ state: 'visible', timeout: 15000 });
      const title = page.locator('.page-title').first();
      await title.waitFor({ state: 'visible', timeout: 15000 });
      const titleText = (await title.textContent() || '').trim();
      if (!/GP Vazir Evidence Settings/i.test(titleText)) {
        throw new Error(`Legacy Documentation URL did not dispatch to the exact-source-predicted Settings document. page-title=${JSON.stringify(titleText)}`);
      }
      await page.locator('label:has-text("Vazir Evidence Text")').waitFor({ state: 'visible', timeout: 15000 });
    });
    const { page, resources, network, navigation } = captured;
    const nodes = {
      page_title: await inspectNode(page, '.page-title', 'aliased Settings page title'),
      text_label: await inspectNode(page, 'label:has-text("Vazir Evidence Text")', 'aliased Settings text label'),
      text_control: await inspectControl(page, 'input[type="text"]', 'aliased Settings text control'),
    };
    results.scenarios.documentation = {
      execution_status: 'PASS',
      context: 'legacy_documentation_url_dispatch',
      requested_view: 'documentation',
      source_expected_handler: dispatch.view_requests_dispatch_to,
      observed_document: 'standalone_settings',
      documentation_availability: 'NOT_REACHABLE_AS_DOCUMENTATION',
      disposition: 'NOT_PROVEN',
      navigation, nodes, resources, network,
      protected_families: await scanProtectedFamilies(page), ...resourceSummary(resources),
      typography_not_attributed_to_documentation: true,
    };
    await page.screenshot({ path: path.join(artifactDir, 'gravityperks-documentation-route-alias.png'), fullPage: true });
    await page.close();
  }

  // Supported standalone Settings document.
  {
    const captured = await captureRoute(context, manifest.settings_url, async page => {
      await page.locator('body.perk-iframe.wp-core-ui').waitFor({ state: 'visible', timeout: 15000 });
      const title = page.locator('.page-title').first();
      await title.waitFor({ state: 'visible', timeout: 15000 });
      const titleText = (await title.textContent() || '').trim();
      if (!/GP Vazir Evidence Settings/i.test(titleText)) throw new Error(`Expected Settings document title; got ${JSON.stringify(titleText)}`);
      await page.locator('label:has-text("Vazir Evidence Text")').waitFor({ state: 'visible', timeout: 15000 });
    });
    const { page, resources, network, navigation } = captured;
    const nodes = {
      page_title: await inspectNode(page, '.page-title', 'Settings page title'),
      text_label: await inspectNode(page, 'label:has-text("Vazir Evidence Text")', 'Settings text label'),
      text_description: await inspectNode(page, 'text=Vazir evidence text description', 'Settings text description'),
      text_control: await inspectControl(page, 'input[type="text"]', 'Settings text control'),
      select_control: await inspectControl(page, 'select', 'Settings select control'),
      checkbox_control: await inspectControl(page, 'input[type="checkbox"]', 'Settings checkbox control'),
      save_button: await inspectControl(page, '#gwp_save_settings', 'Settings save button'),
    };

    await page.locator('input[type="text"]').first().fill('Saved by Vazir evidence');
    const select = page.locator('select').first();
    if (await select.count()) {
      const optionCount = await select.locator('option').count();
      if (optionCount > 0) await select.selectOption({ index: Math.min(1, optionCount - 1) });
    }
    const checkbox = page.locator('input[type="checkbox"]').first();
    if (await checkbox.count()) await checkbox.check();
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 15000 }).catch(() => null),
      page.locator('#gwp_save_settings').click(),
    ]);
    await page.locator('body.perk-iframe.wp-core-ui').waitFor({ state: 'visible', timeout: 15000 });
    const notice = await inspectNode(page, '.updated, .notice, .error', 'Settings save notice', { required: false });

    results.scenarios.settings = {
      execution_status: 'PASS', context: 'standalone_settings', navigation, nodes, save_notice: notice, resources, network,
      protected_families: await scanProtectedFamilies(page), ...resourceSummary(resources),
      disposition: renderedTypographyPass(nodes, ['checkbox_control']) ? 'PASS' : 'FAIL',
    };
    await page.screenshot({ path: path.join(artifactDir, 'gravityperks-settings.png'), fullPage: true });
    await page.close();
  }

  const normal = results.scenarios.normal_admin;
  const docs = results.scenarios.documentation;
  const settings = results.scenarios.settings;
  results.repair_seam_evidence = {
    documentation_boundary_reachable: false,
    documentation_route_runtime_alias_confirmed: docs.observed_document === 'standalone_settings',
    documentation_source_only_google_fonts_risk: true,
    documentation_runtime_google_fonts_claim: 'NOT_PROVEN',
    documentation_runtime_vazirmatn_claim: 'NOT_PROVEN',
    settings_gwp_admin_style_loader_pipeline_observed: settings.gwp_admin_style_loader_filter_observed,
    settings_gwp_admin_inline_css_survives_boundary: settings.gwp_admin_inline_css_seam_observed,
    settings_existing_exclusion_option_visible_at_seam: settings.existing_exclusion_authority_visible_at_seam,
    settings_vazirmatn_request_observed: settings.network.vazirmatn_requests.length > 0,
    settings_googleapis_request_attempted: settings.network.googleapis_requests.length > 0,
    settings_gstatic_request_attempted: settings.network.gstatic_requests.length > 0,
    normal_admin_vazirmatn_request_observed: normal.network.vazirmatn_requests.length > 0,
  };

  const supportedGap = [normal, settings].some(scenario => scenario.disposition === 'FAIL');
  results.product_status = supportedGap ? 'QUALIFIED_GAP / NO_REPAIR_YET' : 'QUALIFIED_NO_GAP / NO_REPAIR_NEEDED';
} catch (error) {
  results.status = 'FAIL';
  results.product_status = 'QUALIFICATION_INCOMPLETE';
  results.error = error instanceof Error ? error.stack || error.message : String(error);
} finally {
  fs.writeFileSync(path.join(artifactDir, 'browser-results.json'), `${JSON.stringify(results, null, 2)}\n`);
  await context.close();
  await browser.close();
}

console.log(JSON.stringify(results, null, 2));
if (results.status !== 'PASS') process.exit(1);
