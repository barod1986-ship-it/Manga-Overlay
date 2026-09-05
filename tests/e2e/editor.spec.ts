import { test, expect, type Page } from '@playwright/test';

async function openProperties(page: Page) {
  if ((page.viewportSize()?.width ?? 1440) <= 700) await page.getByRole('button', { name: 'الخصائص', exact: true }).click();
}
test.beforeEach(async ({ page }) => {
  await page.goto('/');
  await page.evaluate(() => document.fonts.ready);
});

test('shared renderer preserves normalized placement at 360 / 768 / 1440', async ({ page }) => {
  for (const width of [360, 768, 1440]) {
    await page.setViewportSize({ width, height: 1000 });
    const stage = page.locator('.mol-stage');
    const element = page.locator('[data-element-key="bubble-reference"]');
    await expect.poll(async () => {
      const image = await stage.boundingBox(); const box = await element.boundingBox();
      if (!image || !box) return 1;
      return Math.abs((box.x - image.x) / image.width - .6) + Math.abs((box.y - image.y) / image.height - .08);
    }).toBeLessThan(.002);
    const size = await page.evaluate(() => ({ scroll: document.documentElement.scrollWidth, view: window.innerWidth }));
    expect(size.scroll).toBeLessThanOrEqual(size.view);
  }
});
test('toggle and preview retain the original image and use the same renderer', async ({ page }) => {
  const original = await page.locator('.mol-page-image').getAttribute('src');
  await page.getByRole('button', { name: 'إخفاء الترجمة', exact: true }).click();
  await expect(page.locator('.mol-element')).toHaveCount(0);
  await expect(page.locator('.mol-page-image')).toHaveAttribute('src', original!);
  await page.getByRole('button', { name: 'إظهار الترجمة', exact: true }).click();
  await page.getByRole('button', { name: 'معاينة القراءة', exact: true }).click();
  await expect(page.locator('.mol-element')).toHaveCount(4);
  await expect(page.locator('.moveable-control')).toHaveCount(0);
  await expect(page.locator('.mol-properties')).toHaveCount(0);
  await expect(page.locator('.mol-page-image')).toHaveAttribute('src', original!);
});
test('Arabic text stays plain, with no executable markup', async ({ page }) => {
  await openProperties(page);
  const text = 'نص عربي متصل — <img src=x onerror="window.molInjected=true">';
  await page.getByRole('textbox', { name: 'النص العربي' }).fill(text);
  await expect(page.locator('[data-element-key="bubble-reference"] .mol-element-text')).toHaveText(text);
  await expect(page.locator('.mol-element img')).toHaveCount(0);
  expect(await page.evaluate(() => 'molInjected' in window)).toBe(false);
  await expect(page.locator('.mol-session-state')).toContainText('هذه الجلسة فقط');
});
test('creates all types, preserves defaults, duplicates, deletes and restores', async ({ page }) => {
  for (const label of ['فقاعة', 'سرد', 'نص حر', 'مؤثر صوتي']) {
    await page.getByRole('button', { name: `إضافة ${label}`, exact: true }).click();
    await page.getByRole('textbox', { name: 'النص العربي' }).fill(`تجربة ${label}`);
  }
  await expect(page.locator('.mol-element')).toHaveCount(8);
  await page.getByRole('button', { name: 'نسخ العنصر', exact: true }).click();
  await expect(page.locator('.mol-element')).toHaveCount(9);
  await page.getByRole('button', { name: 'حذف العنصر', exact: true }).click();
  await expect(page.locator('.mol-element')).toHaveCount(8);
  await page.getByRole('button', { name: 'تراجع', exact: true }).click();
  await expect(page.locator('.mol-element')).toHaveCount(9);
});
test('numeric transform works without dragging, including after zoom', async ({ page }) => {
  await openProperties(page);
  await page.getByLabel('X (%)', { exact: true }).fill('40');
  await page.getByLabel('العرض (%)', { exact: true }).fill('35');
  await page.getByLabel('الدوران (°)', { exact: true }).fill('12');
  const element = page.locator('[data-element-key="bubble-reference"]');
  await expect(element).toHaveCSS('left', /px$/);
  await expect(element).toHaveAttribute('style', /left: 40%/);
  await expect(element).toHaveAttribute('style', /width: 35%/);
  await expect(element).toHaveAttribute('style', /rotate\(12deg\)/);
  if ((page.viewportSize()?.width ?? 1440) <= 700) await page.getByRole('button', { name: 'إغلاق الخصائص' }).click();
  await page.getByRole('button', { name: 'تكبير الصفحة' }).click();
  await expect(element).toHaveAttribute('style', /left: 40%/);
  await expect(element).toHaveAttribute('style', /width: 35%/);
});
test('mouse drag/resize/rotation update model rather than only temporary CSS', async ({ page, isMobile }) => {
  test.skip(isMobile, 'Physical touch is a separate device gate.');
  await page.getByLabel('محاذاة تلقائية').uncheck();
  const element = page.locator('[data-element-key="bubble-reference"]');
  const box = await element.boundingBox();
  const before = Number(await page.getByLabel('X (%)', { exact: true }).inputValue());
  await page.mouse.move(box!.x + box!.width / 2, box!.y + box!.height / 2);
  await page.mouse.down(); await page.mouse.move(box!.x + box!.width / 2 - 55, box!.y + box!.height / 2 + 30, { steps: 10 }); await page.mouse.up();
  await expect.poll(async () => Number(await page.getByLabel('X (%)', { exact: true }).inputValue())).toBeLessThan(before - 3);
  const resize = page.locator('.moveable-se');
  const handle = await resize.boundingBox();
  const priorWidth = Number(await page.getByLabel('العرض (%)', { exact: true }).inputValue());
  await page.mouse.move(handle!.x + handle!.width / 2, handle!.y + handle!.height / 2);
  await page.mouse.down(); await page.mouse.move(handle!.x + 48, handle!.y + 45, { steps: 10 }); await page.mouse.up();
  await expect.poll(async () => Number(await page.getByLabel('العرض (%)', { exact: true }).inputValue())).toBeGreaterThan(priorWidth + 2);
  const rotation = await page.locator('.moveable-rotation-control').boundingBox();
  await page.mouse.move(rotation!.x + rotation!.width / 2, rotation!.y + rotation!.height / 2);
  await page.mouse.down(); await page.mouse.move(rotation!.x + 60, rotation!.y + 25, { steps: 10 }); await page.mouse.up();
  await expect.poll(async () => Math.abs(Number(await page.getByLabel('الدوران (°)', { exact: true }).inputValue()))).toBeGreaterThan(3);
});
test('keyboard in textarea does not trigger element deletion or duplication', async ({ page }) => {
  await openProperties(page);
  const text = page.getByRole('textbox', { name: 'النص العربي' });
  await text.fill('نص عربي'); await text.press('Delete');
  await expect(page.locator('.mol-element')).toHaveCount(4);
});
