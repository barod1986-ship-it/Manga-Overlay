import { test, expect, type Page } from '@playwright/test';
import { fixture, managerApi, readElements, resetEditor } from './editor-fixture';
const pageId = fixture.editor_page_ids[0] as number;
const bubble = fixture.editor_element_ids[0] as number;
test.beforeEach(async ({ playwright }) => resetEditor(playwright));
async function open(page: Page, mobile: boolean) {
  await page.request.get('/wp-login.php');
  expect((await page.request.post('/wp-login.php', { form: { log: fixture.editor_username, pwd: fixture.editor_password, testcookie: '1', 'wp-submit': 'Log In' }, maxRedirects: 0 })).status()).toBe(302);
  await page.goto(fixture.editor_url);
  await expect(page.locator('.mol-element')).toHaveCount(4);
  if (mobile) await page.getByRole('button', { name: 'الطبقات', exact: true }).click();
  await page.locator('.mol-editor-layers button').filter({ hasText: 'نص خاص داخل المحرر' }).click();
  return JSON.parse((await page.locator('#mol-editor-data').textContent())!);
}

test('autosave confirms the server version and survives reload; new element retries cannot duplicate it', async ({ page, isMobile }) => {
  const boot = await open(page, isMobile);
  const original = (await readElements(page.request, boot.api, boot.nonce, pageId)).find((e: any) => e.id === bubble);
  await expect(page.getByLabel('النص العربي', { exact: true })).toBeEditable();
  const saved = page.waitForResponse(response => response.request().method() === 'PATCH' && response.url().endsWith('/elements/' + bubble));
  await page.getByLabel('النص العربي', { exact: true }).fill('ترجمة محفوظة بعد إعادة التحميل');
  const response = await saved;
  expect(response.status()).toBe(200); expect(response.headers().etag).toBe(`"${original.version + 1}"`);
  await expect(page.locator('.mol-editor-save-state')).toContainText('تم الحفظ');
  await page.reload();
  await expect(page.getByLabel('النص العربي', { exact: true })).toHaveValue('ترجمة محفوظة بعد إعادة التحميل');
  // Commit the first POST on WordPress, then lose only its response on the client.
  const posts: { body: string | null; key: string | undefined }[] = [];
  let lost = false;
  await page.route('**/mol/v1/elements', async route => {
    if (route.request().method() !== 'POST') return route.continue();
    posts.push({ body: route.request().postData(), key: route.request().headers()['mol-idempotency-key'] });
    if (!lost) { lost = true; const committed = await route.fetch(); expect(committed.status()).toBe(201); await route.abort('failed'); }
    else await route.continue();
  });
  if (isMobile) await page.getByRole('button', { name: 'إغلاق الخصائص', exact: true }).click();
  await page.getByRole('button', { name: 'إضافة سرد', exact: true }).click();
  await page.getByLabel('النص العربي', { exact: true }).fill('إنشاء واحد رغم انقطاع الرد');
  await expect(page.locator('.mol-editor-save-state')).toContainText('غير متصل');
  await page.getByRole('button', { name: 'إعادة المحاولة', exact: true }).click();
  await expect(page.locator('.mol-editor-save-state')).toContainText('تم الحفظ');
  expect(posts).toHaveLength(2); expect(posts[1]).toEqual(posts[0]);
  expect((await readElements(page.request, boot.api, boot.nonce, pageId)).filter((e: any) => e.content === 'إنشاء واحد رغم انقطاع الرد')).toHaveLength(1);
  await page.reload(); await expect(page.locator('.mol-element')).toHaveCount(5);
});

test('an occupied element is read-only; losing its lease and version opens explicit conflict comparison', async ({ page, playwright, isMobile }) => {
  const admin = await managerApi(playwright);
  try {
    expect((await admin.call('POST', `elements/${bubble}/lock`)).status()).toBe(200);
    await open(page, isMobile);
    await expect(page.locator('.mol-editor-save-state')).toContainText('مقفل');
    await expect(page.getByLabel('النص العربي', { exact: true })).not.toBeEditable();
    await admin.call('DELETE', `elements/${bubble}/lock`);
    await page.getByRole('button', { name: 'إعادة المحاولة', exact: true }).click();
    await expect(page.getByLabel('النص العربي', { exact: true })).toBeEditable();
    await page.getByLabel('النص العربي', { exact: true }).fill('نسختي عند التعارض');
    await admin.call('DELETE', `elements/${bubble}/lock`);
    const lease = (await (await admin.call('POST', `elements/${bubble}/lock`)).json()).data;
    const current = (await (await admin.call('GET', `pages/${pageId}/elements`)).json()).data.find((e: any) => e.id === bubble);
    expect((await admin.call('PATCH', `elements/${bubble}`, { content: 'تعديل المراجع', x_unit: 220000 }, { 'X-MOL-Lock-Token': lease.lock_token, 'If-Match': `"${current.version}"` })).status()).toBe(200);
    await admin.call('DELETE', `elements/${bubble}/lock`);
    await expect(page.locator('.mol-editor-save-state')).toContainText('مقفل');
    await page.getByRole('button', { name: 'إعادة المحاولة', exact: true }).click();
    await expect(page.locator('.mol-editor-comparison')).toContainText('نسختي عند التعارض');
    await expect(page.locator('.mol-editor-comparison')).toContainText('تعديل المراجع');
    await page.getByRole('button', { name: 'إعادة تطبيق تغييري على الحالية ثم الحفظ', exact: true }).click();
    await expect(page.locator('.mol-editor-save-state')).toContainText('تم الحفظ');
    const final = (await (await admin.call('GET', `pages/${pageId}/elements`)).json()).data.find((e: any) => e.id === bubble);
    expect(final.content).toBe('نسختي عند التعارض'); expect(final.x_unit).toBe(220000); expect(final.version).toBe(current.version + 2);
  } finally { await admin.request.dispose(); }
});

test('lease renewal stops editing when a manager releases the lock', async ({ page, playwright, isMobile }) => {
  const admin = await managerApi(playwright);
  try {
    await page.clock.install(); await open(page, isMobile);
    await expect(page.getByLabel('النص العربي', { exact: true })).toBeEditable();
    const renewed = page.waitForResponse(response => response.request().method() === 'PUT' && response.url().endsWith(`/elements/${bubble}/lock`));
    await page.clock.fastForward(16000); expect((await renewed).status()).toBe(200);
    await admin.call('DELETE', `elements/${bubble}/lock`);
    const lost = page.waitForResponse(response => response.request().method() === 'PUT' && response.url().endsWith(`/elements/${bubble}/lock`));
    await page.clock.fastForward(16000); expect((await lost).status()).toBe(409);
    await expect(page.locator('.mol-editor-save-state')).toContainText('مقفل');
    await expect(page.getByLabel('النص العربي', { exact: true })).not.toBeEditable();
  } finally { await admin.request.dispose(); }
});
