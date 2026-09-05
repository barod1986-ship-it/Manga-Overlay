import { expect, test, type Page } from '@playwright/test';

async function dragBy(
  page: Page,
  selector: string,
  deltaX: number,
  deltaY: number,
): Promise<void> {
  const target = page.locator(selector);
  const box = await target.boundingBox();
  expect(box).not.toBeNull();
  if (box === null) {
    return;
  }

  const startX = box.x + box.width / 2;
  const startY = box.y + box.height / 2;
  await page.mouse.move(startX, startY);
  await page.mouse.down();
  await page.mouse.move(startX + deltaX, startY + deltaY, { steps: 8 });
  await page.mouse.up();
}

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
  expect(geometry.transform).toContain('rotate(12deg)');
});

test('commits a pointer drag back to normalized state', async ({ page, browserName }) => {
  test.skip(browserName === 'webkit', 'Desktop mouse path is covered in Chromium and Firefox.');
  const bubble = page.locator('[data-element-id="201"]');
  await bubble.click();
  const before = Number(await page.locator('[data-transform-field="x_unit"]').inputValue());
  const requests: string[] = [];
  page.on('request', (request) => requests.push(request.url()));

  await dragBy(page, '[data-element-id="201"]', 36, 18);

  await expect.poll(async () => Number(await page.locator('[data-transform-field="x_unit"]').inputValue()))
    .toBeGreaterThan(before);
  expect(requests).toEqual([]);
});

test('commits Moveable resize and rotation handles', async ({ page, browserName }) => {
  test.skip(browserName !== 'chromium', 'Precise control-handle path runs once; state alternatives run in every engine.');
  const bubble = page.locator('[data-element-id="201"]');
  await bubble.click();

  const widthField = page.locator('[data-transform-field="w_unit"]');
  const widthBefore = Number(await widthField.inputValue());
  await dragBy(page, '.moveable-control.moveable-e', 32, 0);
  await expect.poll(async () => Number(await widthField.inputValue())).toBeGreaterThan(widthBefore);

  const rotationField = page.locator('[data-transform-field="rotation_mdeg"]');
  const bubbleBox = await bubble.boundingBox();
  const rotationHandle = page.locator('.moveable-rotation-control');
  const handleBox = await rotationHandle.boundingBox();
  expect(bubbleBox).not.toBeNull();
  expect(handleBox).not.toBeNull();

  if (bubbleBox !== null && handleBox !== null) {
    const centerX = bubbleBox.x + bubbleBox.width / 2;
    const centerY = bubbleBox.y + bubbleBox.height / 2;
    const startX = handleBox.x + handleBox.width / 2;
    const startY = handleBox.y + handleBox.height / 2;
    const radius = Math.max(Math.hypot(startX - centerX, startY - centerY), 30);
    await page.mouse.move(startX, startY);
    await page.mouse.down();
    await page.mouse.move(centerX + radius * 0.7, centerY - radius * 0.7, { steps: 10 });
    await page.mouse.up();
  }

  await expect.poll(async () => Math.abs(Number(await rotationField.inputValue()))).toBeGreaterThan(5);
});

test('zooms with explicit controls and exposes fit width', async ({ page }) => {
  const viewport = page.locator('.mol-stage-viewport');
  await page.getByRole('button', { name: 'تكبير', exact: true }).click();
  await expect(viewport).toHaveAttribute('data-zoom', '1.10');
  await expect(page.getByTestId('stage-zoom')).toHaveText('110%');

  await page.getByRole('button', { name: 'ملاءمة' }).click();
  const fittedZoom = Number(await viewport.getAttribute('data-zoom'));
  expect(fittedZoom).toBeGreaterThanOrEqual(0.65);
  expect(fittedZoom).toBeLessThanOrEqual(2.25);
});

test('uses the mobile toolbar and properties bottom sheet on touch viewports', async ({ page, isMobile }) => {
  test.skip(!isMobile, 'Mobile layout assertion.');

  const toolbar = page.locator('.mol-mobile-toolbar');
  const properties = page.getByTestId('properties-panel');
  await expect(toolbar).toBeVisible();
  await expect(properties).toHaveAttribute('data-mobile-open', 'false');
  await toolbar.getByRole('button', { name: 'تحديد' }).click();
  await expect(properties).toHaveAttribute('data-mobile-open', 'true');
  await expect(properties).toHaveAttribute('data-mobile-size', 'compact');
  const compactBox = await properties.boundingBox();
  await page.getByRole('button', { name: 'توسيع' }).click();
  await expect(properties).toHaveAttribute('data-mobile-size', 'expanded');
  const expandedBox = await properties.boundingBox();
  expect(compactBox).not.toBeNull();
  expect(expandedBox).not.toBeNull();
  expect(expandedBox?.height ?? 0).toBeGreaterThan(compactBox?.height ?? 0);
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
