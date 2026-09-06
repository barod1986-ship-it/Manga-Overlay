import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
// The disposable fixture also holds HTTP credentials; tests never attach or log it.
const fixture = JSON.parse(readFileSync(process.env.MOL_HTTP_FIXTURE!, 'utf8')) as {
  reader_url: string; reader_ltr_url: string; draft_reader_url: string;
  reader_work_url: string; reader_work_id: number; reader_chapter_id: number;
};

test('server-rendered library filters, work chapters and draft protection', async ({ page }) => {
  await page.goto('/library/?search=Reader+alternate+unique');
  await expect(page.locator('.mol-work-item')).toHaveCount(1);
  await page.locator('.mol-work-item a').click();
  await expect(page.locator('h1')).toContainText('رحلة بين الصفحات');
  await expect(page.locator('.mol-chapter-list li')).toHaveCount(2);
  const response = await page.goto(fixture.draft_reader_url);
  expect(response?.status()).toBe(404);
  await expect(page.locator('#mol-reader-data')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('فصل مسودة لا يظهر');
});

test('reader overlays toggle without replacing or requesting original images again', async ({ page }, testInfo) => {
  const errors: string[] = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.goto(fixture.reader_url);
  const toggle = page.locator('#mol-reader-toggle');
  await expect(toggle).toBeEnabled();
  await expect(page.locator('.mol-element-text').first()).toContainText('هنا تبدأ حكايتنا');
  await page.evaluate(async () => { await document.fonts.ready; });
  const image = page.locator('.mol-reader-page > img').first();
  await image.evaluate(node => { node.setAttribute('data-original-node', 'preserved'); });
  const originalSrc = await image.evaluate(node => (node as HTMLImageElement).currentSrc);
  const requests: string[] = [];
  page.on('request', request => { if (request.resourceType() === 'image') requests.push(request.url()); });
  await toggle.click();
  await expect(page.locator('.mol-reader-overlay').first()).toBeHidden();
  await expect(toggle).toHaveAttribute('aria-pressed', 'false');
  await expect(image).toHaveAttribute('data-original-node', 'preserved');
  await toggle.click();
  await expect(page.locator('.mol-reader-overlay').first()).toBeVisible();
  await expect(image).toHaveAttribute('data-original-node', 'preserved');
  expect(requests.filter(url => url === originalSrc)).toHaveLength(0);
  expect(errors).toEqual([]);
  await page.screenshot({ path: testInfo.outputPath('reader.png'), fullPage: false });
});

test('paged reader follows RTL keys, restores guest progress, resets zoom and respects LTR override', async ({ page }) => {
  await page.goto(fixture.reader_url);
  await expect(page.locator('#mol-reader-toggle')).toBeEnabled();
  await page.locator('#mol-reader-mode').selectOption('paged');
  await page.locator('#mol-zoom-in').click();
  await expect(page.locator('#mol-zoom-reset')).toHaveText('125%');
  await page.locator('h1').click();
  await page.keyboard.press('ArrowLeft');
  await expect(page.locator('.mol-reader-page:visible')).toHaveAttribute('data-page-index', '1');
  await expect(page.locator('#mol-zoom-reset')).toHaveText('100%');
  await page.reload();
  await expect(page.locator('#mol-reader-mode')).toHaveValue('paged');
  await expect(page.locator('.mol-reader-page:visible')).toHaveAttribute('data-page-index', '1');
  await page.goto(fixture.reader_ltr_url);
  await expect(page.locator('#mol-reader-toggle')).toBeEnabled();
  await page.locator('h1').click();
  await page.keyboard.press('ArrowRight');
  await expect(page.locator('.mol-reader-page:visible')).toHaveAttribute('data-page-index', '1');
});

test('reader controls fit small screens and preferences survive reload', async ({ page }, testInfo) => {
  await page.goto(fixture.reader_url);
  await expect(page.locator('#mol-reader-toggle')).toBeEnabled();
  await page.locator('#mol-reader-toggle').click();
  await page.reload();
  await expect(page.locator('#mol-reader-toggle')).toHaveAttribute('aria-pressed', 'false');
  await page.locator('#mol-hide-toolbar').click();
  await expect(page.locator('#mol-reader-toolbar')).toBeHidden();
  await page.locator('#mol-show-toolbar').click();
  await expect(page.locator('#mol-reader-toolbar')).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
  await page.screenshot({ path: testInfo.outputPath('reader-controls.png'), fullPage: false });
});
