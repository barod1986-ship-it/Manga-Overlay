import { test, expect } from '@playwright/test';
import { fixture } from './editor-fixture';

test.beforeEach(async ({ page }) => {
  await page.request.get('/wp-login.php');
  const response = await page.request.post('/wp-login.php', {
    form: { log: fixture.username, pwd: fixture.password, 'wp-submit': 'Log In', testcookie: '1' },
    maxRedirects: 0,
  });
  expect(response.status()).toBe(302);
});

test('both admin screens initialize when the host does not recognize mjs', async ({ page }) => {
  await page.route(/\.mjs(?:\?|$)/, route => route.fulfill({
    status: 200, contentType: 'application/octet-stream', body: '// Host has no mjs MIME mapping.',
  }));
  await page.goto('/wp-admin/admin.php?page=manga-overlay&work_id=' + fixture.reader_work_id);
  await expect(page.locator('#mol-save-chapter')).toBeEnabled();
  await page.locator('#mol-chapter').selectOption({ value: String(fixture.reader_chapter_id) });
  await expect(page.locator('#mol-pages img')).toHaveCount(3);
  await expect(page.locator('#mol-open-editor')).toBeVisible();
  await page.goto('/wp-admin/admin.php?page=mol-reports');
  await expect(page.locator('#mol-reports-list')).toHaveAttribute('aria-busy', 'false');
  await expect(page.locator('#mol-reports-count')).not.toBeEmpty();
});

test('chapter form cannot submit without its administration module', async ({ page }) => {
  await page.route('**/assets/dist/admin/content.js*', route => route.abort());
  await page.goto('/wp-admin/admin.php?page=manga-overlay&work_id=' + fixture.reader_work_id);
  await expect(page.getByRole('textbox', { name: 'تسمية الفصل', exact: true })).toBeDisabled();
  await expect(page.getByRole('button', { name: 'حفظ الفصل', exact: true })).toBeDisabled();
  await expect(page.getByRole('status')).toContainText('جارٍ تحميل أدوات الإدارة');
});
