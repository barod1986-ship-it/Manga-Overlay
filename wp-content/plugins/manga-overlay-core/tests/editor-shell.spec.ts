import { expect, test } from '@playwright/test';

test('loads the authorized editor shell, routes pages, and keeps content safe', async ({ page }) => {
  await page.goto('/tests/editor-shell-fixture.html?mol_page=1');

  await expect(page.getByTestId('editor-shell')).toBeVisible();
  await expect(page.locator('#mol-editor-root')).toHaveAttribute('dir', 'rtl');
  await expect(page.getByText('بداية الحكاية', { exact: true })).toBeVisible();
  await expect(page.getByText('عرض فقط · الحفظ غير مفعّل بعد')).toBeVisible();
  await expect(
    page.locator('.mol-editor-toolbar').getByRole('button', { name: 'فقاعة', exact: true }),
  ).toBeDisabled();

  const firstOutline = page.getByTestId('stage-element-101');
  await expect(firstOutline).toBeVisible();
  expect(await firstOutline.evaluate((node) => (node as HTMLElement).style.left)).toBe('10%');
  expect(await firstOutline.evaluate((node) => (node as HTMLElement).style.top)).toBe('16%');

  await page.getByTestId('layer-102').click();
  await expect(page.getByTestId('property-content')).toHaveValue(
    '<img src=x onerror="window.__molXss=true">نص آمن',
  );
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
  await expect(page.locator('.mol-editor-element-outline')).toHaveCount(0);
});
