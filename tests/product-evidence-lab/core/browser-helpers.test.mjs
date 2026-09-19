import assert from 'node:assert/strict';
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
