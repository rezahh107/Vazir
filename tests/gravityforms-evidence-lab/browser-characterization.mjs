import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { observeVazirmatnFontRequests } from '../product-evidence-lab/core/browser-helpers.mjs';

const baseUrl = process.env.VAZIR_GF_BASE_URL || 'http://127.0.0.1:8080';
const artifactDir = process.env.VAZIR_GF_ARTIFACT_DIR;
const adminUser = process.env.VAZIR_GF_ADMIN_USER || 'vazir_lab_admin';
const adminPassword = process.env.VAZIR_GF_ADMIN_PASSWORD || 'vazir-lab-admin-password';

if (!artifactDir) throw new Error('VAZIR_GF_ARTIFACT_DIR is required');
const manifest = JSON.parse(fs.readFileSync(path.join(artifactDir, 'fixture-manifest.json'), 'utf8'));
const results = { status: 'PASS', scenarios: {}, browser: {} };
let failed = false;

class NotProvenError extends Error {}

const record = async (name, fn) => {
  try {
    const detail = await fn();
    results.scenarios[name] = { status: 'PASS', ...(detail || {}) };
  } catch (error) {
    if (error instanceof NotProvenError) {
      results.scenarios[name] = { status: 'NOT_PROVEN', reason: error.message };
      return;
    }
    failed = true;
    results.status = 'FAIL';
    results.scenarios[name] = { status: 'FAIL', reason: error instanceof Error ? error.message : String(error) };
  }
};

const browser = await chromium.launch();
results.browser.version = browser.version();
const context = await browser.newContext();
const page = await context.newPage();

const familyOf = locator => locator.evaluate(el => getComputedStyle(el).fontFamily);
const expectVazirmatn = async (locator, label) => {
  await locator.waitFor({ state: 'visible', timeout: 30000 });
  const family = await familyOf(locator);
  assert.match(family, /Vazirmatn/i, `${label} should resolve to Vazirmatn; got ${family}`);
  return family;
};
const expectVazirmatnAttached = async (locator, label) => {
  await locator.waitFor({ state: 'attached', timeout: 30000 });
  const family = await familyOf(locator);
  assert.match(family, /Vazirmatn/i, `${label} should resolve to Vazirmatn; got ${family}`);
  return family;
};
const expectNotVazirmatn = async (locator, label, expected) => {
  await locator.waitFor({ state: 'visible', timeout: 30000 });
  const family = await familyOf(locator);
  assert.doesNotMatch(family, /Vazirmatn/i, `${label} must not resolve to Vazirmatn; got ${family}`);
  if (expected) assert.match(family, expected, `${label} should retain ${expected}; got ${family}`);
  return family;
};
const pseudoFamily = (locator, pseudo = '::before') => locator.evaluate((el, p) => getComputedStyle(el, p).fontFamily, pseudo);

const login = async () => {
  await page.goto(`${baseUrl}/wp-login.php`, { waitUntil: 'domcontentloaded' });
  await page.fill('#user_login', adminUser);
  await page.fill('#user_pass', adminPassword);
  await Promise.all([
    page.waitForURL(/wp-admin\//, { timeout: 30000 }),
    page.click('#wp-submit'),
  ]);
};

const collectAdminComponentCandidates = async routeLabel => page.evaluate(label => {
  const visible = el => {
    const style = getComputedStyle(el);
    const rect = el.getBoundingClientRect();
    return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
  };
  const textBearing = el => {
    if (['INPUT', 'TEXTAREA', 'SELECT', 'BUTTON'].includes(el.tagName)) return true;
    return Boolean((el.textContent || '').trim());
  };
  const rows = [];
  const seen = new Set();
  const visitRules = (rules, sheetHref) => {
    for (const rule of Array.from(rules || [])) {
      if (rule instanceof CSSStyleRule) {
        const declaredFamily = rule.style.getPropertyValue('font-family');
        if (!declaredFamily || !rule.selectorText) continue;
        let elements = [];
        try {
          elements = Array.from(document.querySelectorAll(rule.selectorText));
        } catch {
          continue;
        }
        for (const el of elements) {
          if (!visible(el) || !textBearing(el)) continue;
          const key = `${rule.selectorText}::${el.tagName}::${el.id}::${el.className}`;
          if (seen.has(key)) continue;
          seen.add(key);
          const className = typeof el.className === 'string' ? el.className : '';
          const role = el.getAttribute('role') || '';
          const text = (el.textContent || el.getAttribute('aria-label') || el.getAttribute('placeholder') || '').trim().replace(/\s+/g, ' ').slice(0, 180);
          const lowerClass = className.toLowerCase();
          const lowerRole = role.toLowerCase();
          const kinds = [];
          if (/^H[1-6]$/.test(el.tagName) || lowerRole === 'heading' || /heading|title/.test(lowerClass)) kinds.push('heading');
          if (el.tagName === 'LABEL' || /label|description|help|text/.test(lowerClass)) kinds.push('label');
          if (el.tagName === 'SELECT' || lowerRole === 'combobox' || /dropdown|select/.test(lowerClass)) kinds.push('dropdown');
          if (el.closest('table') || /table|pagination|paging|pager|list/.test(lowerClass)) kinds.push('table');
          if (el.tagName === 'BUTTON' || (el.tagName === 'A' && /gform-button/.test(lowerClass)) || /button/.test(lowerClass)) kinds.push('button');
          if (['dialog', 'tooltip'].includes(lowerRole) || /tooltip|dialog|flyout|calendar|popover|modal/.test(lowerClass)) kinds.push('overlay');
          rows.push({
            route: label,
            tag: el.tagName,
            id: el.id || '',
            className,
            role,
            text,
            selector: rule.selectorText,
            declaredFamily: declaredFamily.trim(),
            computedFamily: getComputedStyle(el).fontFamily,
            stylesheet: sheetHref,
            kinds,
          });
          if (rows.length >= 250) return;
        }
      } else if (rule.cssRules) {
        visitRules(rule.cssRules, sheetHref);
        if (rows.length >= 250) return;
      }
    }
  };

  for (const sheet of Array.from(document.styleSheets)) {
    const href = sheet.href || '';
    if (!/admin-components(?:\.min)?\.css/i.test(href)) continue;
    let rules;
    try {
      rules = sheet.cssRules;
    } catch {
      continue;
    }
    visitRules(rules, href);
    if (rows.length >= 250) break;
  }
  return rows;
}, routeLabel);

const firstAdminCandidate = (inventory, kind) => inventory.find(item => item.kinds.includes(kind));
const assertAdminCandidate = (inventory, kind, label) => {
  const candidate = firstAdminCandidate(inventory, kind);
  if (!candidate) throw new NotProvenError(`${label} was not deterministically reachable from real admin-components.min.css-backed markup.`);
  assert.match(candidate.computedFamily, /Vazirmatn/i, `${label} should resolve to Vazirmatn; got ${candidate.computedFamily}; selector=${candidate.selector}; route=${candidate.route}`);
  return candidate;
};

await record('frontend_orbital_theme_framework', async () => {
  await page.goto(manifest.orbital_url, { waitUntil: 'networkidle' });
  const wrapper = page.locator(`#gform_wrapper_${manifest.orbital_form_id}`);
  await wrapper.waitFor({ state: 'visible' });
  await expectVazirmatn(wrapper, 'Orbital wrapper');
  await expectVazirmatn(page.locator(`#field_${manifest.orbital_form_id}_1 .gfield_label`), 'Orbital label');
  await expectVazirmatn(page.locator(`#field_${manifest.orbital_form_id}_1 .gfield_description`), 'Orbital description');
  await expectVazirmatn(page.locator(`#input_${manifest.orbital_form_id}_1`), 'Orbital text input');
  await expectVazirmatn(page.locator(`#input_${manifest.orbital_form_id}_2`), 'Orbital textarea');
  await expectVazirmatn(page.locator(`#input_${manifest.orbital_form_id}_3`), 'Orbital select');
  await expectVazirmatn(page.locator(`#gform_submit_button_${manifest.orbital_form_id}`), 'Orbital submit button');

  const className = await wrapper.getAttribute('class');
  assert.match(className || '', /gform-theme--framework/, 'Orbital wrapper must use Theme Framework');
  assert.match(className || '', /gform-theme--orbital/, 'Orbital wrapper must use Orbital theme');
  const frameworkFamily = await wrapper.evaluate(el => getComputedStyle(el).getPropertyValue('--gf-font-family-base'));
  const frameworkDiagnostics = await wrapper.evaluate(el => {
    const matchedRules = [];
    for (const sheet of Array.from(document.styleSheets)) {
      let rules;
      try { rules = Array.from(sheet.cssRules || []); } catch { continue; }
      for (const rule of rules) {
        if (!(rule instanceof CSSStyleRule)) continue;
        const value = rule.style.getPropertyValue('--gf-font-family-base');
        if (!value) continue;
        let matches = false;
        try { matches = el.matches(rule.selectorText); } catch {}
        matchedRules.push({ selector: rule.selectorText, value: value.trim(), matches, stylesheet: sheet.href || (sheet.ownerNode && sheet.ownerNode.id) || 'inline' });
      }
    }
    const exclusionSelectors = ['.dashicons', '.menu-icon', '.menu-image', '[class^="dashicons-"]', '[class*=" dashicons-"]', '[class^="fa-"]', '[class*=" fa-"]', '.material-icons', '.vf-gf-excluded'];
    const matchingExcludedDescendants = exclusionSelectors.filter(selector => {
      try { return el.querySelector(selector) !== null; } catch { return false; }
    });
    const vazirStyle = document.getElementById('vazir-font-gravity-forms-inline-css');
    return {
      matchedRules,
      matchingExcludedDescendants,
      vazirStylePresent: Boolean(vazirStyle),
      vazirStyleHasFrameworkProperty: Boolean(vazirStyle && vazirStyle.textContent && vazirStyle.textContent.includes('--gf-font-family-base')),
    };
  });
  assert.match(frameworkFamily, /Vazirmatn/i, `--gf-font-family-base should contain Vazirmatn; got ${frameworkFamily}; diagnostics=${JSON.stringify(frameworkDiagnostics)}`);
  return { theme_framework_custom_property: frameworkFamily, framework_diagnostics: frameworkDiagnostics };
});

await record('frontend_exclusions_and_icons', async () => {
  await page.goto(manifest.dynamic_url, { waitUntil: 'networkidle' });
  await expectVazirmatn(page.locator(`#input_${manifest.dynamic_form_id}_1`), 'dynamic text input');
  await expectVazirmatn(page.getByRole('button', { name: 'بعدی' }), 'dynamic next button');
  await expectNotVazirmatn(page.locator('#vf-gf-excluded-text'), 'excluded Gravity Forms descendant', /monospace/i);
  const icon = page.locator(`#field_${manifest.dynamic_form_id}_5 .dashicons`).first();
  await icon.waitFor({ state: 'attached', timeout: 30000 });
  const iconFamily = await pseudoFamily(icon);
  assert.doesNotMatch(iconFamily, /Vazirmatn/i, `Gravity Forms password icon must not inherit Vazirmatn; got ${iconFamily}`);
  assert.match(iconFamily, /gform-icons-orbital/i, `Gravity Forms password icon must retain the Orbital icon family; got ${iconFamily}`);
  return { password_icon_family: iconFamily };
});

await record('conditional_logic_transition', async () => {
  await page.goto(manifest.dynamic_url, { waitUntil: 'networkidle' });
  const conditional = page.locator(`#field_${manifest.dynamic_form_id}_3`);
  await conditional.waitFor({ state: 'attached' });
  assert.equal(await conditional.isVisible(), false, 'conditional field should start hidden');
  await page.getByLabel('نمایش بده').check();
  await conditional.waitFor({ state: 'visible', timeout: 10000 });
  await expectVazirmatn(page.locator(`#input_${manifest.dynamic_form_id}_3`), 'conditional field after show transition');
  await page.getByLabel('پنهان بمان').check();
  await conditional.waitFor({ state: 'hidden', timeout: 10000 });
});

await record('ajax_validation_and_multipage_rerenders', async () => {
  await page.goto(manifest.dynamic_url, { waitUntil: 'networkidle' });
  const submissionMethod = page.locator(`[data-js="gform_submission_method_${manifest.dynamic_form_id}"]`);
  await submissionMethod.waitFor({ state: 'attached', timeout: 30000 });
  assert.equal(await submissionMethod.inputValue(), 'iframe', 'Gravity Forms 3.1.1.1 ajax="true" should use the real iframe submission method');
  const ajaxFrame = page.locator(`#gform_ajax_frame_${manifest.dynamic_form_id}`);
  await ajaxFrame.waitFor({ state: 'attached', timeout: 30000 });
  await page.getByRole('button', { name: 'بعدی' }).click();
  await page.locator('.gform_validation_errors').waitFor({ state: 'visible', timeout: 30000 });
  await expectVazirmatn(page.locator(`#input_${manifest.dynamic_form_id}_1`), 'required field after validation rerender');
  await expectNotVazirmatn(page.locator('#vf-gf-excluded-text'), 'excluded descendant after validation rerender', /monospace/i);
  await page.locator(`#input_${manifest.dynamic_form_id}_1`).fill('رضا Runtime');
  await page.getByRole('button', { name: 'بعدی' }).click();
  const pageTwo = page.locator(`#input_${manifest.dynamic_form_id}_7`);
  await pageTwo.waitFor({ state: 'visible', timeout: 30000 });
  await expectVazirmatn(pageTwo, 'page-two input after AJAX transition');
  const previousButton = page.locator(`#gform_wrapper_${manifest.dynamic_form_id} .gform_previous_button`).first();
  await expectVazirmatn(previousButton, 'previous button after AJAX transition');
  await previousButton.click();
  await page.locator(`#input_${manifest.dynamic_form_id}_1`).waitFor({ state: 'visible', timeout: 30000 });
  await expectVazirmatn(page.locator(`#input_${manifest.dynamic_form_id}_1`), 'page-one input after previous transition');
  return { submission_method: 'iframe', ajax_frame_present: true };
});

await record('legacy_markup_frontend', async () => {
  await page.goto(manifest.legacy_url, { waitUntil: 'networkidle' });
  const wrapper = page.locator(`#gform_wrapper_${manifest.legacy_form_id}`);
  await wrapper.waitFor({ state: 'visible' });
  const className = await wrapper.getAttribute('class');
  assert.match(className || '', /gform_legacy_markup_wrapper/, 'legacy fixture must use supported Legacy Markup wrapper');
  assert.doesNotMatch(className || '', /gform-theme--framework/, 'legacy fixture must not masquerade as Theme Framework markup');
  await expectVazirmatn(wrapper, 'Legacy wrapper');
  await expectVazirmatn(page.locator(`#input_${manifest.legacy_form_id}_1`), 'Legacy text input');
  await expectVazirmatn(page.locator(`#input_${manifest.legacy_form_id}_2`), 'Legacy select');
});

await record('legacy_steps_multipage_typography', async () => {
  await page.goto(manifest.legacy_steps_url, { waitUntil: 'networkidle' });
  const wrapper = page.locator(`#gform_wrapper_${manifest.legacy_steps_form_id}`);
  await wrapper.waitFor({ state: 'visible', timeout: 30000 });
  assert.match((await wrapper.getAttribute('class')) || '', /gform_legacy_markup_wrapper/, 'legacy steps fixture must use real Legacy Markup');
  const stepNumber = page.locator(`#gform_wrapper_${manifest.legacy_steps_form_id} .gf_step_number`).first();
  const stepLabel = page.locator(`#gform_wrapper_${manifest.legacy_steps_form_id} .gf_step_label`).first();
  await expectVazirmatn(stepNumber, 'Legacy step number');
  await expectVazirmatn(stepLabel, 'Legacy step label');
  await expectVazirmatn(page.locator(`#input_${manifest.legacy_steps_form_id}_1`), 'Legacy steps ordinary field');
  const next = page.locator(`#gform_wrapper_${manifest.legacy_steps_form_id} .gform_next_button`).first();
  await expectVazirmatn(next, 'Legacy steps next control');
  await next.click();
  await page.locator(`#input_${manifest.legacy_steps_form_id}_3`).waitFor({ state: 'visible', timeout: 30000 });
  const previous = page.locator(`#gform_wrapper_${manifest.legacy_steps_form_id} .gform_previous_button`).first();
  await expectVazirmatn(previous, 'Legacy steps previous control');
  return { step_number: await familyOf(stepNumber), step_label: await familyOf(stepLabel) };
});

await record('legacy_percentage_multipage_typography', async () => {
  await page.goto(manifest.legacy_percentage_url, { waitUntil: 'networkidle' });
  const wrapper = page.locator(`#gform_wrapper_${manifest.legacy_percentage_form_id}`);
  await wrapper.waitFor({ state: 'visible', timeout: 30000 });
  assert.match((await wrapper.getAttribute('class')) || '', /gform_legacy_markup_wrapper/, 'legacy percentage fixture must use real Legacy Markup');
  const percentage = page.locator(`#gform_wrapper_${manifest.legacy_percentage_form_id} .gf_progressbar_percentage`).first();
  const progressTitle = page.locator(`#gform_wrapper_${manifest.legacy_percentage_form_id} .gf_progressbar_title`).first();
  await expectVazirmatn(percentage, 'Legacy progress percentage');
  await expectVazirmatn(progressTitle, 'Legacy progress title');
  await expectVazirmatn(page.locator(`#input_${manifest.legacy_percentage_form_id}_1`), 'Legacy percentage ordinary field');
  await expectVazirmatn(page.locator(`#gform_wrapper_${manifest.legacy_percentage_form_id} .gform_next_button`).first(), 'Legacy percentage next control');
  return { progress_percentage: await familyOf(percentage), progress_title: await familyOf(progressTitle) };
});

await record('font_request_deduplication_single_render', async () => observeVazirmatnFontRequests(browser, {
  url: manifest.orbital_url,
  label: 'Gravity Forms single render',
  waitForSurface: async requestPage => {
    await requestPage.locator(`#gform_wrapper_${manifest.orbital_form_id}`).waitFor({ state: 'visible', timeout: 30000 });
  },
}));

await login();

let adminInventory = [];
for (const [routeLabel, url] of [
  ['forms-list', manifest.forms_admin_url],
  ['entries-list', manifest.entries_admin_url],
  ['form-editor', manifest.form_editor_url],
]) {
  await page.goto(url, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(750);
  const candidates = await collectAdminComponentCandidates(routeLabel);
  adminInventory.push(...candidates);
}
fs.writeFileSync(path.join(artifactDir, 'admin-components-inventory.json'), `${JSON.stringify(adminInventory, null, 2)}\n`);

await record('gravity_forms_admin_page_heading', async () => {
  await page.goto(manifest.forms_admin_url, { waitUntil: 'domcontentloaded' });
  const heading = page.locator('.gform-admin h1:visible, h1.wp-heading-inline:visible, .wrap h1:visible').first();
  if (await heading.count() === 0) throw new NotProvenError('No visible Gravity Forms admin page heading was deterministically rendered.');
  return {
    text: (await heading.innerText()).trim(),
    font_family: await expectVazirmatn(heading, 'Gravity Forms admin page heading'),
  };
});
await record('gravity_forms_admin_heading_component', async () => ({ candidate: assertAdminCandidate(adminInventory, 'heading', 'Gravity Forms admin heading/title component') }));
await record('gravity_forms_admin_label_component', async () => ({ candidate: assertAdminCandidate(adminInventory, 'label', 'Gravity Forms admin label/text component') }));
await record('gravity_forms_admin_dropdown_component', async () => ({ candidate: assertAdminCandidate(adminInventory, 'dropdown', 'Gravity Forms admin dropdown/select component') }));
await record('gravity_forms_admin_table_or_pagination_component', async () => ({ candidate: assertAdminCandidate(adminInventory, 'table', 'Gravity Forms admin table/list/pagination component') }));
await record('gravity_forms_admin_button_component', async () => {
  const candidate = adminInventory.find(item => item.selector === '.gform-admin .gform-button' && item.tag === 'A');
  if (!candidate) throw new NotProvenError('A real text-bearing Gravity Forms gform-button link was not deterministically reachable.');
  assert.match(candidate.computedFamily, /Vazirmatn/i, `Gravity Forms admin gform-button link should resolve to Vazirmatn; got ${candidate.computedFamily}; route=${candidate.route}`);
  return { candidate };
});
await record('gravity_forms_admin_overlay_component', async () => ({ candidate: assertAdminCandidate(adminInventory, 'overlay', 'Gravity Forms admin overlay component') }));

await record('gravity_forms_preview_form_content', async () => {
  await page.goto(manifest.preview_url, { waitUntil: 'networkidle' });
  const wrapper = page.locator(`#gform_wrapper_${manifest.dynamic_form_id}`);
  await wrapper.waitFor({ state: 'visible', timeout: 30000 });
  await expectVazirmatn(page.locator(`#input_${manifest.dynamic_form_id}_1`), 'Preview input');
  assert.equal(await page.locator('#vazir-font-gravity-forms-inline-css').count(), 1, 'Preview must print the registered Vazir Gravity Forms style handle');
  await expectNotVazirmatn(page.locator('#vf-gf-excluded-text'), 'Preview excluded descendant', /monospace/i);
  const icon = page.locator(`#field_${manifest.dynamic_form_id}_5 .dashicons`).first();
  await icon.waitFor({ state: 'attached', timeout: 30000 });
  const iconFamily = await pseudoFamily(icon);
  assert.doesNotMatch(iconFamily, /Vazirmatn/i, `Preview password icon must not inherit Vazirmatn; got ${iconFamily}`);
  assert.match(iconFamily, /gform-icons-orbital/i, `Preview password icon must retain the Orbital icon family; got ${iconFamily}`);
  return { password_icon_family: iconFamily };
});

await record('gravity_forms_preview_header_chrome', async () => {
  await page.goto(manifest.preview_url, { waitUntil: 'networkidle' });
  const header = page.locator('#preview_hdr');
  return { font_family: await expectVazirmatn(header, 'Gravity Forms Preview header chrome') };
});

await record('gravity_forms_preview_note_chrome', async () => {
  await page.goto(manifest.preview_url, { waitUntil: 'networkidle' });
  const note = page.locator('#preview_note');
  if (await note.count() === 0) throw new NotProvenError('Gravity Forms Preview #preview_note is not rendered in this exact runtime route.');
  return { font_family: await expectVazirmatnAttached(note, 'Gravity Forms Preview note chrome') };
});

await record('gravity_forms_preview_helper_toggle_chrome', async () => {
  await page.goto(manifest.preview_url, { waitUntil: 'networkidle' });
  const candidates = page.locator('#preview_hdr label, #preview_hdr button, #preview_hdr select, #preview_hdr input, [id*="preview"][class*="toggle"], [class*="preview"][class*="toggle"]');
  const count = await candidates.count();
  for (let index = 0; index < count; index++) {
    const candidate = candidates.nth(index);
    if (await candidate.isVisible()) {
      return { font_family: await expectVazirmatn(candidate, 'Gravity Forms Preview helper/toggle chrome') };
    }
  }
  throw new NotProvenError('No visible Preview helper/toggle text was deterministically rendered.');
});

await record('form_editor_and_no_conflict_mode', async () => {
  await page.goto(manifest.form_editor_url, { waitUntil: 'domcontentloaded' });
  await page.locator('.gform_editor').waitFor({ state: 'visible', timeout: 30000 });
  await expectVazirmatn(page.locator('.gform_editor .gfield_label').first(), 'Form Editor field label');
  assert.equal(await page.locator('#vazir-font-gravity-forms-inline-css').count(), 1, 'GF style handle must survive No Conflict Mode');
  assert.equal(await page.locator('#vazir-font-admin-runtime-inline-css').count(), 1, 'Vazir admin handle must survive No Conflict Mode');
  const gfIcon = page.locator('.gform-icon').first();
  await gfIcon.waitFor({ state: 'attached', timeout: 30000 });
  const gfIconFamily = await familyOf(gfIcon);
  assert.match(gfIconFamily, /gform-icons-admin/i, `Gravity Forms admin icon must retain gform-icons-admin; got ${gfIconFamily}`);
  return { gravity_forms_admin_icon_family: gfIconFamily };
});

await record('admin_additional_icon_family_inventory', async () => {
  await page.goto(manifest.form_editor_url, { waitUntil: 'domcontentloaded' });
  const families = await page.evaluate(() => {
    const found = [];
    const selectors = ['.gform-icon', '[class*="gform-icon"]', '[class*="gravity-components-icon"]', '[class*="dashicons"]'];
    for (const selector of selectors) {
      for (const el of Array.from(document.querySelectorAll(selector))) {
        const base = getComputedStyle(el).fontFamily;
        const before = getComputedStyle(el, '::before').fontFamily;
        for (const [surface, family] of [['element', base], ['before', before]]) {
          if (/gform-icons|gravity-components-icons|dashicons/i.test(family)) {
            found.push({ selector, surface, family, className: typeof el.className === 'string' ? el.className : '' });
          }
        }
      }
    }
    return found.filter((item, index, array) => array.findIndex(other => other.family === item.family && other.surface === item.surface) === index);
  });
  assert.ok(families.length > 0, 'At least one real Gravity Forms/WordPress admin icon family should be rendered.');
  for (const item of families) assert.doesNotMatch(item.family, /Vazirmatn/i, `Protected admin icon family must remain host-owned; got ${item.family}`);
  return { families };
});

await record('normal_wp_admin_under_gf_noconflict_setting', async () => {
  await page.goto(`${baseUrl}/wp-admin/`, { waitUntil: 'networkidle' });
  await expectVazirmatn(page.locator('body.wp-admin'), 'normal wp-admin body');
  const dashicon = page.locator('#adminmenu .wp-menu-image.dashicons-before').first();
  await dashicon.waitFor({ state: 'attached', timeout: 30000 });
  const family = await pseudoFamily(dashicon);
  assert.match(family, /dashicons/i, `normal wp-admin Dashicons must remain protected; got ${family}`);
});

fs.writeFileSync(path.join(artifactDir, 'browser-results.json'), `${JSON.stringify(results, null, 2)}\n`);

if (failed) {
  try { await page.screenshot({ path: path.join(artifactDir, 'browser-failure.png'), fullPage: true }); } catch {}
}
await context.close();
await browser.close();

if (failed) {
  console.error(JSON.stringify(results, null, 2));
  process.exit(1);
}
console.log('LICENSED GRAVITY FORMS BROWSER CHARACTERIZATION PASSED');
console.log(JSON.stringify(results, null, 2));
