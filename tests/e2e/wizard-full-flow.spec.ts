import { test, expect } from '@playwright/test';

test.describe('Add News Wizard — Full Rebuilt Flow & Interaction Contracts', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name=email]', 'test@example.com');
    await page.fill('input[name=password]', 'password');
    await page.click('button[type=submit]');
    await page.waitForURL('**/admin**');
  });

  test('Step 1 (Write): Form validation, character counter, and Quill toolbar tools', async ({ page }) => {
    await page.goto('/admin/add-news');
    await page.waitForLoadState('networkidle');

    // 1. Verify Stepper and Step 1 initial state
    await expect(page.locator('.stp.active .stp-label')).toHaveText('Write');
    await expect(page.locator('#progressText')).toContainText('Step 1 of 4 — Write');

    // 2. Validate empty headline error on Continue
    await page.click('#nextBtn');
    await page.waitForTimeout(300);
    await expect(page.locator('#headlineError')).toBeVisible();

    // 3. Fill Headline and Brief
    const headline = 'Government announces new renewable energy policy for industrial zones';
    const brief = 'Energy ministry outlines target for 30% solar power in economic zones by 2030.';
    await page.fill('#headlineInput', headline);
    await page.fill('#briefInput', brief);

    // 4. Verify 280-char counter updates
    await expect(page.locator('#briefCount')).toHaveText(String(brief.length));

    // 5. Wire Dateline Toolbar Tool
    const datelineBtn = page.locator('#editorToolbar button.ql-dateline');
    await expect(datelineBtn).toBeVisible();
    await datelineBtn.click();
    await page.waitForTimeout(200);
    const editorContent = await page.locator('.ql-editor').textContent();
    expect(editorContent).toContain('DHAKA');

    // 6. Wire Signoff Toolbar Tool
    const signoffBtn = page.locator('#editorToolbar button.ql-signoff');
    await expect(signoffBtn).toBeVisible();
    await signoffBtn.click();
    await page.waitForTimeout(200);
    const updatedContent = await page.locator('.ql-editor').textContent();
    expect(updatedContent).toContain('END/UNB/');

    // 7. Verify Live Preview reflects headline and brief
    if (await page.locator('#pvStrip').isVisible()) {
      await page.click('#pvStrip');
      await page.waitForTimeout(200);
    }
    await expect(page.locator('#pvBody .pv-headline')).toContainText('Government announces new renewable energy policy');
    await expect(page.locator('#pvBody .pv-brief')).toContainText('Energy ministry outlines target');
  });

  test('Step 1 (Write): Start with AI Hide/Show toggle button and Language selector', async ({ page }) => {
    await page.goto('/admin/add-news');
    await page.waitForLoadState('networkidle');

    // 1. Initial state: AI start box is expanded, toggle text is 'Hide'
    const toggleBtn = page.locator('#aiStartToggle');
    const aiBody = page.locator('#aiStartBody');
    await expect(toggleBtn).toHaveText('Hide');
    await expect(aiBody).toBeVisible();

    // 2. Click 'Hide' -> body becomes hidden, toggle text changes to 'Show'
    await toggleBtn.click();
    await page.waitForTimeout(200);
    await expect(toggleBtn).toHaveText('Show');
    await expect(aiBody).toBeHidden();

    // 3. Click 'Show' -> body becomes visible again, toggle text changes to 'Hide'
    await toggleBtn.click();
    await page.waitForTimeout(200);
    await expect(toggleBtn).toHaveText('Hide');
    await expect(aiBody).toBeVisible();

    // 4. Fill raw notes in textarea
    const aiRaw = page.locator('#aiRaw');
    await aiRaw.fill('Press release from Finance Ministry: Annual tax revenue growth reaches 14% year over year.');
    await expect(aiRaw).toHaveValue(/Finance Ministry/);

    // 5. Language toggle switches between English and Bangla
    const bnLangBtn = page.getByRole('button', { name: 'বাংলা (bn)' });
    const enLangBtn = page.getByRole('button', { name: 'English (en)' });
    await expect(enLangBtn).toHaveClass(/bg-navy-800/);

    await bnLangBtn.click();
    await page.waitForTimeout(400);
    await expect(bnLangBtn).toHaveClass(/bg-navy-800/);
    await expect(enLangBtn).not.toHaveClass(/bg-navy-800/);
  });

  test('Step 1 (Write): Document Import Modal and Table Generator Modal', async ({ page }) => {
    await page.goto('/admin/add-news');
    await page.waitForLoadState('networkidle');

    // 1. Open Import from Doc modal
    await page.click('#openImport');
    await expect(page.locator('#docOverlay')).toHaveClass(/open/);
    await expect(page.locator('#docOverlay .mm-title')).toContainText('Import story from document');

    // Close Doc modal
    await page.click('#closeDoc');
    await expect(page.locator('#docOverlay')).not.toHaveClass(/open/);

    // 2. Open Table Modal from Quill Toolbar
    await page.click('#editorToolbar button.ql-table');
    await expect(page.locator('#tblOverlay')).toHaveClass(/open/);
    await expect(page.locator('#tblPrev')).toBeVisible();

    // Change rows and columns
    await page.fill('#tblRows', '4');
    await page.fill('#tblCols', '2');
    await page.waitForTimeout(100);

    // Insert Table
    await page.click('#tblInsert');
    await expect(page.locator('#tblOverlay')).not.toHaveClass(/open/);
    const editorText = await page.locator('.ql-editor').textContent();
    expect(editorText).toContain('Column 1');
  });

  test('Step 2 (Media): Photo Archive selection, featured image preview, and attached media grid', async ({ page }) => {
    await page.goto('/admin/add-news');
    await page.waitForLoadState('networkidle');

    // Fill required step 1 fields to advance
    await page.fill('#headlineInput', 'Chittagong port achieves record container turnaround time');
    await page.fill('#briefInput', 'Average vessel dwell time drops below 48 hours following automated terminal rollout.');

    // Click Continue to step 2
    await page.click('#nextBtn');
    await page.waitForTimeout(400);

    // Verify Step 2 is active
    await expect(page.locator('.stp.active .stp-label')).toHaveText('Media');
    await expect(page.locator('#progressText')).toContainText('Step 2 of 4 — Media');

    // Open photo archive modal for featured image
    await page.click('#openArchive');
    await expect(page.locator('#mediaOverlay')).toHaveClass(/open/);

    // Select the first photo card
    const firstPhoto = page.locator('.photo-card').first();
    if (await firstPhoto.count() > 0) {
      await firstPhoto.click();
      await expect(firstPhoto).toHaveClass(/selected/);
      await page.click('#insertPhoto');
      await expect(page.locator('#mediaOverlay')).not.toHaveClass(/open/);
      await page.waitForTimeout(300);

      // Verify featured preview has photo state
      await expect(page.locator('#featuredPreview')).toHaveClass(/has-photo/);
    }
  });

  test('Step 3 (Organize & access): Category select, Tags autocomplete, and Exclusive Access Mode', async ({ page }) => {
    await page.goto('/admin/add-news');
    await page.waitForLoadState('networkidle');

    await page.fill('#headlineInput', 'Bangladesh Bank raises policy rate by 50 basis points to curb inflation');
    await page.fill('#briefInput', 'Central bank repo rate now stands at 9.00% effective next week.');

    // Navigate to step 3
    await page.click('.stp[data-go="3"]');
    await page.waitForTimeout(400);

    await expect(page.locator('.stp.active .stp-label')).toHaveText('Organize & access');
    await expect(page.locator('#progressText')).toContainText('Step 3 of 4 — Organize & access');

    // Select Category
    const catSelect = page.locator('#catSelect');
    await catSelect.selectOption({ index: 1 });
    await page.waitForTimeout(200);

    // Add Tag
    const tagInput = page.locator('#tagInput');
    await tagInput.fill('#economy');
    await page.keyboard.press('Enter');
    await page.waitForTimeout(300);
    await expect(page.locator('.tag-chip').first()).toContainText('economy');

    // Toggle Exclusive Access Mode via label click
    await page.locator('#accessSeg label:has-text("Exclusive")').click();
    await page.waitForTimeout(200);
    await expect(page.locator('#exclusiveOpts')).toHaveClass(/show/);

    // Expand Live Preview if collapsed
    if (await page.locator('#pvStrip').isVisible()) {
      await page.click('#pvStrip');
      await page.waitForTimeout(200);
    }

    // Verify Live Preview shows Exclusive Badge
    await expect(page.locator('#pvBody .pv-badge.exc')).toBeVisible();
  });

  test('Step 4 (Review & publish): Review summary rows, internal notes, and publish lifecycle', async ({ page }) => {
    await page.goto('/admin/add-news');
    await page.waitForLoadState('networkidle');

    const testHead = 'National Board of Revenue integrates digital tax system across all zones';
    const testBrief = 'Automated income tax filing system aims for 10 million registered taxpayers.';
    await page.fill('#headlineInput', testHead);
    await page.fill('#briefInput', testBrief);

    // Go to step 3 and select category
    await page.click('.stp[data-go="3"]');
    await page.waitForTimeout(300);
    await page.locator('#catSelect').selectOption({ index: 1 });

    // Go to Step 4
    await page.click('#nextBtn');
    await page.waitForTimeout(400);

    await expect(page.locator('.stp.active .stp-label')).toHaveText('Review & publish');
    await expect(page.locator('#progressText')).toContainText('Step 4 of 4 — Review & publish');

    // Verify Review Card content
    await expect(page.locator('#reviewCard')).toBeVisible();
    await expect(page.locator('#reviewRows')).toContainText(testHead);

    // Add internal note
    await page.fill('#ntInput', 'Checked numbers against NBR official gazette notification.');
    await page.click('#ntSend');
    await page.waitForTimeout(600);
    await expect(page.locator('#ntList')).toContainText('Checked numbers against NBR official gazette notification.');

    // Publish story
    await page.click('#publishBtn');
    await page.waitForTimeout(800);

    // Verify success card is displayed
    await expect(page.locator('#successCard')).toHaveClass(/show/);
    await expect(page.locator('.success-title').first()).toHaveText('Story published');

    // E2E assertion: published story actually appears in the admin News list
    // (proves the server-side publish ran, not just a fake DOM success card)
    await page.goto('/admin/news/en');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('text=' + testHead).first()).toBeVisible({ timeout: 10000 });

    // E2E assertion: published story appears in the public portal feed API
    const feedResponse = await page.request.get('/api/v1/portal/feed');
    expect(feedResponse.status()).toBe(200);
    const feedJson = await feedResponse.json();
    const found = (feedJson.data || []).some((s: { headline: string }) => s.headline === testHead);
    expect(found).toBe(true);
  });

  test('Live Preview Rail: Collapsing toggle, expansion button, and Device Focus Emulator', async ({ page }) => {
    await page.goto('/admin/add-news');
    await page.waitForLoadState('networkidle');

    // 1. By default, live preview is collapsed per prototype contract
    await expect(page.locator('#addGrid')).toHaveClass(/pv-collapsed/);
    await expect(page.locator('#pvStrip')).toBeVisible();

    // 2. Expand preview via strip button
    await page.click('#pvStrip');
    await page.waitForTimeout(200);
    await expect(page.locator('#addGrid')).not.toHaveClass(/pv-collapsed/);

    // 3. Collapse back via topbar preview toggle button
    await page.click('#pvToggleBtn');
    await page.waitForTimeout(200);
    await expect(page.locator('#addGrid')).toHaveClass(/pv-collapsed/);

    // 4. Expand again and open Fullscreen Device Focus Overlay
    await page.click('#pvStrip');
    await page.waitForTimeout(200);
    await page.click('#pvExpand');
    await expect(page.locator('#pvOverlay')).toHaveClass(/open/);

    // 5. Switch to Tablet and Mobile device widths
    await page.click('button[data-dev="tablet"]');
    await expect(page.locator('#pvFocus')).toHaveClass(/dev-tablet/);

    await page.click('button[data-dev="mobile"]');
    await expect(page.locator('#pvFocus')).toHaveClass(/dev-mobile/);

    // 6. Close overlay
    await page.click('#pvFocusClose');
    await expect(page.locator('#pvOverlay')).not.toHaveClass(/open/);
  });
});
