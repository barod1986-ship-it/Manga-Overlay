import { expect, test } from '@playwright/test';

test('loads T-11 safely, routes pages, and previews the reader renderer', async ({ page }) => {
  await page.goto('/tests/editor-shell-fixture.html?mol_page=1');

  await expect(page.getByTestId('editor-shell')).toBeVisible();
  await expect(page.locator('#mol-editor-root')).toHaveAttribute('dir', 'rtl');
  await expect(page.getByText('بداية الحكاية', { exact: true })).toBeVisible();
  await expect(page.getByText('جاهز للتحرير محليًا · الحفظ في T‑12')).toBeVisible();
  await expect(page.getByTestId('add-bubble')).toBeEnabled();

  const firstElement = page.getByTestId('stage-element-101');
  await expect(firstElement).toBeVisible();
  expect(await firstElement.evaluate((node) => (node as HTMLElement).style.left)).toBe('10%');
  expect(await firstElement.evaluate((node) => (node as HTMLElement).style.top)).toBe('16%');
  await expect(firstElement.locator('.mol-element-shape')).toBeVisible();
  await expect(firstElement.locator('.mol-element-text')).toHaveText('مرحبا من الفقاعة');

  await page.getByTestId('layer-102').click();
  await expect(page.getByTestId('property-content')).toHaveValue(
    '<img src=x onerror="window.__molXss=true">نص آمن',
  );
  await expect(page.getByTestId('stage-element-102').locator('.mol-element-text')).toContainText('نص آمن');
  expect(await page.evaluate(() => (window as Window & { __molXss?: boolean }).__molXss)).toBe(false);
  await expect(page.locator('img[src="x"]')).toHaveCount(0);

  await page.getByTestId('next-page').click();
  await expect(page).toHaveURL(/\?mol_page=2$/);
  await expect(page.getByText('صفحة 2 من 2')).toBeVisible();
  await expect(page.getByText('لا توجد عناصر ترجمة في هذه الصفحة.')).toBeVisible();
  await page.reload();
  await expect(page.getByText('صفحة 2 من 2')).toBeVisible();
  await page.goBack();
  await expect(page.getByText('صفحة 1 من 2')).toBeVisible();

  await page.getByRole('button', { name: 'معاينة' }).click();
  await expect(page.getByRole('button', { name: 'العودة إلى المحرر' })).toBeVisible();
  await expect(page.locator('.mol-editor-toolbar')).toHaveCount(0);
  await expect(page.locator('.mol-editor-properties')).toHaveCount(0);
  await expect(page.locator('.moveable-control-box')).toHaveCount(0);
  await expect(page.getByTestId('stage-element-101')).toBeVisible();
  await expect(page.getByTestId('stage-element-101').locator('.mol-element-shape')).toBeVisible();
});

test('creates, edits, duplicates, reorders, and deletes all four local element types', async ({ page }) => {
  await page.goto('/tests/editor-shell-fixture.html?mol_page=2');

  for (const type of ['bubble', 'narration', 'free_text', 'sfx'] as const) {
    await page.getByTestId(`add-${type}`).click();
  }

  const overlay = page.getByTestId('overlay-layer');
  await expect(overlay.locator('.mol-overlay-element')).toHaveCount(4);
  for (const type of ['bubble', 'narration', 'free_text', 'sfx']) {
    await expect(overlay.locator(`[data-element-type="${type}"]`)).toHaveCount(1);
  }
  await expect(page.getByText('تغييرات محلية · الحفظ في T‑12')).toBeVisible();

  const selected = overlay.locator('[data-element-type="sfx"]');
  await expect(selected).toHaveAttribute('data-selected', 'true');
  await page.getByTestId('property-content').fill('طَقّ!');
  await page.getByTestId('shape-select').selectOption('burst');
  await page.getByTestId('stroke-color').fill('#cc0000');
  await expect(selected.locator('.mol-element-text')).toHaveText('طَقّ!');
  await expect(selected.locator('svg polygon')).toHaveCount(1);
  await expect(selected.locator('.mol-element-text')).toHaveCSS('-webkit-text-stroke-color', 'rgb(204, 0, 0)');

  await page.getByRole('button', { name: 'طبقة لأسفل' }).click();
  await page.getByRole('button', { name: 'نسخ العنصر' }).click();
  await expect(overlay.locator('.mol-overlay-element')).toHaveCount(5);
  await page.getByRole('button', { name: 'حذف العنصر' }).click();
  await expect(overlay.locator('.mol-overlay-element')).toHaveCount(4);
});

test('commits Moveable drag/resize and supports numeric and keyboard alternatives', async ({ page }) => {
  await page.goto('/tests/editor-shell-fixture.html?mol_page=1');
  const originalElement = await page.getByTestId('stage-element-101').elementHandle();
  if (originalElement === null) throw new Error('Element handle is missing.');
  await originalElement.click();
  await expect.poll(async () => originalElement.evaluate((node) => node.isConnected)).toBe(true);
  await expect.poll(async () => originalElement.getAttribute('data-selected')).toBe('true');
  await expect(page.locator('.moveable-control-box')).toBeVisible();
  await expect(page.locator('.moveable-rotation-control')).toBeVisible();

  const element = page.getByTestId('stage-element-101');
  const beforeDrag = await element.boundingBox();
  if (beforeDrag === null) throw new Error('Element box is missing.');
  await page.mouse.move(beforeDrag.x + beforeDrag.width / 2, beforeDrag.y + beforeDrag.height / 2);
  await page.mouse.down();
  await page.mouse.move(beforeDrag.x + beforeDrag.width / 2 + 36, beforeDrag.y + beforeDrag.height / 2 + 22, { steps: 5 });
  await page.mouse.up();
  await expect.poll(async () => Number(await page.getByTestId('geometry-x_unit').inputValue())).toBeGreaterThan(10);

  const eastHandle = page.locator('.moveable-control.moveable-e');
  const eastBox = await eastHandle.boundingBox();
  if (eastBox === null) throw new Error('Resize handle is missing.');
  const widthBefore = Number(await page.getByTestId('geometry-w_unit').inputValue());
  await page.mouse.move(eastBox.x + eastBox.width / 2, eastBox.y + eastBox.height / 2);
  await page.mouse.down();
  await page.mouse.move(eastBox.x + eastBox.width / 2 + 32, eastBox.y + eastBox.height / 2, { steps: 5 });
  await page.mouse.up();
  await expect.poll(async () => Number(await page.getByTestId('geometry-w_unit').inputValue())).toBeGreaterThan(widthBefore);

  await page.getByTestId('geometry-rotation').fill('18');
  expect(await element.evaluate((node) => (node as HTMLElement).style.transform)).toContain('rotate(18deg)');
  const beforeKeyboard = Number(await page.getByTestId('geometry-x_unit').inputValue());
  await element.click();
  await page.keyboard.press('ArrowRight');
  await expect.poll(async () => Number(await page.getByTestId('geometry-x_unit').inputValue())).toBeGreaterThan(beforeKeyboard);
});
