import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';

const baseUrl = process.env.VAZIR_GF_BASE_URL || 'http://127.0.0.1:8080';
const artifactDir = process.env.VAZIR_GF_ARTIFACT_DIR;
const adminUser = process.env.VAZIR_GF_ADMIN_USER || 'vazir_lab_admin';
const adminPassword = process.env.VAZIR_GF_ADMIN_PASSWORD || 'vazir-lab-admin-password';
const repositorySha = process.env.VAZIR_GF_REPOSITORY_SHA || process.env.GITHUB_SHA || null;
const admissionManifestPath = path.resolve('tests/gravityforms-evidence-lab/admitted-selector-evidence.json');

if (!artifactDir) throw new Error('VAZIR_GF_ARTIFACT_DIR is required');
const fixtureManifest = JSON.parse(fs.readFileSync(path.join(artifactDir, 'fixture-manifest.json'), 'utf8'));
const admissionManifest = fs.existsSync(admissionManifestPath)
  ? JSON.parse(fs.readFileSync(admissionManifestPath, 'utf8'))
  : null;

const candidates = [
  { selector: '.gform_legacy_markup_wrapper .gf_step_number', label: 'Legacy step number', context: 'legacy_steps', url: fixtureManifest.legacy_steps_url, state: 'visible' },
  { selector: '.gform_legacy_markup_wrapper .gf_step_label', label: 'Legacy step label', context: 'legacy_steps', url: fixtureManifest.legacy_steps_url, state: 'visible' },
  { selector: '.gform_legacy_markup_wrapper .gf_progressbar_percentage', label: 'Legacy progress percentage', context: 'legacy_percentage', url: fixtureManifest.legacy_percentage_url, state: 'visible' },
  { selector: '.gform_legacy_markup_wrapper .gf_progressbar_title', label: 'Legacy progress title', context: 'legacy_percentage', url: fixtureManifest.legacy_percentage_url, state: 'visible' },
  { selector: '.gform-admin .gform-dropdown', label: 'Gravity Forms admin dropdown', context: 'admin' },
  { selector: '.gform-admin .gform-dropdown__control-text', label: 'Gravity Forms admin dropdown control text', context: 'admin' },
  { selector: '.gform-admin .gform-dropdown__group-text', label: 'Gravity Forms admin dropdown group text', context: 'admin' },
  { selector: '.gform-admin .gform-button', label: 'Gravity Forms admin text-bearing gform-button', context: 'admin' },
  { selector: '#preview_hdr', label: 'Gravity Forms Preview header', context: 'preview', url: fixtureManifest.preview_url, state: 'visible' },
  { selector: '#preview_note', label: 'Gravity Forms Preview note', context: 'preview', url: fixtureManifest.preview_url, state: 'attached' },
];

const adminRoutes = [
  ['forms-list', fixtureManifest.forms_admin_url],
  ['entries-list', fixtureManifest.entries_admin_url],
  ['form-editor', fixtureManifest.form_editor_url],
];

const browser = await chromium.launch();
const context = await browser.newContext();
const page = await context.newPage();

const login = async () => {
  await page.goto(`${baseUrl}/wp-login.php`, { waitUntil: 'domcontentloaded' });
  await page.fill('#user_login', adminUser);
  await page.fill('#user_pass', adminPassword);
  await Promise.all([
    page.waitForURL(/wp-admin\//, { timeout: 30000 }),
    page.click('#wp-submit'),
  ]);
};

const inspectLocator = async locator => locator.evaluate(el => {
  const style = getComputedStyle(el);
  const rect = el.getBoundingClientRect();
  return {
    tag: el.tagName,
    id: el.id || '',
    className: typeof el.className === 'string' ? el.className : '',
    text: (el.textContent || el.getAttribute('aria-label') || el.getAttribute('placeholder') || '').trim().replace(/\s+/g, ' ').slice(0, 180),
    computedFamily: style.fontFamily,
    display: style.display,
    visibility: style.visibility,
    width: rect.width,
    height: rect.height,
  };
});

const isTextBearing = detail => {
  if (['INPUT', 'TEXTAREA', 'SELECT', 'BUTTON'].includes(detail.tag)) return true;
  return detail.text.length > 0;
};

const classify = detail => ({
  rendered: true,
  computed_family: detail.computedFamily,
  disposition: /Vazirmatn/i.test(detail.computedFamily) ? 'ALREADY_VAZIRMATN' : 'REPRODUCED',
  node: {
    tag: detail.tag,
    id: detail.id,
    className: detail.className,
    text: detail.text,
    display: detail.display,
    visibility: detail.visibility,
    width: detail.width,
    height: detail.height,
  },
});

const notProven = reason => ({ rendered: false, computed_family: null, disposition: 'NOT_PROVEN', reason });

const measureExact = async candidate => {
  if (candidate.context === 'admin') {
    for (const [route, url] of adminRoutes) {
      await page.goto(url, { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(750);
      const locators = page.locator(candidate.selector);
      const count = await locators.count();
      for (let index = 0; index < count; index++) {
        const locator = locators.nth(index);
        const detail = await inspectLocator(locator);
        const visible = detail.display !== 'none' && detail.visibility !== 'hidden' && detail.width > 0 && detail.height > 0;
        if (!visible || !isTextBearing(detail)) continue;
        return { ...classify(detail), route };
      }
    }
    return notProven(`${candidate.label} was not deterministically rendered as a visible text-bearing node on the qualified admin routes.`);
  }

  await page.goto(candidate.url, { waitUntil: 'networkidle' });
  const locator = page.locator(candidate.selector).first();
  if (await locator.count() === 0) return notProven(`${candidate.label} selector did not match a rendered node.`);
  try {
    await locator.waitFor({ state: candidate.state, timeout: 30000 });
  } catch {
    return notProven(`${candidate.label} did not reach required ${candidate.state} state.`);
  }
  return classify(await inspectLocator(locator));
};

const results = {
  schema: 1,
  repository_sha: repositorySha,
  gravity_forms_version: '3.1.1.1',
  mode: admissionManifest ? 'FINAL_VERIFY' : 'PRE_REPAIR_MEASURE',
  browser: { version: browser.version() },
  candidates: {},
  status: 'PASS',
};

const measureCandidates = async selected => {
  for (const candidate of selected) {
    try {
      results.candidates[candidate.selector] = await measureExact(candidate);
    } catch (error) {
      results.candidates[candidate.selector] = notProven(`${candidate.label} measurement failed without authorizing repair: ${error instanceof Error ? error.message : String(error)}`);
    }
  }
};

await measureCandidates(candidates.filter(item => item.context.startsWith('legacy_')));

// Preview is a real authenticated Gravity Forms route in this fixture, so its
// chrome must be measured only after login. Admin targets share the same
// authenticated browser context. Each candidate still receives its own record.
await login();
await measureCandidates(candidates.filter(item => item.context === 'preview'));
await measureCandidates(candidates.filter(item => item.context === 'admin'));

const violations = [];
if (admissionManifest) {
  const admitted = new Set(admissionManifest.admitted_selectors || []);
  for (const selector of admitted) {
    const record = results.candidates[selector];
    if (!record) {
      violations.push(`${selector}: missing final rendered-node evidence record`);
      continue;
    }
    if (!record.rendered) {
      violations.push(`${selector}: final target is NOT_PROVEN (${record.reason || 'not rendered'})`);
      continue;
    }
    if (!/Vazirmatn/i.test(record.computed_family || '')) violations.push(`${selector}: admitted repaired target is not Vazirmatn (${record.computed_family})`);
  }
  for (const selector of Object.keys(results.candidates)) {
    if (!admissionManifest.pre_repair?.[selector]) violations.push(`${selector}: selector has no pre-repair admission record`);
  }
}

if (violations.length > 0) {
  results.status = 'FAIL';
  results.violations = violations;
}

fs.writeFileSync(path.join(artifactDir, 'selector-admission-results.json'), `${JSON.stringify(results, null, 2)}\n`);
await context.close();
await browser.close();

if (violations.length > 0) {
  console.error(JSON.stringify(results, null, 2));
  process.exit(1);
}
console.log(JSON.stringify(results, null, 2));
