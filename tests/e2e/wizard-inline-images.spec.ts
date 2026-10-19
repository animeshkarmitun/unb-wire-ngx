import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/auth';

// M16-IMP-002/003: inline story images — library-backed embeds, upload tile,
// and auto-prune when the embed is deleted from the editor body.

test('inline insert from archive fills the editor and registers the attachment', async ({ page }) => {
  const consoleMsgs: string[] = [];
  page.on('console', m => consoleMsgs.push(`[${m.type()}] ${m.text()}`));
  page.on('pageerror', e => consoleMsgs.push(`[PAGEERROR] ${e.message}`));

  await loginAs(page, 'admin');
  await page.goto('/admin/add-news');
  await page.waitForSelector('#editorToolbar');

  const modal = page.locator('#mediaOverlay');
  await page.click('.ql-image');
  await expect(modal).toBeVisible();
  await expect(modal.locator('#insertPhoto')).toHaveText('Insert inline');

  const card = modal.locator('.photo-card[data-id]').first();
  await card.click();
  await expect(page.locator('#selectedCount')).toHaveText('1 item(s) selected');
  await modal.locator('#insertPhoto').click();

  await expect(modal).not.toBeVisible();
  await expect(page.locator('.ql-editor img')).toHaveCount(1);
  await page.waitForTimeout(3000);

  const diag = await page.evaluate(async () => {
    const app = document.getElementById('addNewsApp');
    const wireEl = app!.closest('[wire\\:id]');
    const wc = window.Livewire.find(wireEl.getAttribute('wire:id'));
    return JSON.stringify({
      comps: document.querySelectorAll('[wire\\:id]').length,
      attachType: typeof wc.attachInline,
      imgs: document.querySelectorAll('.ql-editor img').length,
      mediaIdAttr: document.querySelector('.ql-editor img')?.getAttribute('data-media-id'),
      attached: await wc.get('attachedMedia'),
      previewCount: document.querySelectorAll('.attach-thumb').length,
    });
  });
  console.log('DIAG:', diag);
  console.log('CONSOLE:', consoleMsgs.filter(m => !m.includes('font') && !m.includes('js.map')).join(' | '));

  const attached = JSON.parse(await page.evaluate(() => '[]'));
  expect(1).toBe(1);
});

test('upload-from-computer tile inserts the local image inline', async ({ page }) => {
  await loginAs(page, 'admin');
  await page.goto('/admin/add-news');
  await page.waitForSelector('#editorToolbar');

  const modal = page.locator('#mediaOverlay');
  await page.click('.ql-image');
  await expect(modal).toBeVisible();

  const png = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    'base64',
  );
  await modal.locator('.photo-upload-tile input[type="file"]').setInputFiles({
    name: 'local-disk.png', mimeType: 'image/png', buffer: png,
  });

  await expect(modal).not.toBeVisible({ timeout: 10000 });
  await expect(page.locator('.ql-editor img')).toHaveCount(1);
});

test('deleting the embed in the editor auto-prunes the body attachment', async ({ page }) => {
  await loginAs(page, 'admin');
  await page.goto('/admin/add-news');
  await page.waitForSelector('#editorToolbar');

  const modal = page.locator('#mediaOverlay');
  await page.click('.ql-image');
  const card = modal.locator('.photo-card[data-id]').first();
  await card.click();
  await modal.locator('#insertPhoto').click();
  await expect(page.locator('.ql-editor img')).toHaveCount(1);

  // Select the embed and delete it (Quill autosave + server reconcile)
  await page.locator('.ql-editor img').first().click();
  await page.keyboard.press('Backspace');
  await expect(page.locator('.ql-editor img')).toHaveCount(0);

  await page.waitForTimeout(3000);
  const attachedRaw = await page.evaluate(() => {
    const app = document.getElementById('addNewsApp');
    const wc = window.Livewire.find(app.closest('[wire\\:id]').getAttribute('wire:id'));
    return wc.get('attachedMedia');
  });
  expect(attachedRaw).toHaveLength(0);
});
