import assert from 'node:assert/strict';

const VAZIRMATN_FONT_REQUEST_RE = /\/assets\/fonts\/vazirmatn-\d+\.woff2(?:\?|$)/;

export const familyOf = locator => locator.evaluate(el => getComputedStyle(el).fontFamily);
export const pseudoFamily = (locator, pseudo = '::before') => locator.evaluate((el, p) => getComputedStyle(el, p).fontFamily, pseudo);

export async function expectVazirmatn(locator, label) {
  await locator.waitFor({ state: 'visible', timeout: 30000 });
  const family = await familyOf(locator);
  assert.match(family, /Vazirmatn/i, `${label} should resolve to Vazirmatn; got ${family}`);
  return family;
}

export async function expectNotVazirmatn(locator, label, expected) {
  await locator.waitFor({ state: 'visible', timeout: 30000 });
  const family = await familyOf(locator);
  assert.doesNotMatch(family, /Vazirmatn/i, `${label} must not resolve to Vazirmatn; got ${family}`);
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

export function analyzeVazirmatnFontRequests(requestUrls, label = 'Vazirmatn font request measurement') {
  const matching = requestUrls.filter(url => VAZIRMATN_FONT_REQUEST_RE.test(url));
  assert.ok(matching.length > 0, `${label} must observe at least one bundled Vazirmatn WOFF2 request`);

  const counts = new Map();
  for (const url of matching) counts.set(url, (counts.get(url) || 0) + 1);
  const duplicates = [...counts.entries()].filter(([, count]) => count > 1);
  assert.deepEqual(duplicates, [], `${label} observed duplicate bundled Vazirmatn font requests: ${JSON.stringify(duplicates)}`);

  return {
    observed_font_urls: [...counts.keys()],
    request_count: matching.length,
  };
}

export async function observeVazirmatnFontRequests(browser, { url, waitForSurface, label }) {
  const context = await browser.newContext();
  const page = await context.newPage();
  const requests = [];
  const responseTasks = [];
  const bytesByUrl = new Map();

  page.on('request', request => {
    const requestUrl = request.url();
    if (VAZIRMATN_FONT_REQUEST_RE.test(requestUrl)) requests.push(requestUrl);
  });
  page.on('response', response => {
    const responseUrl = response.url();
    if (!VAZIRMATN_FONT_REQUEST_RE.test(responseUrl)) return;
    responseTasks.push((async () => {
      const body = await response.body();
      bytesByUrl.set(responseUrl, body.length);
    })());
  });

  try {
    await page.goto(url, { waitUntil: 'networkidle' });
    await waitForSurface(page);
    await page.evaluate(async () => {
      if (document.fonts?.ready) await document.fonts.ready;
    });
    await page.waitForLoadState('networkidle');
    await Promise.all(responseTasks);
    const measurement = analyzeVazirmatnFontRequests(requests, label);
    for (const observedUrl of measurement.observed_font_urls) {
      assert.ok((bytesByUrl.get(observedUrl) || 0) > 0, `${label} must measure response bytes for ${observedUrl}`);
    }
    const fontBytesByUrl = Object.fromEntries(measurement.observed_font_urls.map(observedUrl => [observedUrl, bytesByUrl.get(observedUrl)]));
    return {
      ...measurement,
      font_bytes_by_url: fontBytesByUrl,
      total_font_bytes: Object.values(fontBytesByUrl).reduce((sum, value) => sum + value, 0),
    };
  } finally {
    await context.close();
  }
}
