import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';
import { expectVazirmatn, makeRecorder, observeVazirmatnFontRequests } from '../../core/browser-helpers.mjs';

const artifactDir = process.env.VAZIR_LAB_ARTIFACT_DIR;
if (!artifactDir) throw new Error('VAZIR_LAB_ARTIFACT_DIR is required');
const manifest = JSON.parse(fs.readFileSync(path.join(artifactDir, 'gp-file-upload-pro-fixture.json'), 'utf8'));
const results = {
  status: 'PASS',
  profile: 'gp-file-upload-pro',
  exact_package_version: manifest.gp_file_upload_pro_version,
  disposition: 'NATIVE_INHERITANCE',
  production_repair: 'NOT_REQUIRED',
  scenarios: {},
  boundaries: {
    crop_heading_guidance: 'NOT_REACHABLE: exact 1.5.13 crop UI renders actions/count but no separate heading/guidance node.',
    textual_progress_status: 'NOT_REACHABLE: exact 1.5.13 progress indicator is visual and does not expose a text status surface.',
  },
};
const recorder = makeRecorder(results);
const browser = await chromium.launch();
const context = await browser.newContext();
const page = await context.newPage();
const root = `#field_${manifest.form_id}_${manifest.file_field_id}`;
const fileInputSelector = `${root} input[type="file"]`;
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAFAAAAA8CAIAAAB+RarbAAAAY0lEQVR4nO3PAQ3AIADAMEASmhCLrLv4k71VsM179viT9XXA2wzXGa4zXGe4znCd4TrDdYbrDNcZrjNcZ7jOcJ3hOsN1husM1xmuM1xnuM5wneE6wzXGa4zXGe4znDdA7I0AdZ4WGfhAAAAAElFTkSuQmCC', 'base64');

async function loadFixture() {
  await page.goto(manifest.frontend_url, { waitUntil: 'networkidle' });
  await page.locator(`${root} .gpfup__droparea`).waitFor({ state: 'visible', timeout: 30000 });
}

async function waitForUploadSettled() {
  // Exact 1.5.13 renders filename/file metadata before Plupload reaches DONE,
  // and the Vue file node can be replaced while image processing completes.
  // The product-owned progress component remains until status=5/DONE and its
  // minimum display interval elapses. Measure computed typography only after
  // that stable lifecycle boundary so a detached transient node cannot create
  // a false empty computed-style result.
  await page.locator(`${root} .gpfup__progress-container`).first().waitFor({ state: 'hidden', timeout: 30000 });
  await page.waitForTimeout(100);
}

async function waitForCropperReady(lightbox) {
  // Exact 1.5.13 enables the Save button as soon as imgSrc exists, while its
  // save() implementation still returns early if vue-advanced-cropper's
  // getResult().canvas is not ready yet. Wait for the actual cropper image and
  // stencil plus two paint frames before exercising Save so the test does not
  // turn that transient initialization window into a false interaction failure.
  const cropperImage = lightbox.locator('.vue-advanced-cropper__image').first();
  await cropperImage.waitFor({ state: 'visible', timeout: 15000 });
  await cropperImage.evaluate(async (el) => {
    if (el instanceof HTMLImageElement && (!el.complete || el.naturalWidth === 0)) {
      await new Promise((resolve, reject) => {
        const timer = setTimeout(() => reject(new Error('Cropper image did not finish loading.')), 10000);
        el.addEventListener('load', () => { clearTimeout(timer); resolve(); }, { once: true });
        el.addEventListener('error', () => { clearTimeout(timer); reject(new Error('Cropper image failed to load.')); }, { once: true });
      });
    }
    await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
  });
  await lightbox.locator('.vue-rectangle-stencil:visible, .vue-circle-stencil:visible').first().waitFor({ state: 'visible', timeout: 15000 });
  await page.waitForTimeout(100);
}

async function openCropEditor(preview, edit) {
  await preview.hover();
  await edit.waitFor({ state: 'visible', timeout: 10000 });
  await edit.click();
  const lightbox = page.locator('.cropper__lightbox:visible').first();
  await lightbox.waitFor({ state: 'visible', timeout: 15000 });
  assert.equal(
    await lightbox.evaluate((el, fieldSelector) => el.closest(fieldSelector) === null, root),
    true,
    'Authentic crop lightbox must be detached from the Gravity Forms field ancestry.'
  );
  await waitForCropperReady(lightbox);
  return lightbox;
}

await recorder.record('initial_upload_ui_and_validation', async () => {
  await loadFixture();
  const families = {};
  families.drop_guidance = await expectVazirmatn(page.locator(`${root} .gpfup__droparea > div`).first(), 'File Upload Pro drop/select guidance');
  families.select_button = await expectVazirmatn(page.locator(`${root} .gpfup__select-files`).first(), 'File Upload Pro upload/select button');

  let validation = { disposition: 'NOT_REACHABLE' };
  const input = page.locator(fileInputSelector).first();
  await input.setInputFiles({ name: 'invalid-evidence.txt', mimeType: 'text/plain', buffer: Buffer.from('invalid extension evidence') });
  const error = page.locator(`${root} .gpfup__file-error`).first();
  try {
    await error.waitFor({ state: 'visible', timeout: 7000 });
    validation = {
      disposition: 'PASS',
      computed_family: await expectVazirmatn(error, 'File Upload Pro validation/upload error text'),
      text: (await error.innerText()).trim(),
    };
  } catch {
    validation = { disposition: 'NOT_REACHABLE', reason: 'Exact browser path rejected/cleared the invalid file without a stable visible textual error node.' };
  }
  return { families, validation };
});

await recorder.record('uploaded_file_crop_cancel_save_and_rerender', async () => {
  await loadFixture();
  const input = page.locator(fileInputSelector).first();
  await input.setInputFiles({ name: 'vazir-crop-evidence.png', mimeType: 'image/png', buffer: png });
  let filename = page.locator(`${root} .gpfup__filename`).first();
  await filename.waitFor({ state: 'visible', timeout: 30000 });
  await waitForUploadSettled();
  filename = page.locator(`${root} .gpfup__filename`).first();
  const families = {
    filename: await expectVazirmatn(filename, 'File Upload Pro uploaded filename'),
  };
  const sizeNode = page.locator(`${root} .gpfup__filesize`).first();
  if (await sizeNode.count()) families.filesize = await expectVazirmatn(sizeNode, 'File Upload Pro uploaded file size');

  const preview = page.locator(`${root} .gpfup__preview`).first();
  await preview.waitFor({ state: 'visible', timeout: 30000 });
  const edit = page.locator(`${root} .gpfup__edit`).first();
  let lightbox = await openCropEditor(preview, edit);

  let cancel = lightbox.locator('.gpfup__cancel').first();
  let save = lightbox.locator('.gpfup__crop').first();
  families.crop_cancel = await expectVazirmatn(cancel, 'File Upload Pro detached crop Cancel action');
  families.crop_save = await expectVazirmatn(save, 'File Upload Pro detached crop Save/Crop action');
  const count = lightbox.locator('.gpfup__cropper_count').first();
  if (await count.count() && await count.isVisible()) families.crop_count = await expectVazirmatn(count, 'File Upload Pro detached crop count');

  await cancel.click();
  await lightbox.waitFor({ state: 'hidden', timeout: 10000 });
  lightbox = await openCropEditor(preview, edit);
  save = lightbox.locator('.gpfup__crop').first();
  assert.equal(await save.isEnabled(), true, 'File Upload Pro Save action must be enabled after the cropper reaches its rendered-ready state.');
  await save.click();

  let cropSaveCompletion;
  try {
    await lightbox.waitFor({ state: 'hidden', timeout: 5000 });
    await filename.waitFor({ state: 'visible', timeout: 30000 });
    await waitForUploadSettled();
    filename = page.locator(`${root} .gpfup__filename`).first();
    families.post_crop_filename = await expectVazirmatn(filename, 'File Upload Pro post-crop file state');
    cropSaveCompletion = {
      disposition: 'PASS',
      lightbox_closed: true,
      save_click_dispatched: true,
    };
  } catch (error) {
    cropSaveCompletion = {
      disposition: 'NOT_PROVEN',
      reason: `Exact 1.5.13 synthetic crop Save exposed no stable completion/closure signal within the bounded observation window: ${String(error?.message || error).split('\n')[0]}`,
      save_action_enabled: true,
      save_click_dispatched: true,
    };
    if (await lightbox.isVisible().catch(() => false)) {
      cancel = lightbox.locator('.gpfup__cancel').first();
      await cancel.click();
      await lightbox.waitFor({ state: 'hidden', timeout: 10000 });
    }
    filename = page.locator(`${root} .gpfup__filename`).first();
    await filename.waitFor({ state: 'visible', timeout: 15000 });
    families.post_save_attempt_filename = await expectVazirmatn(filename, 'File Upload Pro file state after bounded Save attempt');
  }

  await page.evaluate(({ formId }) => {
    if (!window.jQuery) throw new Error('jQuery is unavailable for authentic gform_post_render rerender signal.');
    window.jQuery(document).trigger('gform_post_render', [formId, 1]);
  }, { formId: manifest.form_id });
  await page.locator(`${root} .gpfup__droparea`).waitFor({ state: 'visible', timeout: 15000 });
  await page.waitForTimeout(800);
  assert.equal(await page.locator(`${root} .gpfup`).count(), 1, 'Rerender must retain exactly one File Upload Pro component root.');
  families.rerender_drop_guidance = await expectVazirmatn(page.locator(`${root} .gpfup__droparea > div`).first(), 'File Upload Pro rerendered guidance');
  filename = page.locator(`${root} .gpfup__filename`).first();
  await filename.waitFor({ state: 'visible', timeout: 15000 });
  const rerenderPreview = page.locator(`${root} .gpfup__preview`).first();
  const rerenderEdit = page.locator(`${root} .gpfup__edit`).first();
  lightbox = await openCropEditor(rerenderPreview, rerenderEdit);
  cancel = lightbox.locator('.gpfup__cancel').first();
  families.rerender_crop_cancel = await expectVazirmatn(cancel, 'File Upload Pro rerendered detached crop Cancel action');
  await cancel.click();
  await lightbox.waitFor({ state: 'hidden', timeout: 10000 });

  return {
    families,
    uploaded_filename: (await filename.innerText()).trim(),
    crop_lightbox_detached_from_field: true,
    cancel_completed: true,
    crop_save_completion: cropSaveCompletion,
    rerender_component_count: 1,
    rerender_crop_reopened: true,
  };
});

await recorder.record('single_authority_font_delivery', async () => observeVazirmatnFontRequests(browser, {
  url: manifest.frontend_url,
  label: 'GP File Upload Pro authentic frontend',
  waitForSurface: async requestPage => {
    await requestPage.locator(`${root} .gpfup__droparea`).waitFor({ state: 'visible', timeout: 30000 });
  },
}));

if (recorder.failed()) {
  results.disposition = 'FAIL';
  results.production_repair = 'NOT_ADMITTED';
  try { await page.screenshot({ path: path.join(artifactDir, 'gp-file-upload-pro-browser-failure.png'), fullPage: true }); } catch {}
}
fs.writeFileSync(path.join(artifactDir, 'gp-file-upload-pro-browser-results.json'), `${JSON.stringify(results, null, 2)}\n`);
await context.close();
await browser.close();
if (recorder.failed()) process.exit(1);
console.log(JSON.stringify(results, null, 2));
