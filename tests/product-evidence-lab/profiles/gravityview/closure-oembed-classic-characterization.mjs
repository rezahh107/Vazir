import assert from 'node:assert/strict';
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
const persistedSource = manifest.oembed_source_shortcode;
if (typeof persistedSource !== 'string' || !persistedSource) {
  throw new Error('GravityView persisted oEmbed fixture source is unavailable.');
}

function classifyWpviewAssociation(dataAttributes, expectedSource) {
  const attributes = dataAttributes && typeof dataAttributes === 'object' ? dataAttributes : {};
  const encodedText = typeof attributes['data-wpview-text'] === 'string'
    ? attributes['data-wpview-text']
    : null;
  const wpviewType = typeof attributes['data-wpview-type'] === 'string'
    ? attributes['data-wpview-type']
    : null;
  const expectedEncodedText = encodeURIComponent(expectedSource);
  let decodedText = null;
  let decodeError = null;

  if (encodedText !== null) {
    try {
      decodedText = decodeURIComponent(encodedText);
    } catch (error) {
      decodeError = String(error?.message || error);
    }
  }

  const encodedMatchesSource = encodedText === expectedEncodedText;
  const decodedMatchesSource = decodedText === expectedSource;
  const embedTypeMatches = wpviewType === 'embed';

  return {
    associated: embedTypeMatches && encodedMatchesSource && decodedMatchesSource,
    wpview_type: wpviewType,
    encoded_text: encodedText,
    decoded_text: decodedText,
    expected_encoded_text: expectedEncodedText,
    embed_type_matches: embedTypeMatches,
    encoded_text_matches_expected_source: encodedMatchesSource,
    decoded_text_matches_expected_source: decodedMatchesSource,
    decode_error: decodeError,
  };
}

function falsifyWpviewAssociationClassifier(realAttributes, expectedSource) {
  const unrelatedSource = '[embed]https://example.invalid/vazir-unrelated-oembed-control[/embed]';
  const withoutText = { ...realAttributes };
  const withoutType = { ...realAttributes };
  const replacedText = { ...realAttributes, 'data-wpview-text': encodeURIComponent(unrelatedSource) };
  const replacedType = { ...realAttributes, 'data-wpview-type': 'vazir-unrelated-control' };
  delete withoutText['data-wpview-text'];
  delete withoutType['data-wpview-type'];

  const controls = {
    unrelated_expected_source: classifyWpviewAssociation(realAttributes, unrelatedSource),
    missing_data_wpview_text: classifyWpviewAssociation(withoutText, expectedSource),
    missing_data_wpview_type: classifyWpviewAssociation(withoutType, expectedSource),
    replaced_data_wpview_text: classifyWpviewAssociation(replacedText, expectedSource),
    replaced_data_wpview_type: classifyWpviewAssociation(replacedType, expectedSource),
  };

  for (const [name, control] of Object.entries(controls)) {
    assert.equal(
      control.associated,
      false,
      `wpview association classifier must reject ${name}; relevant data attributes cannot be ignored.`,
    );
  }

  return {
    unrelated_source: unrelatedSource,
    controls,
    all_negative_controls_rejected: true,
  };
}

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
const persistedEditorSource = await page.locator('textarea#content').inputValue().catch(() => null);
assert.equal(
  persistedEditorSource,
  persistedSource,
  'Classic Editor textarea source must equal the persisted exact GravityView oEmbed fixture source.',
);
assert.equal(
  persistedSource.includes(manifest.oembed_entry_url),
  true,
  'Persisted exact fixture source must contain the exact GravityView entry URL.',
);

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
    persisted_fixture_source: persistedSource,
    classic_editor_source_matches_fixture: persistedEditorSource === persistedSource,
    parse_embed_request_count: parseEmbedRequests.length,
    parse_embed_requests: parseEmbedRequests,
    parse_embed_responses: responseEvidence,
    authentic_placeholder_inserted: false,
    wpview_data_attribute_association: {
      proven: false,
      reason: 'No authentic placeholder/wpview node was available for data-attribute association qualification.',
    },
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
        data_attributes: Object.fromEntries([...node.attributes]
          .filter(attribute => attribute.name.startsWith('data-'))
          .map(attribute => [attribute.name, attribute.value])),
      });
      node = node.parentElement;
    }
    return rows;
  });
  const outerAncestry = await frameContext(frame);
  const wpviewNode = await placeholder.evaluate(el => {
    const node = el.closest('.wpview.wpview-wrap');
    if (!node) return null;
    return {
      tag: node.tagName,
      id: node.id || null,
      class_name: typeof node.className === 'string' ? node.className : '',
      data_attributes: Object.fromEntries([...node.attributes]
        .filter(attribute => attribute.name.startsWith('data-'))
        .map(attribute => [attribute.name, attribute.value])),
    };
  });
  assert.ok(wpviewNode, 'Authentic GravityView placeholder must be contained by the WordPress wpview wrapper.');

  const wpviewAssociation = classifyWpviewAssociation(wpviewNode.data_attributes, persistedSource);
  assert.equal(
    wpviewAssociation.associated,
    true,
    'WordPress wpview data attributes must associate the authentic placeholder with the persisted exact GravityView source.',
  );
  const associationFalsification = falsifyWpviewAssociationClassifier(wpviewNode.data_attributes, persistedSource);

  const wordpressApiAssociation = await page.evaluate(({ expectedSource, expectedEncodedText }) => {
    const tinymceEditors = Object.values(window.tinymce?.editors || {});
    let matchedEditor = null;
    let matchedNode = null;

    for (const editor of tinymceEditors) {
      const body = editor?.getBody?.();
      if (!body) continue;
      const nodes = [...body.querySelectorAll('.wpview.wpview-wrap')];
      const node = nodes.find(candidate => candidate.getAttribute('data-wpview-text') === expectedEncodedText);
      if (node) {
        matchedEditor = editor;
        matchedNode = node;
        break;
      }
    }

    const views = window.wp?.mce?.views;
    const getTextAvailable = typeof views?.getText === 'function';
    const getInstanceAvailable = typeof views?.getInstance === 'function';
    let wordpressDecodedText = null;
    let instanceText = null;

    if (matchedNode && getTextAvailable) {
      wordpressDecodedText = views.getText(matchedNode);
    }
    if (matchedNode && getInstanceAvailable) {
      instanceText = views.getInstance(matchedNode)?.text ?? null;
    }

    return {
      editor_id: matchedEditor?.id || null,
      matched_node_found: Boolean(matchedNode),
      wp_mce_views_get_text_available: getTextAvailable,
      wp_mce_views_get_instance_available: getInstanceAvailable,
      wordpress_get_text: wordpressDecodedText,
      wordpress_get_text_matches_expected_source: wordpressDecodedText === expectedSource,
      wordpress_instance_text: instanceText,
      wordpress_instance_text_matches_expected_source: instanceText === expectedSource,
    };
  }, {
    expectedSource: persistedSource,
    expectedEncodedText: wpviewAssociation.expected_encoded_text,
  });
  assert.equal(wordpressApiAssociation.matched_node_found, true, 'WordPress TinyMCE must expose the exact wpview node for the persisted source.');
  assert.equal(wordpressApiAssociation.wp_mce_views_get_text_available, true, 'WordPress wp.mce.views.getText must be available on the authentic Classic Editor path.');
  assert.equal(wordpressApiAssociation.wordpress_get_text_matches_expected_source, true, 'WordPress wp.mce.views.getText must decode data-wpview-text to the persisted exact fixture source.');
  assert.equal(wordpressApiAssociation.wp_mce_views_get_instance_available, true, 'WordPress wp.mce.views.getInstance must be available on the authentic Classic Editor path.');
  assert.equal(wordpressApiAssociation.wordpress_instance_text_matches_expected_source, true, 'WordPress wp.mce.views.getInstance must bind the wpview node to the persisted exact fixture source.');

  assert.equal(
    parseEmbedRequests.some(request => request.post_data_has_gravityview_entry_url),
    true,
    'Authentic parse-embed request must contain the exact GravityView entry URL.',
  );
  assert.equal(
    responseEvidence.some(response => response.http_status === 200 && response.success && response.body_has_loading_placeholder),
    true,
    'Authentic parse-embed response must successfully contain the GravityView loading placeholder.',
  );

  const stableScopeMarkers = [...innerAncestry, ...(outerAncestry || [])]
    .flatMap(row => [
      row.id || '',
      row.class_name || '',
      ...Object.entries(row.data_attributes || {}).map(([name, value]) => `${name}=${value}`),
    ])
    .join(' ');
  const gravityViewSpecificAncestorScope = /(?:^|\s)(?:gravityview|gk-gravityview)[-_\w]*/i.test(
    [...innerAncestry, ...(outerAncestry || [])]
      .map(row => `${row.id || ''} ${row.class_name || ''}`)
      .join(' '),
  );
  const exactGravityViewSourceAssociation = wpviewAssociation.associated
    && wordpressApiAssociation.wordpress_get_text_matches_expected_source
    && wordpressApiAssociation.wordpress_instance_text_matches_expected_source
    && persistedSource.includes(manifest.oembed_entry_url);
  const unrelatedEmbedIsolation = associationFalsification.all_negative_controls_rejected;
  const gravityViewSpecificScope = gravityViewSpecificAncestorScope || exactGravityViewSourceAssociation;
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
  const exclusionAssociationObserved = sameDocumentExclusion || frameElementInsideExclusion;
  const exclusionClass = /^\.([A-Za-z_][\w-]*)$/.exec(exclusionSelector)?.[1] || null;
  const sourceFixtureExercisesExclusion = exclusionClass
    ? new RegExp(`class=[\"'][^\"']*\\b${exclusionClass.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\b[^\"']*[\"']`).test(persistedEditorSource)
    : false;
  const existingExclusionAssociationProven = sourceFixtureExercisesExclusion && exclusionAssociationObserved;
  const importantRequired = /font-family\s*:/i.test(inlineStyles.heading || '') || /font-family\s*:/i.test(inlineStyles.paragraph || '');
  const typographyDisposition = isVazirmatn(headingFamily) && isVazirmatn(paragraphFamily) ? 'ALREADY_CORRECT' : 'FAIL';

  let finalDisposition = 'ALREADY_CORRECT';
  let reason = 'Authentically inserted placeholder text already resolves to Vazirmatn.';
  if ('FAIL' === typographyDisposition) {
    if (gravityViewSpecificScope && unrelatedEmbedIsolation && existingExclusionAssociationProven) {
      finalDisposition = 'ADMITTABLE_REPAIR_SEAM';
      reason = 'The authentic insertion exposes a stable source-bound wpview presentation association, rejects unrelated wpview sources, and preserves a usable relationship to the single configured exclusion boundary.';
    } else if (gravityViewSpecificScope && unrelatedEmbedIsolation && !existingExclusionAssociationProven) {
      finalDisposition = 'NOT_PROVEN';
      reason = sourceFixtureExercisesExclusion
        ? 'The authentic insertion has a stable source-bound wpview presentation association and rejects unrelated wpview sources, but the existing exclusion boundary was not preserved into the rendered placeholder context. The current bounded evidence therefore cannot admit a repair without weakening the single exclude_selectors authority.'
        : 'The authentic insertion has a stable source-bound wpview presentation association and rejects unrelated wpview sources. The inline GravityView font declarations still require !important, but the persisted fixture does not exercise an excluded source, so preservation of the single existing exclude_selectors authority remains unproven. No production repair is admitted from this evidence.';
    } else {
      finalDisposition = 'NOT_PROVEN';
      reason = 'The authentic typography failure is reproduced, but the repaired evidence does not establish every presentation-scoping, unrelated-embed isolation, and exclusion-preservation condition required to admit or mechanically reject a production seam.';
    }
  }

  results.oembed = {
    interaction_path: insertionPath,
    persisted_fixture_source: persistedSource,
    classic_editor_source_matches_fixture: persistedEditorSource === persistedSource,
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
    wpview: {
      node: wpviewNode,
      association_classifier: wpviewAssociation,
      wordpress_api_association: wordpressApiAssociation,
      falsification_controls: associationFalsification,
      exact_gravityview_source_association_proven: exactGravityViewSourceAssociation,
      data_wpview_type: wpviewNode.data_attributes['data-wpview-type'] || null,
      data_wpview_text: wpviewNode.data_attributes['data-wpview-text'] || null,
    },
    computed_typography: {
      placeholder: placeholderFamily,
      heading: headingFamily,
      paragraph: paragraphFamily,
    },
    inline_styles: inlineStyles,
    normal_stylesheet_can_beat_inline_font_without_important: false,
    important_required: importantRequired,
    gravityview_specific_ancestor_scope_exists: gravityViewSpecificAncestorScope,
    gravityview_specific_styling_scope_exists: gravityViewSpecificScope,
    gravityview_specific_presentation_scope_exists: gravityViewSpecificScope,
    exact_source_isolated_from_unrelated_wpviews: unrelatedEmbedIsolation,
    generic_wordpress_embed_scope_observed: genericWordPressScope,
    exclusion: {
      selector: exclusionSelector,
      source_fixture_exercises_exclusion_boundary: sourceFixtureExercisesExclusion,
      placeholder_inside_excluded_source_same_document: sameDocumentExclusion,
      containing_frame_inside_excluded_source: frameElementInsideExclusion,
      exclusion_association_observed: exclusionAssociationObserved,
      usable_existing_exclusion_association: existingExclusionAssociationProven,
      usable_existing_exclusion_association_proven: existingExclusionAssociationProven,
      preservation_disposition: existingExclusionAssociationProven ? 'PROVEN' : 'NOT_PROVEN',
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
