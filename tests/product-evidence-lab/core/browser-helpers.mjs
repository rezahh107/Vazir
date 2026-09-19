import assert from 'node:assert/strict';

const VAZIR_FONT_REQUEST_RE = /\/assets\/fonts\/vazir-\d+\.woff2(?:\?|$)/;

export const familyOf = locator => locator.evaluate(el => getComputedStyle(el).fontFamily);
export const pseudoFamily = (locator, pseudo = '::before') => locator.evaluate((el, p) => getComputedStyle(el, p).fontFamily, pseudo);

export async function expectVazir(locator, label) {
  await locator.waitFor({ state: 'visible', timeout: 30000 });
  const family = await familyOf(locator);
  assert.match(family, /Vazir/i, `${label} should resolve to Vazir; got ${family}`);
  return family;
}

export async function expectNotVazir(locator, label, expected) {
  await locator.waitFor({ state: 'visible', timeout: 30000 });
  const family = await familyOf(locator);
  assert.doesNotMatch(family, /Vazir/i, `${label} must not resolve to Vazir; got ${family}`);
  if (expected) assert.match(family, expected, `${label} should retain ${expected}; got ${family}`);
  return family;
}

export function makeRecorder(results) {
  let failed = false;
  return {
    async record(name, fn) {
      try {
        const detail = await fn();
        results.scenarios[name] = { status: 'PASS', ...(detail || {}) };
      } catch (error) {
        failed = true;
        results.status = 'FAIL';
        results.scenarios[name] = { status: 'FAIL', reason: error instanceof Error ? error.message : String(error) };
      }
    },
    failed: () => failed,
  };
}

export async function login(page, baseUrl, user, password) {
  await page.goto(`${baseUrl}/wp-login.php`, { waitUntil: 'domcontentloaded' });
  await page.fill('#user_login', user);
  await page.fill('#user_pass', password);
  await Promise.all([
    page.waitForURL(/wp-admin\//, { timeout: 30000 }),
    page.click('#wp-submit'),
  ]);
}

export function analyzeVazirFontRequests(requestUrls, label = 'Vazir font request measurement') {
  const matching = requestUrls.filter(url => VAZIR_FONT_REQUEST_RE.test(url));
  assert.ok(matching.length > 0, `${label} must observe at least one bundled Vazir WOFF2 request`);

  const counts = new Map();
  for (const url of matching) counts.set(url, (counts.get(url) || 0) + 1);
  const duplicates = [...counts.entries()].filter(([, count]) => count > 1);
  assert.deepEqual(duplicates, [], `${label} observed duplicate bundled Vazir font requests: ${JSON.stringify(duplicates)}`);

  return {
    observed_font_urls: [...counts.keys()],
    request_count: matching.length,
  };
}

export async function observeVazirFontRequests(browser, { url, waitForSurface, label }) {
  const context = await browser.newContext();
  const page = await context.newPage();
  const requests = [];

  page.on('request', request => {
    const requestUrl = request.url();
    if (VAZIR_FONT_REQUEST_RE.test(requestUrl)) requests.push(requestUrl);
  });

  try {
    await page.goto(url, { waitUntil: 'networkidle' });
    await waitForSurface(page);
    await page.evaluate(async () => {
      if (document.fonts?.ready) await document.fonts.ready;
    });
    await page.waitForLoadState('networkidle');
    return analyzeVazirFontRequests(requests, label);
  } finally {
    await context.close();
  }
}
