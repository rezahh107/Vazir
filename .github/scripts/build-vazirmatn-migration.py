from pathlib import Path

ROOT = Path('.')


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content, encoding='utf-8')


def replace_exact(path: str, old: str, new: str, expected: int | None = 1) -> None:
    text = read(path)
    actual = text.count(old)
    if expected is not None and actual != expected:
        raise RuntimeError(f'{path}: expected {expected} occurrences of {old!r}, found {actual}')
    if actual == 0:
        raise RuntimeError(f'{path}: missing replacement target {old!r}')
    write(path, text.replace(old, new))


def replace_section(path: str, start: str, end: str, replacement: str) -> None:
    text = read(path)
    start_at = text.find(start)
    end_at = text.find(end, start_at + len(start))
    if start_at < 0 or end_at < 0:
        raise RuntimeError(f'{path}: section markers not found: {start!r} -> {end!r}')
    write(path, text[:start_at] + replacement.rstrip() + '\n\n' + text[end_at:])


# Production runtime: keep public plugin/filter/class/handle names stable, but make
# the bundled family and asset identity truthful.
replace_exact(
    'includes/class-vazirfont-loader.php',
    "VAZIR_FONT_FONTS_URL . 'vazir-' . $weight . '.woff2'",
    "VAZIR_FONT_FONTS_URL . 'vazirmatn-' . $weight . '.woff2'",
)
replace_exact(
    'includes/class-vazirfont-loader.php',
    '$css .= "\\tfont-family: \'Vazir\';\\n";',
    '$css .= "\\tfont-family: \'Vazirmatn\';\\n";',
)
replace_exact(
    'includes/class-vazirfont-loader.php',
    "\"'Vazir', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Liberation Sans', sans-serif\"",
    "\"'Vazirmatn', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Liberation Sans', sans-serif\"",
)
replace_exact(
    'includes/class-vazirfont-gravityforms-integration.php',
    "\"'Vazir', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Liberation Sans', sans-serif\"",
    "\"'Vazirmatn', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Liberation Sans', sans-serif\"",
)
replace_exact(
    'includes/class-vazirfont-admin-settings.php',
    "font-family: \\'Vazir\\', sans-serif;",
    "font-family: \\'Vazirmatn\\', sans-serif;",
)

# Repository-level provenance and compatibility contracts.
repository_test = read('tests/RepositoryContractTest.php')
old_asset_test = """\tpublic function test_supported_font_assets_exist(): void {\n\t\tforeach ( [ '300', '400', '500', '700', '900' ] as $weight ) {\n\t\t\t$this->assertFileExists( VAZIR_TEST_ROOT . '/assets/fonts/vazir-' . $weight . '.woff2' );\n\t\t}\n\t}\n"""
new_asset_test = """\tpublic function test_supported_font_assets_match_pinned_vazirmatn_release(): void {\n\t\t$expected = [\n\t\t\t'300' => 'a3aa104f9a256734ca6769e017b4a2697c3036221e13758e0995a0cbeea969c4',\n\t\t\t'400' => 'e382101336c6eb32cfb31381c027d02d2e0354bad08f6a395d4088beb3db3d91',\n\t\t\t'500' => '3333e31188a2b628db8780ca22fd5aad85bc083ccee9beb8d4d52db18cb98d48',\n\t\t\t'700' => '836fae7d42d83faa249bc00e0099592be98a1fa260d22d82f269b6091e585627',\n\t\t\t'900' => 'e65a05523e6c0a434265913805746ebe6ed48af843e6126a936d06f69d7d47ad',\n\t\t];\n\n\t\tforeach ( $expected as $weight => $sha256 ) {\n\t\t\t$path = VAZIR_TEST_ROOT . '/assets/fonts/vazirmatn-' . $weight . '.woff2';\n\t\t\t$this->assertFileExists( $path );\n\t\t\t$this->assertSame( $sha256, hash_file( 'sha256', $path ) );\n\t\t\t$this->assertFileDoesNotExist( VAZIR_TEST_ROOT . '/assets/fonts/vazir-' . $weight . '.woff2' );\n\t\t}\n\n\t\t$this->assertSame(\n\t\t\t'17e355067c8284f47743a1ee3b1ef7ff684ff0601eda357f9353b10b3016ab31',\n\t\t\thash_file( 'sha256', VAZIR_TEST_ROOT . '/assets/fonts/OFL.txt' )\n\t\t);\n\t\t$this->assertSame(\n\t\t\t'b57746a5f7002c0974c76c32af74079ff7ef1aaf8f35495e9409cfa1eb11e1ca',\n\t\t\thash_file( 'sha256', VAZIR_TEST_ROOT . '/assets/fonts/AUTHORS.txt' )\n\t\t);\n\t\t$this->assertFileExists( VAZIR_TEST_ROOT . '/assets/fonts/Vazirmatn-PROVENANCE.md' );\n\t}\n\n\tpublic function test_vazirmatn_family_is_canonical_while_public_filter_is_preserved(): void {\n\t\t$loader = file_get_contents( VAZIR_TEST_ROOT . '/includes/class-vazirfont-loader.php' );\n\t\t$gravity = file_get_contents( VAZIR_TEST_ROOT . '/includes/class-vazirfont-gravityforms-integration.php' );\n\t\t$this->assertIsString( $loader );\n\t\t$this->assertIsString( $gravity );\n\t\t$this->assertStringContainsString( \"font-family: 'Vazirmatn'\", $loader );\n\t\t$this->assertStringContainsString( \"'Vazirmatn', system-ui\", $loader );\n\t\t$this->assertStringContainsString( \"'Vazirmatn', system-ui\", $gravity );\n\t\t$this->assertStringContainsString( \"'vazir_font_family'\", $loader );\n\t\t$this->assertStringContainsString( \"'vazir_font_family'\", $gravity );\n\t}\n"""
if repository_test.count(old_asset_test) != 1:
    raise RuntimeError('RepositoryContractTest.php: legacy asset test did not match exactly')
write('tests/RepositoryContractTest.php', repository_test.replace(old_asset_test, new_asset_test))

replace_exact('tests/runtime-contract.php', 'vazir-{$weight}.woff2', 'vazirmatn-{$weight}.woff2')
replace_exact('tests/runtime-contract.php', "/font-family:\\s*'Vazir'[^;]*!important;/", "/font-family:\\s*'Vazirmatn'[^;]*!important;/")
replace_exact('tests/runtime-contract.php', '"font-family: \'Vazir\'"', '"font-family: \'Vazirmatn\'"')

wordpress_smoke = read('tests/wordpress-smoke.php')
needle = """vazir_wp_assert( false === strpos( $css, \"format('truetype')\" ), 'real WordPress CSS does not advertise TTF' );\n"""
addition = needle + """vazir_wp_assert( false !== strpos( $css, \"font-family: 'Vazirmatn'\" ), 'real WordPress emits the canonical Vazirmatn family' );\nforeach ( [ '300', '400', '500', '700', '900' ] as $weight ) {\n\tvazir_wp_assert( false !== strpos( $css, \"vazirmatn-{$weight}.woff2\" ), \"real WordPress emits the packaged Vazirmatn source for weight {$weight}\" );\n\tvazir_wp_assert( false === strpos( $css, \"vazir-{$weight}.woff2\" ), \"real WordPress does not emit the legacy Vazir source for weight {$weight}\" );\n}\n\n$legacy_options = [\n\t'enable_frontend'      => false,\n\t'enable_admin'         => true,\n\t'enable_gravity_forms' => false,\n\t'font_weights'         => [ '400', '700' ],\n\t'exclude_selectors'    => [ '.legacy-option-boundary' ],\n];\nupdate_option( VAZIR_FONT_OPTION_NAME, $legacy_options, false );\nVazirFontPlugin::clear_cache();\nvazir_wp_assert( $legacy_options === VazirFontPlugin::get_options(), 'existing pre-migration option semantics survive the font migration unchanged' );\n"""
if wordpress_smoke.count(needle) != 1:
    raise RuntimeError('tests/wordpress-smoke.php insertion target missing')
write('tests/wordpress-smoke.php', wordpress_smoke.replace(needle, addition))

# Browser characterization: canonical family, selected-weight request semantics,
# byte accounting, duplicate detection, and bounded layout-sensitive checks.
write('tests/browser-characterization.mjs', r'''import { chromium } from 'playwright';
import assert from 'node:assert/strict';

const baseUrl = process.env.VAZIR_BASE_URL || 'http://127.0.0.1:8080';
const postId = process.env.VAZIR_POST_ID;
const blockTheme = process.env.VAZIR_BLOCK_THEME === '1';
const fontRequestRe = /\/assets\/fonts\/vazirmatn-(\d+)\.woff2(?:\?|$)/;
const selectedWeights = new Set(['400', '700']);

if (!postId) {
  throw new Error('VAZIR_POST_ID is required');
}

const browser = await chromium.launch();
const context = await browser.newContext();
const page = await context.newPage();
const fontRequests = [];
const fontResponseTasks = [];
const fontBytes = new Map();

page.on('request', request => {
  const url = request.url();
  if (fontRequestRe.test(url)) fontRequests.push(url);
});

page.on('response', response => {
  const url = response.url();
  if (!fontRequestRe.test(url)) return;
  fontResponseTasks.push((async () => {
    const body = await response.body();
    fontBytes.set(url, body.length);
  })());
});

const familyOf = async locator => locator.evaluate(element => getComputedStyle(element).fontFamily);
const expectVazirmatn = async (locator, label) => {
  await locator.waitFor({ state: 'visible' });
  const family = await familyOf(locator);
  assert.match(family, /Vazirmatn/i, `${label} should resolve to Vazirmatn; got ${family}`);
};

const measure = locator => locator.evaluate(element => {
  const style = getComputedStyle(element);
  const rect = element.getBoundingClientRect();
  return {
    width: rect.width,
    height: rect.height,
    lineHeight: Number.parseFloat(style.lineHeight),
    clientWidth: element.clientWidth,
    clientHeight: element.clientHeight,
    scrollWidth: element.scrollWidth,
    scrollHeight: element.scrollHeight,
  };
});

const assertSingleLineControlLayout = async (locator, label) => {
  const metrics = await measure(locator);
  assert.ok(metrics.width > 0 && metrics.height > 0, `${label} must have non-zero dimensions`);
  if (Number.isFinite(metrics.lineHeight)) {
    assert.ok(metrics.height + 0.5 >= metrics.lineHeight, `${label} height must accommodate line-height: ${JSON.stringify(metrics)}`);
  }
  assert.ok(metrics.scrollHeight <= metrics.clientHeight + 2, `${label} must not vertically clip after font migration: ${JSON.stringify(metrics)}`);
  return metrics;
};

await page.goto(`${baseUrl}/?p=${postId}`, { waitUntil: 'networkidle' });
await expectVazirmatn(page.locator('body'), 'frontend body');
await expectVazirmatn(page.getByRole('heading', { name: 'عنوان فارسی Mixed Heading' }), 'frontend heading');
await expectVazirmatn(page.getByText('متن فارسی Mixed Latin 123', { exact: true }), 'frontend paragraph');
await expectVazirmatn(page.locator('#vf-input'), 'frontend input');
await expectVazirmatn(page.locator('#vf-textarea'), 'frontend textarea');
await expectVazirmatn(page.locator('#vf-select'), 'frontend select');
await expectVazirmatn(page.locator('#vf-button'), 'frontend button');

const layoutMetrics = {
  input: await assertSingleLineControlLayout(page.locator('#vf-input'), 'frontend input'),
  select: await assertSingleLineControlLayout(page.locator('#vf-select'), 'frontend select'),
  button: await assertSingleLineControlLayout(page.locator('#vf-button'), 'frontend button'),
};
const cardMetrics = await measure(page.locator('#vf-layout-card'));
const tableMetrics = await measure(page.locator('#vf-layout-table'));
assert.ok(cardMetrics.width > 0 && tableMetrics.width > 0, 'layout card/table must render with non-zero dimensions');
assert.ok(tableMetrics.scrollWidth <= tableMetrics.clientWidth + 2, `layout table must not horizontally overflow after font migration: ${JSON.stringify(tableMetrics)}`);
layoutMetrics.card = cardMetrics;
layoutMetrics.table = tableMetrics;

const excludedText = page.locator('#vf-excluded-text');
await excludedText.waitFor({ state: 'visible' });
const excludedFamily = await familyOf(excludedText);
assert.doesNotMatch(excludedFamily, /Vazirmatn/i, `excluded component text must not resolve to Vazirmatn; got ${excludedFamily}`);
assert.match(excludedFamily, /monospace/i, `excluded component text must retain its explicit non-Vazirmatn family; got ${excludedFamily}`);

const pluginPreloads = await page.locator('link[rel="preload"][as="font"]').evaluateAll(nodes =>
  nodes.map(node => node.href).filter(href => /\/assets\/fonts\/vazirmatn-/i.test(href))
);
assert.equal(pluginPreloads.length, 0, 'plugin must not emit default font preloads');
await page.evaluate(async () => { if (document.fonts?.ready) await document.fonts.ready; });
await page.waitForLoadState('networkidle');
await Promise.all(fontResponseTasks);

const counts = new Map();
for (const url of fontRequests) counts.set(url, (counts.get(url) || 0) + 1);
const duplicates = [...counts.entries()].filter(([, count]) => count > 1);
assert.deepEqual(duplicates, [], `frontend must not duplicate bundled Vazirmatn font URL requests: ${JSON.stringify(duplicates)}`);
assert.ok(counts.size > 0, 'frontend must request at least one bundled Vazirmatn font');
const observedWeights = [...counts.keys()].map(url => {
  const match = url.match(fontRequestRe);
  return match ? match[1] : '';
});
assert.ok(observedWeights.every(weight => selectedWeights.has(weight)), `frontend requested an unselected weight: ${observedWeights.join(',')}`);
for (const url of counts.keys()) {
  assert.ok((fontBytes.get(url) || 0) > 0, `font response must have measured bytes for ${url}`);
}
const totalFontBytes = [...fontBytes.values()].reduce((sum, value) => sum + value, 0);
console.log(`FRONTEND_FONT_REQUESTS=${counts.size}`);
console.log(`FRONTEND_FONT_URLS=${[...counts.keys()].join(',')}`);
console.log(`FRONTEND_FONT_BYTES=${totalFontBytes}`);
console.log(`FRONTEND_LAYOUT_METRICS=${JSON.stringify(layoutMetrics)}`);

await page.goto(`${baseUrl}/wp-login.php`, { waitUntil: 'networkidle' });
await expectVazirmatn(page.locator('body.login'), 'login body');
await expectVazirmatn(page.locator('#user_login'), 'login input');
await expectVazirmatn(page.locator('#wp-submit'), 'login button');

await page.fill('#user_login', 'admin');
await page.fill('#user_pass', 'admin-password');
await Promise.all([
  page.waitForURL(/wp-admin/),
  page.click('#wp-submit'),
]);

await page.goto(`${baseUrl}/wp-admin/`, { waitUntil: 'networkidle' });
await expectVazirmatn(page.locator('body.wp-admin'), 'wp-admin body');
await expectVazirmatn(page.locator('.wrap h1').first(), 'wp-admin heading');

const adminIcon = page.locator('#adminmenu .wp-menu-image.dashicons-before').first();
await adminIcon.waitFor({ state: 'attached' });
const dashiconsFamily = await adminIcon.evaluate(element => getComputedStyle(element, '::before').fontFamily);
assert.match(dashiconsFamily, /dashicons/i, `wp-admin icon font must remain Dashicons; got ${dashiconsFamily}`);

await page.goto(`${baseUrl}/wp-admin/post.php?post=${postId}&action=edit`, { waitUntil: 'domcontentloaded' });
const editorFrameElement = page.locator('iframe[name="editor-canvas"]');
await editorFrameElement.waitFor({ state: 'attached', timeout: 30000 });
const editor = page.frameLocator('iframe[name="editor-canvas"]');
await expectVazirmatn(editor.locator('.editor-styles-wrapper'), 'Post Editor canvas root');
await expectVazirmatn(editor.getByText('متن فارسی Mixed Latin 123', { exact: true }).first(), 'Post Editor paragraph');
await expectVazirmatn(editor.getByText('عنوان فارسی Mixed Heading', { exact: true }).first(), 'Post Editor heading');

if (blockTheme) {
  await page.goto(`${baseUrl}/wp-admin/site-editor.php`, { waitUntil: 'domcontentloaded' });
  const siteEditorFrame = page.locator('iframe[name="editor-canvas"]');
  await siteEditorFrame.waitFor({ state: 'attached', timeout: 30000 });
  const siteEditor = page.frameLocator('iframe[name="editor-canvas"]');
  await expectVazirmatn(siteEditor.locator('.editor-styles-wrapper'), 'Site Editor canvas root');
}

console.log('BROWSER CHARACTERIZATION PASSED');
await browser.close();
''')

# Shared licensed-browser helper now measures Vazirmatn requests and bytes.
write('tests/product-evidence-lab/core/browser-helpers.mjs', r'''import assert from 'node:assert/strict';

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
''')

write('tests/product-evidence-lab/core/browser-helpers.test.mjs', r'''import assert from 'node:assert/strict';
import { analyzeVazirmatnFontRequests } from './browser-helpers.mjs';

const font400 = 'http://127.0.0.1:8080/wp-content/plugins/vazir-font-wp/assets/fonts/vazirmatn-400.woff2';
const font700 = 'http://127.0.0.1:8080/wp-content/plugins/vazir-font-wp/assets/fonts/vazirmatn-700.woff2?ver=1.3.0';

assert.throws(
  () => analyzeVazirmatnFontRequests([], 'zero-control'),
  /at least one bundled Vazirmatn WOFF2 request/,
  'zero observed matching requests must fail',
);

assert.deepEqual(
  analyzeVazirmatnFontRequests([font400], 'single-control'),
  { observed_font_urls: [font400], request_count: 1 },
  'one matching bundled font request should pass',
);

assert.throws(
  () => analyzeVazirmatnFontRequests([font400, font400], 'duplicate-control'),
  /duplicate bundled Vazirmatn font requests/,
  'duplicate matching URL must fail',
);

assert.deepEqual(
  analyzeVazirmatnFontRequests([font400, font700], 'distinct-control'),
  { observed_font_urls: [font400, font700], request_count: 2 },
  'multiple distinct bundled font requests should pass',
);

console.log('FONT REQUEST ANALYSIS FALSIFICATION PASSED');
''')

# Rename shared helper APIs throughout licensed browser profiles and the deep GF lane.
for path in [
    Path('tests/gravityforms-evidence-lab/browser-characterization.mjs'),
    *Path('tests/product-evidence-lab/profiles').glob('*/browser-characterization.mjs'),
]:
    text = path.read_text(encoding='utf-8')
    text = text.replace('expectNotVazir', 'expectNotVazirmatn')
    text = text.replace('expectVazir', 'expectVazirmatn')
    text = text.replace('observeVazirFontRequests', 'observeVazirmatnFontRequests')
    text = text.replace('/Vazir/i', '/Vazirmatn/i')
    text = text.replace('resolve to Vazir', 'resolve to Vazirmatn')
    text = text.replace('inherit Vazir', 'inherit Vazirmatn')
    text = text.replace('contain Vazir', 'contain Vazirmatn')
    path.write_text(text, encoding='utf-8')

# Generic browser fixture selects two weights to prove request selection and adds
# bounded card/table surfaces for layout-sensitive migration checks.
ci = read('.github/workflows/ci.yml')
old_eval = """          wp eval 'VazirFontPlugin::update_options([\"exclude_selectors\" => array_merge(VazirFontPlugin::get_options()[\"exclude_selectors\"], [\".vf-excluded-component\"])]);' --path=/tmp/vazir-wordpress\n"""
new_eval = """          wp eval '$options = VazirFontPlugin::get_options(); $options[\"exclude_selectors\"] = array_merge($options[\"exclude_selectors\"], [\".vf-excluded-component\"]); $options[\"font_weights\"] = [\"400\", \"700\"]; VazirFontPlugin::update_options($options);' --path=/tmp/vazir-wordpress\n"""
if ci.count(old_eval) != 1:
    raise RuntimeError('.github/workflows/ci.yml: option fixture target missing')
ci = ci.replace(old_eval, new_eval)
old_controls = """          <form id=\"vf-controls\"><input id=\"vf-input\" value=\"متن\"><textarea id=\"vf-textarea\">متن</textarea><select id=\"vf-select\"><option>گزینه</option></select><button id=\"vf-button\" type=\"button\">دکمه</button></form>\n          <!-- /wp:html -->\n"""
new_controls = """          <form id=\"vf-controls\"><input id=\"vf-input\" value=\"متن\"><textarea id=\"vf-textarea\">متن</textarea><select id=\"vf-select\"><option>گزینه</option></select><button id=\"vf-button\" type=\"button\">دکمه</button></form>\n          <div id=\"vf-layout-card\" style=\"width:320px;max-width:100%;border:1px solid;padding:12px;box-sizing:border-box;\"><p>کارت فارسی Card 123</p><table id=\"vf-layout-table\" style=\"width:100%;table-layout:fixed;\"><tbody><tr><th>عنوان</th><td>مقدار 123</td></tr></tbody></table></div>\n          <!-- /wp:html -->\n"""
if ci.count(old_controls) != 1:
    raise RuntimeError('.github/workflows/ci.yml: layout fixture target missing')
write('.github/workflows/ci.yml', ci.replace(old_controls, new_controls))

# Documentation: explicit upstream provenance, static-vs-variable decision,
# compatibility transition, options/upgrade/rollback, and evidence boundaries.
replace_section('README.md', '## Font delivery', '## Gravity Forms compatibility', '''## Font delivery

The bundled typeface is the official upstream **Vazirmatn v33.003** release from `rastikerdar/vazirmatn`, pinned to release commit `83629f877e8f084cc07b47030b5d3a0ff06c76ec`. The plugin self-hosts the static WOFF2 files for weights `300`, `400`, `500`, `700`, and `900`; it does not fetch font bytes at runtime.

`assets/fonts/Vazirmatn-PROVENANCE.md` records the exact upstream release archive identity, source paths, byte sizes, SHA-256 digests, license, and author material used to reproduce the bundled files.

Static delivery remains intentional. The product already exposes five discrete weight selections, while the upstream variable webfont is 111,152 bytes and each selected static face is approximately 50–51 KiB. Static faces preserve the existing settings model and let the browser request only weights actually used by a page. Variable delivery would become advantageous only when enough distinct weights are consumed on the same surface to outweigh its larger single request and the additional migration/verification complexity.

The runtime:

- references only packaged `vazirmatn-*.woff2` files;
- exposes the truthful canonical CSS family `Vazirmatn`;
- retains the public `vazir_font_family` filter as the existing compatibility API for overriding the complete family stack;
- does **not** create a hidden `Vazir` alias for Vazirmatn bytes;
- uses `font-display: swap`;
- performs no default font preloading;
- has no CDN dependency.

Existing callbacks on `vazir_font_family` continue to run unchanged. A callback that deliberately returns the legacy `Vazir` family name remains responsible for providing that family itself; the plugin no longer bundles legacy Vazir binaries under that identity.

### Upgrade and rollback

The persisted option name and schema are unchanged: existing frontend/admin/Gravity Forms toggles, selected weights, and `exclude_selectors` retain their previous meaning. Upgrading replaces only the bundled typeface/default family behavior; it does not reinterpret user options or require a database migration subsystem.

For this personal plugin, rollback is intentionally simple: reinstall/restore the previous pre-migration plugin revision/package. Because the option schema is unchanged, the prior version can reuse the same saved settings. The migration therefore does not keep a second legacy font payload solely for rollback.
''')
replace_exact('README.md', '`exclude_selectors` means that Vazir `font-family` enforcement', '`exclude_selectors` means that Vazirmatn `font-family` enforcement')
replace_exact('README.md', 'characterize how Vazir coexists with the exact Owner-supplied products', 'characterize how the bundled typography coexists with the exact Owner-supplied products')
replace_exact('README.md', 'Bundled font files are distributed with `assets/fonts/OFL.txt` under the SIL Open Font License 1.1.', 'Bundled Vazirmatn font files are distributed with the exact upstream `assets/fonts/OFL.txt` and `assets/fonts/AUTHORS.txt`; reproducible source identity and SHA-256 digests are recorded in `assets/fonts/Vazirmatn-PROVENANCE.md`.')

agents = read('AGENTS.md')
agents = agents.replace(
    'Bundled font assets are static Vazir WOFF2 files for weights `300`, `400`, `500`, `700`, and `900`.',
    'Bundled font assets are pinned static Vazirmatn `v33.003` WOFF2 files for weights `300`, `400`, `500`, `700`, and `900`.'
)
agents = agents.replace(
    '- keep the public `Vazir` family identity and `vazir_font_family` filter unless a separately characterized migration changes them;\n- keep `assets/fonts/OFL.txt` with bundled font files.\n\nA Vazirmatn migration is a separate typography migration, not a cleanup side effect.',
    '- use the truthful canonical `Vazirmatn` CSS family for the bundled upstream font;\n- retain the public `vazir_font_family` filter as the compatibility API for overriding the complete stack;\n- do not create a hidden legacy `Vazir` alias for Vazirmatn bytes;\n- keep exact upstream `assets/fonts/OFL.txt` and `assets/fonts/AUTHORS.txt` with the bundled files;\n- keep `assets/fonts/Vazirmatn-PROVENANCE.md` synchronized with the pinned release archive and bundled SHA-256 digests.'
)
agents = agents.replace('Element-level exclusions are negative applicability boundaries: Vazir `font-family` enforcement', 'Element-level exclusions are negative applicability boundaries: Vazirmatn `font-family` enforcement')
agents = agents.replace('| `assets/fonts/` | Packaged Vazir WOFF2 binaries and OFL license |', '| `assets/fonts/` | Pinned Vazirmatn WOFF2 binaries, upstream license/authors, and provenance |')
write('AGENTS.md', agents)

release = read('RELEASE.md')
release = release.replace('an explicit non-Vazir family must retain that family while neighboring text remains Vazir', 'an explicit non-Vazirmatn family must retain that family while neighboring text remains Vazirmatn')
release = release.replace('4. Verify every generated `@font-face` URL resolves to a packaged WOFF2 asset.', '4. Verify every generated `@font-face` URL resolves to the pinned packaged Vazirmatn WOFF2 asset and matches `assets/fonts/Vazirmatn-PROVENANCE.md`.')
write('RELEASE.md', release)

characterization = read('docs/CHARACTERIZATION.md')
start = characterization.find('## Current verification boundary')
end = characterization.find('## Exclusion semantics', start)
if start < 0 or end < 0:
    raise RuntimeError('docs/CHARACTERIZATION.md: PR #12 boundary markers missing')
replacement = '''## PR #12 exact-Head verification boundary

The initial PR #12 attempts that failed to receive runners were later superseded by executed exact-Head evidence. PR #12's final Head was `8042526d6d8130497b318b915aeb84f9c5a94fe0`.

On that exact Head, normal CI run #160 passed all 10 jobs, and Product-Wide Reproducible Evidence Lab run #30 passed all four licensed profiles: `gravityforms`, `gravityflow`, `gravityview`, and `gravity-stack`. The Gravity Forms profile included the Theme Framework/Orbital, supported Legacy Markup, Preview, Form Editor, No Conflict, iframe AJAX validation/rerender, multipage forward/back behavior, protected icon families, exclusions, and duplicate-free bundled font request characterization described in PR #12.

The merge commit `cb35e57f7ad62824e642e14576e1df9f8fb8e0f9` contains the same file tree as that successfully characterized PR Head, but the PR-head run must not be represented as an exact-SHA run of the later merge commit. These results are the legacy-Vazir baseline; they do not by themselves prove a later Vazirmatn candidate.

'''
characterization = characterization[:start] + replacement + characterization[end:]
characterization = characterization.replace('negative applicability boundary for Vazir `font-family` enforcement', 'negative applicability boundary for bundled `font-family` enforcement')
characterization += '''\n\n## Vazirmatn migration contract\n\nThe typography migration pins official upstream `rastikerdar/vazirmatn` release `v33.003` at commit `83629f877e8f084cc07b47030b5d3a0ff06c76ec`. Exact release-archive identity plus per-file SHA-256 digests are recorded in `assets/fonts/Vazirmatn-PROVENANCE.md`; the packaged `OFL.txt` and `AUTHORS.txt` are preserved byte-for-byte from that release.\n\nThe product continues to use static WOFF2 delivery because the existing settings/API expose discrete weights `300/400/500/700/900`. The upstream variable webfont is 111,152 bytes; the five static files are each approximately 50–51 KiB and are fetched only when a selected face is actually used. This keeps per-weight selection, request accounting, provenance, and rollback deterministic. Variable delivery remains reversible later if measured product surfaces consistently use enough simultaneous weights to justify the extra behavior.\n\nThe bundled canonical family is now `Vazirmatn`. The existing `vazir_font_family` filter remains the public compatibility seam and still overrides the complete family stack. No legacy `Vazir` alias is emitted for the new bytes. Existing persisted options retain their previous schema and meaning; rollback is restoring the pre-migration plugin version/package, not shipping both font families indefinitely.\n\nMigration verification must run on the exact candidate Head. Generic WordPress browser characterization now measures requested Vazirmatn URLs, selected-weight behavior, response bytes, duplicate requests, preload absence, protected Dashicons, mixed Persian/Latin/numerals, inputs/buttons/selects, and bounded card/table dimensions. Licensed product profiles remain the authority for real Gravity Forms/Flow/View behavior and protected families.\n'''
write('docs/CHARACTERIZATION.md', characterization)

# Remove legacy font binaries; exact upstream Vazirmatn binaries/provenance already
# exist on this temporary construction branch.
for weight in ['300', '400', '500', '700', '900']:
    Path(f'assets/fonts/vazir-{weight}.woff2').unlink()

# Final source-level sanity checks for the constructed tree.
for weight in ['300', '400', '500', '700', '900']:
    if not Path(f'assets/fonts/vazirmatn-{weight}.woff2').is_file():
        raise RuntimeError(f'missing Vazirmatn asset for {weight}')
    if Path(f'assets/fonts/vazir-{weight}.woff2').exists():
        raise RuntimeError(f'legacy Vazir asset still present for {weight}')

loader = read('includes/class-vazirfont-loader.php')
if "font-family: 'Vazirmatn'" not in loader or "'vazir_font_family'" not in loader:
    raise RuntimeError('loader family/filter compatibility sanity check failed')
if "VAZIR_FONT_FONTS_URL . 'vazir-'" in loader:
    raise RuntimeError('loader still references legacy font filenames')
