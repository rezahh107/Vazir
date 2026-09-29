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

const results = {
  status: 'PASS',
  product_status: 'QUALIFICATION_EXECUTED',
  profile: 'gravityperks',
  gravity_perks_version: manifest.gravity_perks_version,
  gravity_forms_version: manifest.gravity_forms_version,
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
      text: (el.textContent || el.getAttribute('value') || el.getAttribute('aria-label') || '').trim().replace(/\s+/g, ' ').slice(0, 200),
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
  const locator = page.locator(selector).first();
  evidence.control = await locator.evaluate(el => ({
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
        matches.push({
          surface: pseudo || 'element',
          family,
          tag: el.tagName,
          id: el.id || '',
          className: typeof el.className === 'string' ? el.className : '',
        });
      }
    }
    return matches;
  });
}

async function captureRoute(context, url, label, bodyCheck) {
  const page = await context.newPage();
  const requests = [];
  const responses = [];
  const failures = [];
  page.on('request', request => requests.push(request.url()));
  page.on('response', response => responses.push({ url: response.url(), status: response.status() }));
  page.on('requestfailed', request => failures.push({ url: request.url(), error: request.failure()?.errorText || 'unknown' }));
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await bodyCheck(page);
  await page.waitForTimeout(2500);
  try {
    await page.evaluate(async () => {
      if (document.fonts?.ready) {
        await Promise.race([document.fonts.ready, new Promise(resolve => setTimeout(resolve, 1500))]);
      }
    });
  } catch {}
  const resources = await page.evaluate(() => ({
    stylesheets: Array.from(document.querySelectorAll('link[rel~="stylesheet"]')).map(link => ({
      id: link.id || '',
      href: link.href || '',
      media: link.media || '',
      style_loader_probe: link.getAttribute('data-vazir-gp-style-loader-probe'),
    })),
    inline_styles: Array.from(document.querySelectorAll('style')).map(style => ({
      id: style.id || '',
      text: (style.textContent || '').slice(0, 1000),
    })),
    body_class: document.body?.className || '',
    document_title: document.title || '',
    root_inline_seam_probe: getComputedStyle(document.documentElement).getPropertyValue('--vazir-gravityperks-gwp-admin-seam-probe').trim(),
    root_exclusion_count: getComputedStyle(document.documentElement).getPropertyValue('--vazir-gravityperks-exclusion-count').trim(),
  }));
  const network = {
    googleapis_requests: requests.filter(u => /fonts\.googleapis\.com/i.test(u)),
    gstatic_requests: requests.filter(u => /fonts\.gstatic\.com/i.test(u)),
    vazirmatn_requests: requests.filter(u => vazirRequestRe.test(u)),
    google_responses: responses.filter(item => /fonts\.(?:googleapis|gstatic)\.com/i.test(item.url)),
    google_failures: failures.filter(item => /fonts\.(?:googleapis|gstatic)\.com/i.test(item.url)),
  };
  return { page, resources, network, label };
}

const browser = await chromium.launch();
results.browser.version = browser.version();
const context = await browser.newContext();
const authPage = await context.newPage();
await login(authPage, baseUrl, adminUser, adminPassword);
await authPage.close();

try {
  {
    const captured = await captureRoute(context, manifest.normal_admin_url, 'normal_admin', async page => {
      await page.locator('body.wp-admin').waitFor({ state: 'visible', timeout: 15000 });
    });
    const { page, resources, network } = captured;
    const body = await inspectNode(page, 'body.wp-admin', 'Gravity Perks normal wp-admin body');
    const heading = await inspectNode(page, '.wrap h1, .wrap h2, h1.wp-heading-inline', 'Gravity Perks normal admin heading', { required: false });
    const perkListing = await inspectNode(page, 'text=GP Vazir Evidence', 'real test Perk listing/card text', { required: false });
    const actionLink = await inspectNode(page, 'a:has-text("Documentation"), a:has-text("Settings"), .actions a, .button', 'Gravity Perks normal admin action link', { required: false });
    const icons = await scanProtectedFamilies(page);
    results.scenarios.normal_admin = {
      execution_status: 'PASS',
      context: 'ordinary_wp_admin',
      body,
      heading,
      perk_listing: perkListing,
      action_link: actionLink,
      resources,
      network,
      protected_families: icons,
      disposition: [body, heading].filter(item => item.rendered).every(item => item.status === 'PASS') ? 'PASS' : 'FAIL',
    };
    await page.close();
  }

  {
    const captured = await captureRoute(context, manifest.documentation_url, 'documentation', async page => {
      await page.locator('body.perk-iframe').waitFor({ state: 'visible', timeout: 15000 });
      await page.locator('#vazir-gp-doc-paragraph').waitFor({ state: 'visible', timeout: 15000 });
    });
    const { page, resources, network } = captured;
    const nodes = {
      body: await inspectNode(page, 'body.perk-iframe', 'Documentation body'),
      page_title: await inspectNode(page, '.page-title', 'Documentation page title'),
      content_h2: await inspectNode(page, '.content h2', 'Documentation H2'),
      paragraph: await inspectNode(page, '#vazir-gp-doc-paragraph', 'Documentation paragraph'),
      description: await inspectNode(page, '.content li span.description', 'Documentation list description'),
      footer_link: await inspectNode(page, '.content-footer a', 'Documentation host footer link'),
      excluded_probe: await inspectNode(page, '#vazir-gp-doc-excluded', 'Documentation excluded probe'),
    };
    const icons = await scanProtectedFamilies(page);
    const googleLink = resources.stylesheets.find(item => /fonts\.googleapis\.com/i.test(item.href)) || null;
    const gwpStyle = resources.stylesheets.find(item => item.id === 'gwp-admin-css' || /gravityperks.*admin/i.test(item.href)) || null;
    const vazirStyles = resources.stylesheets.filter(item => /vazir-font/i.test(`${item.id} ${item.href}`));
    const vazirInline = resources.inline_styles.filter(item => /vazir-font/i.test(item.id));
    results.scenarios.documentation = {
      execution_status: 'PASS',
      context: 'standalone_documentation',
      nodes,
      resources,
      network,
      protected_families: icons,
      gwp_admin_printed: Boolean(gwpStyle),
      google_fonts_link_present: Boolean(googleLink),
      google_fonts_link: googleLink,
      google_fonts_style_loader_filter_observed: Boolean(googleLink?.style_loader_probe),
      gwp_admin_style_loader_filter_observed: resources.stylesheets.some(item => item.style_loader_probe === 'gwp-admin'),
      gwp_admin_inline_css_seam_observed: resources.root_inline_seam_probe === '1',
      existing_exclusion_authority_visible_at_seam: resources.root_exclusion_count === '1',
      vazir_stylesheets_present: vazirStyles,
      vazir_inline_styles_present: vazirInline.map(item => item.id),
      disposition: Object.entries(nodes)
        .filter(([key, item]) => key !== 'excluded_probe' && item.rendered)
        .every(([, item]) => item.status === 'PASS') ? 'PASS' : 'FAIL',
    };
    await page.screenshot({ path: path.join(artifactDir, 'gravityperks-documentation.png'), fullPage: true });
    await page.close();
  }

  {
    const captured = await captureRoute(context, manifest.settings_url, 'settings', async page => {
      await page.locator('body.perk-iframe.wp-core-ui').waitFor({ state: 'visible', timeout: 15000 });
      await page.locator('label:has-text("Vazir Evidence Text")').waitFor({ state: 'visible', timeout: 15000 });
    });
    const { page, resources, network } = captured;
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
    await page.locator('#gwp_save_settings').click();
    await page.waitForLoadState('domcontentloaded');
    await page.locator('body.perk-iframe.wp-core-ui').waitFor({ state: 'visible', timeout: 15000 });
    const notice = await inspectNode(page, '.updated, .notice, .error', 'Settings save notice', { required: false });
    const icons = await scanProtectedFamilies(page);
    const vazirStyles = resources.stylesheets.filter(item => /vazir-font/i.test(`${item.id} ${item.href}`));
    const vazirInline = resources.inline_styles.filter(item => /vazir-font/i.test(item.id));
    results.scenarios.settings = {
      execution_status: 'PASS',
      context: 'standalone_settings',
      nodes,
      save_notice: notice,
      resources,
      network,
      protected_families: icons,
      gwp_admin_printed: resources.stylesheets.some(item => item.id === 'gwp-admin-css' || /gravityperks.*admin/i.test(item.href)),
      wp_admin_printed: resources.stylesheets.some(item => item.id === 'wp-admin-css'),
      gwp_admin_style_loader_filter_observed: resources.stylesheets.some(item => item.style_loader_probe === 'gwp-admin'),
      gwp_admin_inline_css_seam_observed: resources.root_inline_seam_probe === '1',
      existing_exclusion_authority_visible_at_seam: resources.root_exclusion_count === '1',
      vazir_stylesheets_present: vazirStyles,
      vazir_inline_styles_present: vazirInline.map(item => item.id),
      disposition: Object.entries(nodes)
        .filter(([key]) => key !== 'checkbox_control')
        .every(([, item]) => item.status === 'PASS') ? 'PASS' : 'FAIL',
    };
    await page.screenshot({ path: path.join(artifactDir, 'gravityperks-settings.png'), fullPage: true });
    await page.close();
  }

  const normal = results.scenarios.normal_admin;
  const docs = results.scenarios.documentation;
  const settings = results.scenarios.settings;
  results.repair_seam_evidence = {
    gwp_admin_inline_css_survives_documentation_boundary: docs.gwp_admin_inline_css_seam_observed,
    gwp_admin_inline_css_survives_settings_boundary: settings.gwp_admin_inline_css_seam_observed,
    existing_exclusion_option_readable_at_documentation_seam: docs.existing_exclusion_authority_visible_at_seam,
    existing_exclusion_option_readable_at_settings_seam: settings.existing_exclusion_authority_visible_at_seam,
    gwp_admin_uses_style_loader_pipeline_in_documentation: docs.gwp_admin_style_loader_filter_observed,
    gwp_admin_uses_style_loader_pipeline_in_settings: settings.gwp_admin_style_loader_filter_observed,
    literal_google_fonts_link_bypasses_style_loader_tag: docs.google_fonts_link_present && !docs.google_fonts_style_loader_filter_observed,
    documentation_googleapis_request_attempted: docs.network.googleapis_requests.length > 0,
    documentation_gstatic_request_attempted: docs.network.gstatic_requests.length > 0,
    documentation_vazirmatn_request_observed: docs.network.vazirmatn_requests.length > 0,
    settings_vazirmatn_request_observed: settings.network.vazirmatn_requests.length > 0,
    normal_admin_vazirmatn_request_observed: normal.network.vazirmatn_requests.length > 0,
  };

  const anyTypographyGap = [docs, settings].some(scenario => scenario.disposition === 'FAIL');
  results.product_status = anyTypographyGap ? 'QUALIFIED_GAP / NO_REPAIR_YET' : 'QUALIFIED_NO_GAP / NO_REPAIR_NEEDED';
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
