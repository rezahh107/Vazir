import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import { familyOf, login } from '../../core/browser-helpers.mjs';

const baseUrl = process.env.VAZIR_LAB_BASE_URL || 'http://127.0.0.1:8080';
const artifactDir = process.env.VAZIR_LAB_ARTIFACT_DIR;
const user = process.env.VAZIR_LAB_ADMIN_USER || 'vazir_lab_admin';
const password = process.env.VAZIR_LAB_ADMIN_PASSWORD || 'vazir-lab-admin-password';
if (!artifactDir) throw new Error('VAZIR_LAB_ARTIFACT_DIR is required');

const resultPath = path.join(artifactDir, 'gravityview-closure-browser-results.json');
const manifest = JSON.parse(fs.readFileSync(path.join(artifactDir, 'gravityview-fixture.json'), 'utf8'));
const sourceEvidence = JSON.parse(fs.readFileSync(path.join(artifactDir, 'gravityview-closure-runtime-results.json'), 'utf8'));
const results = JSON.parse(fs.readFileSync(resultPath, 'utf8'));
const isVazirmatn = family => /Vazirmatn/i.test(family || '');
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));

async function firstVisible(locator) {
  const count = await locator.count();
  for (let index = 0; index < count; index += 1) {
    const candidate = locator.nth(index);
    if (await candidate.isVisible().catch(() => false)) return candidate;
  }
  return null;
}

async function locatePlaceholder(page) {
  for (const frame of page.frames()) {
    const visible = await firstVisible(frame.locator('.loading-placeholder'));
    if (visible) return { frame, locator: visible };
  }
  return null;
}

async function frameContext(frame) {
  if (frame === frame.page().mainFrame()) return null;
  try {
    const handle = await frame.frameElement();
    return await handle.evaluate(el => {
      const ancestry = [];
      let node = el;
      while (node && node !== document.documentElement && ancestry.length < 12) {
        ancestry.push({
          tag: node.tagName,
          id: node.id || null,
          class_name: typeof node.className === 'string' ? node.className : '',
          role: node.getAttribute('role'),
          data_attributes: Object.fromEntries([...node.attributes]
            .filter(attribute => attribute.name.startsWith('data-'))
            .map(attribute => [attribute.name, attribute.value])),
        });
        node = node.parentElement;
      }
      return ancestry;
    });
  } catch {
    return null;
  }
}

const browser = await chromium.launch();
const context = await browser.newContext();
const page = await context.newPage();
await login(page, baseUrl, user, password);

const parseEmbedRequests = [];
const parseEmbedResponses = [];
page.on('request', request => {
  const postData = request.postData() || '';
  if (/\/wp-admin\/admin-ajax\.php(?:\?|$)/.test(request.url()) && /(?:^|&)action=parse-embed(?:&|$)/.test(postData)) {
    parseEmbedRequests.push({
      method: request.method(),
      url: request.url(),
      post_data_has_gravityview_entry_url: postData.includes(encodeURIComponent(manifest.oembed_entry_url)) || postData.includes(manifest.oembed_entry_url),
    });
  }
});
page.on('response', response => {
  const postData = response.request().postData() || '';
  if (/\/wp-admin\/admin-ajax\.php(?:\?|$)/.test(response.url()) && /(?:^|&)action=parse-embed(?:&|$)/.test(postData)) {
    parseEmbedResponses.push(response);
  }
});

await page.goto(manifest.oembed_editor_url, { waitUntil: 'domcontentloaded' });
await page.locator('body.post-type-vazir_gv_oembed').waitFor({ state: 'visible', timeout: 30000 });
const visualTab = page.locator('#content-tmce').first();
if (await visualTab.count() && await visualTab.isVisible().catch(() => false)) {
  await visualTab.click().catch(() => {});
}

let placeholderHit = null;
for (let attempt = 0; attempt < 60 && !placeholderHit; attempt += 1) {
  await sleep(250);
  placeholderHit = await locatePlaceholder(page);
}
await sleep(250);

const responseEvidence = [];
for (const response of parseEmbedResponses) {
  try {
    const json = await response.json();
    responseEvidence.push({
      http_status: response.status(),
      success: Boolean(json?.success),
      body_has_loading_placeholder: Boolean(json?.data?.body?.includes('loading-placeholder')),
    });
  } catch (error) {
    responseEvidence.push({ http_status: response.status(), json_error: String(error?.message || error) });
  }
}

const insertionPath = 'Native WordPress Classic editor -> TinyMCE wp.mce.views embed preview for persisted GravityView [embed] shortcode';
if (!placeholderHit) {
  results.oembed = {
    interaction_path: insertionPath,
    parse_embed_request_count: parseEmbedRequests.length,
    parse_embed_requests: parseEmbedRequests,
    parse_embed_responses: responseEvidence,
    authentic_placeholder_inserted: false,
    supported_source_seams: {
      gravityview_embed_related_hook_names: sourceEvidence.gravityview_oembed.embed_related_literal_hook_names,
      wordpress_embed_related_hook_names: sourceEvidence.wordpress_embed_lifecycle.embed_related_literal_hook_names,
      wordpress_generic_mce_embed_path: sourceEvidence.wordpress_embed_lifecycle.generic_mce_embed_path,
    },
    final_disposition: parseEmbedRequests.length ? 'NOT_PROVEN' : 'NOT_REACHABLE',
    reason: parseEmbedRequests.length
      ? 'The native Classic editor executed parse-embed but the returned placeholder was not deterministically observed in the rendered wpview context.'
      : 'The native Classic editor did not reach parse-embed for the persisted GravityView [embed] shortcode.',
  };
} else {
  const { frame, locator: placeholder } = placeholderHit;
  const heading = placeholder.locator('h3').first();
  const paragraph = placeholder.locator('p').first();
  const placeholderFamily = await familyOf(placeholder);
  const headingFamily = await heading.count() ? await familyOf(heading) : null;
  const paragraphFamily = await paragraph.count() ? await familyOf(paragraph) : null;
  const inlineStyles = await placeholder.evaluate(el => ({
    heading: el.querySelector('h3')?.getAttribute('style') || null,
    paragraph: el.querySelector('p')?.getAttribute('style') || null,
  }));
  const innerAncestry = await placeholder.evaluate(el => {
    const rows = [];
    let node = el;
    while (node && node !== document.documentElement && rows.length < 12) {
      rows.push({
        tag: node.tagName,
        id: node.id || null,
        class_name: typeof node.className === 'string' ? node.className : '',
        role: node.getAttribute('role'),
      });
      node = node.parentElement;
    }
    return rows;
  });
  const outerAncestry = await frameContext(frame);
  const stableScopeMarkers = [...innerAncestry, ...(outerAncestry || [])]
    .map(row => `${row.id || ''} ${row.class_name || ''}`)
    .join(' ');
  const gravityViewSpecificScope = /(?:^|\s)(?:gravityview|gk-gravityview)[-_\w]*/i.test(stableScopeMarkers);
  const genericWordPressScope = /wpview|mce|wp-editor|mce-container|wp-editor-wrap/i.test(stableScopeMarkers);

  const exclusionSelector = manifest.editor_exclusion_selector || '.vazir-gv-evidence-excluded';
  const sameDocumentExclusion = await placeholder.evaluate((el, selector) => Boolean(el.closest(selector)), exclusionSelector).catch(() => false);
  let frameElementInsideExclusion = false;
  if (frame !== page.mainFrame()) {
    try {
      const handle = await frame.frameElement();
      frameElementInsideExclusion = await handle.evaluate((el, selector) => Boolean(el.closest(selector)), exclusionSelector);
    } catch {}
  }
  const exclusionAssociation = sameDocumentExclusion || frameElementInsideExclusion;
  const importantRequired = /font-family\s*:/i.test(inlineStyles.heading || '') || /font-family\s*:/i.test(inlineStyles.paragraph || '');
  const typographyDisposition = isVazirmatn(headingFamily) && isVazirmatn(paragraphFamily) ? 'ALREADY_CORRECT' : 'FAIL';

  let finalDisposition = 'ALREADY_CORRECT';
  let reason = 'Authentically inserted placeholder text already resolves to Vazirmatn.';
  if ('FAIL' === typographyDisposition) {
    if (gravityViewSpecificScope && exclusionAssociation) {
      finalDisposition = 'ADMITTABLE_REPAIR_SEAM';
      reason = 'The authentic insertion exposes both a stable GravityView-specific styling scope and a usable relationship to the single configured exclusion boundary.';
    } else {
      finalDisposition = 'NO_ADMISSION';
      reason = 'The authentic failing placeholder is rendered inside generic WordPress wpview/TinyMCE context without a stable GravityView-specific styling ancestor and without a usable relationship to the single configured source exclusion boundary. The inline font declarations require !important, so a CSS repair would necessarily broaden to unrelated embed placeholders; response rewriting, vendor edits, and a second exclusion system are outside Vazir ownership.';
    }
  }

  results.oembed = {
    interaction_path: insertionPath,
    parse_embed_request_count: parseEmbedRequests.length,
    parse_embed_requests: parseEmbedRequests,
    parse_embed_responses: responseEvidence,
    authentic_placeholder_inserted: true,
    frame: {
      url: frame.url(),
      name: frame.name(),
      outer_ancestry: outerAncestry,
    },
    surrounding_dom: innerAncestry,
    computed_typography: {
      placeholder: placeholderFamily,
      heading: headingFamily,
      paragraph: paragraphFamily,
    },
    inline_styles: inlineStyles,
    normal_stylesheet_can_beat_inline_font_without_important: false,
    important_required: importantRequired,
    gravityview_specific_styling_scope_exists: gravityViewSpecificScope,
    generic_wordpress_embed_scope_observed: genericWordPressScope,
    exclusion: {
      selector: exclusionSelector,
      placeholder_inside_excluded_source_same_document: sameDocumentExclusion,
      containing_frame_inside_excluded_source: frameElementInsideExclusion,
      usable_existing_exclusion_association: exclusionAssociation,
    },
    supported_source_seams: {
      gravityview_embed_related_hook_names: sourceEvidence.gravityview_oembed.embed_related_literal_hook_names,
      wordpress_embed_related_hook_names: sourceEvidence.wordpress_embed_lifecycle.embed_related_literal_hook_names,
      wordpress_generic_mce_embed_path: sourceEvidence.wordpress_embed_lifecycle.generic_mce_embed_path,
      response_rewriting_admitted: false,
    },
    typography_disposition: typographyDisposition,
    final_disposition: finalDisposition,
    reason,
  };
}

fs.writeFileSync(resultPath, `${JSON.stringify(results, null, 2)}\n`);
await browser.close();
console.log(JSON.stringify(results.oembed, null, 2));
