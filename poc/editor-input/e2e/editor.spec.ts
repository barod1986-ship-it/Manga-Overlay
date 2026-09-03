import { expect, test } from '@playwright/test';

test.beforeEach(async ({ page }) => {
  await page.goto('/');
  await page.locator('.mol-editor-page-frame > img').evaluate(async (image: HTMLImageElement) => image.decode());
  await expect(page.locator('[data-element-id="201"]')).toBeVisible();
});

test('edits Arabic content safely through the textarea', async ({ page, isMobile }) => {
  const bubble = page.locator('[data-element-id="201"]');
  const textarea = page.getByTestId('content-input');
  const updatedText = 'النص العربي يعمل على الهاتف وسطح المكتب.';

  await bubble.click();
  if (isMobile) {
    await page.locator('.mol-mobile-toolbar').getByRole('button', { name: 'تحديد' }).click();
  }
  await textarea.fill(updatedText);

  await expect(bubble.locator('.mol-element-text')).toHaveText(updatedText);
  await expect(bubble.locator('.mol-element-text')).toHaveCSS('direction', 'rtl');
  await expect(bubble.locator('script')).toHaveCount(0);
});

test('offers numeric geometry alternatives and Moveable controls', async ({ page, isMobile }) => {
  const bubble = page.locator('[data-element-id="201"]');
  await bubble.click();
  if (isMobile) {
    await page.locator('.mol-mobile-toolbar').getByRole('button', { name: 'تحديد' }).click();
  }

  await expect(page.locator('.moveable-control-box')).toBeVisible();
  await page.locator('[data-transform-field="x_unit"]').fill('40');
  await page.locator('[data-transform-field="rotation_mdeg"]').fill('12');

  const geometry = await bubble.evaluate((node) => {
    const element = node as HTMLElement;
    return { left: element.style.left, transform: element.style.transform };
  });
  expect(geometry.left).toBe('40%');
  expect(geometry.transform).toBe('rotate(12deg)');
});

test('commits a pointer drag back to normalized state', async ({ page, browserName }) => {
  test.skip(browserName === 'webkit', 'Desktop mouse path is covered in Chromium and Firefox.');
  const bubble = page.locator('[data-element-id="201"]');
  await bubble.click();
  const before = Number(await page.locator('[data-transform-field="x_unit"]').inputValue());
  const box = await bubble.boundingBox();
  expect(box).not.toBeNull();

  if (box !== null) {
    await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
    await page.mouse.down();
    await page.mouse.move(box.x + box.width / 2 + 36, box.y + box.height / 2 + 18, { steps: 5 });
    await page.mouse.up();
  }

  await expect.poll(async () => Number(await page.locator('[data-transform-field="x_unit"]').inputValue()))
    .toBeGreaterThan(before);
});

test('uses the mobile toolbar and properties bottom sheet on touch viewports', async ({ page, isMobile }) => {
  test.skip(!isMobile, 'Mobile layout assertion.');

  const toolbar = page.locator('.mol-mobile-toolbar');
  const properties = page.getByTestId('properties-panel');
  await expect(toolbar).toBeVisible();
  await expect(properties).toHaveAttribute('data-mobile-open', 'false');
  await toolbar.getByRole('button', { name: 'تحديد' }).click();
  await expect(properties).toHaveAttribute('data-mobile-open', 'true');
  await expect(page.getByTestId('content-input')).toBeVisible();

  await page.getByRole('button', { name: 'إغلاق الخصائص' }).click();
  await expect(properties).toHaveAttribute('data-mobile-open', 'false');
  await toolbar.getByRole('button', { name: 'الطبقات' }).click();
  await expect(page.getByTestId('layers-panel')).toHaveAttribute('data-mobile-open', 'true');

  const targetSizes = await toolbar.locator('button').evaluateAll((buttons) => buttons.map((button) => {
    const box = button.getBoundingClientRect();
    return { width: box.width, height: box.height };
  }));
  expect(targetSizes.every(({ width, height }) => width >= 44 && height >= 44)).toBe(true);
});
