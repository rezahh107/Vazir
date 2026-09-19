import assert from 'node:assert/strict';
import { analyzeVazirFontRequests } from './browser-helpers.mjs';

const font400 = 'http://127.0.0.1:8080/wp-content/plugins/vazir-font-wp/assets/fonts/vazir-400.woff2';
const font700 = 'http://127.0.0.1:8080/wp-content/plugins/vazir-font-wp/assets/fonts/vazir-700.woff2?ver=1.3.0';

assert.throws(
  () => analyzeVazirFontRequests([], 'zero-control'),
  /at least one bundled Vazir WOFF2 request/,
  'zero observed matching requests must fail',
);

assert.deepEqual(
  analyzeVazirFontRequests([font400], 'single-control'),
  { observed_font_urls: [font400], request_count: 1 },
  'one matching bundled font request should pass',
);

assert.throws(
  () => analyzeVazirFontRequests([font400, font400], 'duplicate-control'),
  /duplicate bundled Vazir font requests/,
  'duplicate matching URL must fail',
);

assert.deepEqual(
  analyzeVazirFontRequests([font400, font700], 'distinct-control'),
  { observed_font_urls: [font400, font700], request_count: 2 },
  'multiple distinct bundled font requests should pass',
);

console.log('FONT REQUEST ANALYSIS FALSIFICATION PASSED');
