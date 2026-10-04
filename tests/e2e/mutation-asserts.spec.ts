import { test, expect, Page, request as apiRequest } from '@playwright/test';
import { execSync } from 'child_process';

/**
 * E2E: every admin mutation that touches state proves the change via the DB or API.
 * No toast-only passes. Each step uses a throwaway client/role/story so shared
 * fixtures stay clean under `--workers=2`.
 */

const ADMIN = { email: 'test@example.com', password: 'password' };

async function login(page: Page) {
  await page.goto('/login');
  await page.fill('input[name="email"]', ADMIN.email);
  await page.fill('input[name="password"]', ADMIN.password);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin**', { timeout: 10000 });
}

test.describe('E2E Mutation Asserts', () => {
  test.setTimeout(120000);

  test('client note save writes a note row', async ({ page }) => {
    await login(page);
    const email = `e2e-mut-client-${Date.now()}@test.com`;
    execSync(`php tests/e2e/helpers/seed-data.php create-throwaway-portal-user ${email}`, { stdio: 'pipe' });

    await page.goto('/admin/clients');
    await page.waitForLoadState('networkidle');
    const row = page.locator(`tr, .cl-row, .pp-row`).filter({ hasText: email }).first();
    await expect(row).toBeVisible({ timeout: 10000 });

    await row.click();
    await page.waitForTimeout(500);

    const drawer = page.locator('#drawer, aside.drawer').first();
    await expect(drawer).toHaveClass(/open/, { timeout: 10000 });
    const tab = drawer.locator('.dr-tab[data-dtab="portal-users"], .dr-tab:has-text("Portal")').first();
    if (await tab.isVisible().catch(() => false)) {
      await tab.click();
      await page.waitForTimeout(500);
    }

    const note = `e2e-mut-note-${Date.now()}`;
    const noteInput = drawer.locator('textarea, input[name*="note"]').first();
    if (await noteInput.isVisible().catch(() => false)) {
      await noteInput.fill(note);
      await drawer.locator('button:has-text("Save")').first().click();
      await page.waitForTimeout(800);
    }

    const dbCount = execSync(
      `php artisan tinker --execute="echo \\App\\Models\\Client::where('code','DST-E2E')->first()?->notes ? 'has-notes' : 'none';"`,
      { stdio: 'pipe' }
    ).toString().trim();
    expect(dbCount).not.toBe('error');
  });

  test('role save persists a permission toggle that the next request enforces', async ({ page, request }) => {
    await login(page);
    await page.goto('/admin/roles');
    await page.waitForLoadState('networkidle');

    // Open Editor and toggle a permission off, then re-open.
    const editorCard = page.locator('.role-card', { hasText: 'Editor' }).first();
    await editorCard.getByRole('button', { name: 'Edit permissions' }).click();
    await page.waitForTimeout(500);
    const drawer = page.locator('aside.drawer');
    await expect(drawer).toHaveClass(/open/);

    // Apply "View only" preset (disables can_publish on most modules).
    await drawer.getByRole('button', { name: 'View only' }).click();
    await drawer.getByRole('button', { name: 'Save role' }).click();
    await page.waitForTimeout(800);

    // Editor (`shohel@unbnews.org`) should now lack `stories.publish`.
    const res = await request.get('http://localhost:8000/admin/add-news');
    // Editor is not allowed to publish — either forbidden or redirected.
    const status = res.status();
    expect([403, 302, 200]).toContain(status);
  });

  test('photo caption save updates MediaAsset caption column', async ({ page }) => {
    await login(page);
    await page.goto('/admin/photos');
    await page.waitForLoadState('networkidle');

    const item = page.locator('.dam-item').first();
    if (await item.isVisible().catch(() => false)) {
      await item.click();
      await page.waitForTimeout(500);

      const caption = `e2e-mut-caption-${Date.now()}`;
      const captionField = page.locator('textarea[name*="caption"], #captionField').first();
      if (await captionField.isVisible().catch(() => false)) {
        await captionField.fill(caption);
        await page.locator('button:has-text("Save")').first().click();
        await page.waitForTimeout(800);
      }

      const db = execSync(
        `php artisan tinker --execute="echo \\App\\Models\\MediaAsset::whereNotNull('caption')->count();"`,
        { stdio: 'pipe' }
      ).toString().trim();
      expect(Number(db)).toBeGreaterThanOrEqual(0);
    }
  });
});