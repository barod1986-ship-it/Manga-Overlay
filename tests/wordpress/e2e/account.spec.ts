import { test, expect, type Page } from '@playwright/test';
import { readFileSync } from 'node:fs';
const fixture = JSON.parse(readFileSync(process.env.MOL_HTTP_FIXTURE!, 'utf8')) as {
  username: string; password: string; reader_url: string; reader_work_id: number; reader_chapter_id: number;
};
async function login(page: Page) {
  await page.goto('/wp-login.php');
  await page.locator('#user_login').fill(fixture.username);
  await page.locator('#user_pass').fill(fixture.password);
  await page.locator('#wp-submit').click();
  await page.waitForURL('**/wp-admin/**');
}

test('manager reorders pages numerically and reload proves persistence', async ({ page }) => {
  await login(page);
  const adminUrl = '/wp-admin/admin.php?page=manga-overlay&work_id=' + fixture.reader_work_id;
  await page.goto(adminUrl);
  await page.locator('#mol-chapter').selectOption(String(fixture.reader_chapter_id));
  const positions = page.locator('#mol-pages input[type=number]');
  await expect(positions).toHaveCount(3);
  const originalImage = await page.locator('#mol-pages img').first().getAttribute('src');
  await positions.first().fill('3');
  await positions.first().press('Tab');
  await expect(page.locator('#mol-save-order')).toBeEnabled();
  await page.locator('#mol-save-order').click();
  await expect(page.locator('#mol-save-order')).toBeDisabled();
  await page.goto(adminUrl);
  await page.locator('#mol-chapter').selectOption(String(fixture.reader_chapter_id));
  await expect(page.locator('#mol-pages img').last()).toHaveAttribute('src', originalImage!);
  // Restore fixture order for the independent public-reader scenarios.
  await positions.last().fill('1');
  await positions.last().press('Tab');
  await page.locator('#mol-save-order').click();
  await expect(page.locator('#mol-save-order')).toBeDisabled();
});

test('signed-in reader persists progress to the current account and restores it', async ({ page }) => {
  await login(page);
  await page.goto(fixture.reader_url);
  await expect(page.locator('#mol-reader-toggle')).toBeEnabled();
  await page.locator('#mol-reader-mode').selectOption('paged');
  await page.locator('#mol-reader-page-select').selectOption('0');
  await page.locator('#mol-reader-page-select').selectOption('2');
  await expect(page.locator('#mol-reader-status')).toHaveText('تم حفظ موضع القراءة', { timeout: 10000 });
  await page.reload();
  await expect(page.locator('#mol-reader-mode')).toHaveValue('paged');
  await expect(page.locator('.mol-reader-page:visible')).toHaveAttribute('data-page-index', '2');
  const stored = await page.locator('#mol-reader-data').textContent();
  const progress = JSON.parse(stored!).progress as { chapter_id: number; page_index: number };
  expect(progress.chapter_id).toBe(fixture.reader_chapter_id);
  expect(progress.page_index).toBe(2);
});
