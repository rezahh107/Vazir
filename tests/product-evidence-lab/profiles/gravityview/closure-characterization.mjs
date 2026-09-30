import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { familyOf, login } from '../../core/browser-helpers.mjs';

const baseUrl = process.env.VAZIR_LAB_BASE_URL || 'http://127.0.0.1:8080';
const artifactDir = process.env.VAZIR_LAB_ARTIFACT_DIR;
const user = process.env.VAZIR_LAB_ADMIN_USER || 'vazir_lab_admin';
const password = process.env.VAZIR_LAB_ADMIN_PASSWORD || 'vazir-lab-admin-password';
if (!artifactDir) throw new Error('VAZIR_LAB_ARTIFACT_DIR is required');

const manifest = JSON.parse(fs.readFileSync(path.join(artifactDir, 'gravityview-fixture.json'), 'utf8'));
const sourceEvidence = JSON.parse(fs.readFileSync(path.join(artifactDir, 'gravityview-closure-runtime-results.json'), 'utf8'));
const results = {
  status: 'PASS',
  profile: 'gravityview-closure',
  exact_runtime: sourceEvidence.runtime,
  portal: {},
  oembed: {},
};

const isVazirmatn = family => /Vazirmatn/i.test(family || '');
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));

async function dismissCoreEditorWelcomeGuide(page) {
  const overlay = page.locator('.components-modal__screen-overlay').filter({ hasText: /Welcome to the editor/i }).first();
  if (!(await overlay.count()) || !(await overlay.isVisible())) return false;
  const closeButton = overlay.locator('button[aria-label="Close"]').first();
  if (await closeButton.count()) await closeButton.click();
  else await page.keyboard.press('Escape');
  await overlay.waitFor({ state: 'hidden', timeout: 5000 });
  return true;
}

async function ensureGravityViewInspector(page) {
  await page.goto(manifest.editor_url, { waitUntil: 'domcontentloaded' });
  await page.locator('body.block-editor-page').waitFor({ state: 'visible', timeout: 30000 });
  await page.waitForFunction(
    blockName => {
      const select = window.wp?.data?.select('core/block-editor');
      return Boolean(select?.getBlocks?.().some(block => block.name === blockName));
    },
    manifest.block_name,
    { timeout: 30000 },
  );
  await dismissCoreEditorWelcomeGuide(page);
  await page.evaluate(blockName => {
    const blocks = window.wp.data.select('core/block-editor').getBlocks();
    const block = blocks.find(candidate => candidate.name === blockName);
    if (!block) throw new Error(`GravityView block ${blockName} is not present in the editor store.`);
    window.wp.data.dispatch('core/block-editor').selectBlock(block.clientId);
  }, manifest.block_name);

  const inspector = page.locator('.gk-gravityview-blocks').first();
  if (!(await inspector.count()) || !(await inspector.isVisible())) {
    const settingsButton = page.getByRole('button', { name: /^Settings$/ }).last();
    if (await settingsButton.count()) await settingsButton.click();
    const blockTab = page.getByRole('tab', { name: /^Block$/ }).last();
    if (await blockTab.count() && 'true' !== await blockTab.getAttribute('aria-selected')) await blockTab.click();
  }
  await inspector.waitFor({ state: 'visible', timeout: 30000 });
  return inspector;
}

async function visibleListbox(page) {
  const listboxes = page.locator('[role="listbox"]:visible');
  const count = await listboxes.count();
  return count ? listboxes.nth(count - 1) : null;
}

async function openPortalSemantically(page, root, input) {
  const attempts = [];
  const actions = [
    ['focus+ArrowDown', async () => { await input.focus(); await page.keyboard.press('ArrowDown'); }],
    ['control-click', async () => { await root.locator('[class$="-control"]').first().click(); }],
    ['input-click+ArrowDown', async () => { await input.click(); await page.keyboard.press('ArrowDown'); }],
  ];

  for (const [name, action] of actions) {
    try {
      await action();
      await sleep(500);
      const listbox = await visibleListbox(page);
      attempts.push({ interaction: name, visible_listbox: Boolean(listbox) });
      if (listbox) return { listbox, attempts, successful_interaction: name };
    } catch (error) {
      attempts.push({ interaction: name, visible_listbox: false, error: String(error?.message || error) });
    }
  }
  return { listbox: null, attempts, successful_interaction: null };
}

await login((await chromium.launch()).newPage ? (() => { throw new Error('unreachable'); })() : null, baseUrl, user, password);
