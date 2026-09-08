import { test, expect, type Page } from '@playwright/test';
import { fixture, managerApi } from './editor-fixture';

async function login(page: Page, moderator = false) {
  const prefix = moderator ? 'report_moderator' : 'member';
  await page.request.get('/wp-login.php');
  expect((await page.request.post('/wp-login.php', { form: { log: fixture[prefix + '_username'], pwd: fixture[prefix + '_password'], 'wp-submit': 'Log In', testcookie: '1' }, maxRedirects: 0 })).status()).toBe(302);
}
async function openReport(page: Page) {
  await login(page); await page.goto(fixture.reader_url);
  await expect(page.getByRole('link', { name: 'مساحة الترجمة', exact: true })).toHaveCount(0);
  await page.getByRole('button', { name: 'الإبلاغ عن مشكلة', exact: true }).click();
  await expect(page.getByRole('dialog')).toBeVisible();
  return JSON.parse((await page.locator('#mol-reader-data').textContent())!);
}

test('reader member reports element, page and chapter without editor permissions; message remains plain', async ({ page, playwright }, testInfo) => {
  const boot = await openReport(page);
  const dialog = page.getByRole('dialog');
  await page.getByLabel('موضع المشكلة', { exact: true }).selectOption('element');
  await expect(page.getByLabel('عنصر الترجمة', { exact: true })).toHaveValue(String(boot.overlays[0].elements[0].id));
  const message = 'بلاغ عربي <img src=x onerror="window.molReportInjected=true"> موضع غير صحيح';
  await page.getByLabel('وصف المشكلة').fill(message);
  const sent = page.waitForResponse(response => response.request().method() === 'POST' && response.url().endsWith('/reports'));
  await page.getByRole('button', { name: 'إرسال البلاغ', exact: true }).click();
  const response = await sent; expect(response.status()).toBe(201);
  const report = (await response.json()).data;
  expect(report.page_id).toBe(boot.pages[0].id); expect(report.element_id).toBe(boot.overlays[0].elements[0].id);
  expect(report.message).toBe('بلاغ عربي  موضع غير صحيح');
  await expect(dialog).toContainText('وصل بلاغك #' + report.id);
  expect((await page.request.get(boot.api + 'reports', { headers: { 'X-WP-Nonce': boot.nonce } })).status()).toBe(403);
  for (const scope of ['page', 'chapter']) {
    await page.getByRole('button', { name: 'كتابة بلاغ آخر', exact: true }).click();
    await page.getByLabel('موضع المشكلة', { exact: true }).selectOption(scope);
    if (scope === 'page') await page.getByLabel('صفحة البلاغ').selectOption(String(boot.pages[1].id));
    await page.getByLabel('نوع المشكلة').selectOption('missing');
    await page.getByLabel('وصف المشكلة').fill('ترجمة ناقصة في ' + scope);
    const saved = page.waitForResponse(r => r.request().method() === 'POST' && r.url().endsWith('/reports'));
    await page.getByRole('button', { name: 'إرسال البلاغ', exact: true }).click();
    const result = await saved; expect(result.status()).toBe(201); const row = (await result.json()).data;
    expect(row.element_id).toBeNull(); expect(row.page_id).toBe(scope === 'page' ? boot.pages[1].id : null);
    await expect(dialog).toContainText('وصل بلاغك #' + row.id);
  }
  await page.screenshot({ path: testInfo.outputPath('reader-report-arabic.png') });
  await page.getByRole('button', { name: 'إغلاق البلاغ' }).click();
  await expect(page.getByRole('button', { name: 'الإبلاغ عن مشكلة', exact: true })).toBeFocused();
  expect(await page.evaluate(() => 'molReportInjected' in window)).toBe(false);
  const admin = await managerApi(playwright);
  try {
    const rows = (await (await admin.call('GET', 'reports?chapter_id=' + boot.chapter.id)).json()).data;
    expect(rows.find((row: any) => row.id === report.id).message).toBe(report.message);
  } finally { await admin.request.dispose(); }
});

test('lost report response preserves text and does not repeat a committed POST', async ({ page, playwright }) => {
  const boot = await openReport(page);
  const message = 'انقطع رد البلاغ ' + crypto.randomUUID(); let requests = 0;
  await page.route('**/reports', async route => {
    if (route.request().method() !== 'POST') return route.continue();
    requests++; const response = await route.fetch(); expect(response.status()).toBe(201); await route.abort('failed');
  });
  await page.getByLabel('وصف المشكلة').fill(message);
  await page.getByRole('button', { name: 'إرسال البلاغ', exact: true }).click();
  await expect(page.getByRole('dialog')).toContainText('قد يكون وصل بالفعل');
  await expect(page.getByLabel('وصف المشكلة')).toHaveValue(message);
  await expect(page.getByRole('button', { name: 'إرسال البلاغ', exact: true })).toBeDisabled();
  await page.getByRole('button', { name: 'إغلاق البلاغ' }).click();
  await page.getByRole('button', { name: 'الإبلاغ عن مشكلة', exact: true }).click();
  await expect(page.getByRole('button', { name: 'إرسال البلاغ', exact: true })).toBeDisabled();
  const admin = await managerApi(playwright);
  try {
    const reports = (await (await admin.call('GET', 'reports?chapter_id=' + boot.chapter.id)).json()).data;
    expect(reports.filter((report: any) => report.message === message)).toHaveLength(1); expect(requests).toBe(1);
  } finally { await admin.request.dispose(); }
});

test('rate limit countdown and revoked session retain the report draft', async ({ page }) => {
  await openReport(page); let attempts = 0;
  await page.route('**/reports', async route => {
    if (route.request().method() !== 'POST') return route.continue();
    attempts++;
    await route.fulfill({ status: attempts === 1 ? 429 : 403, contentType: 'application/json', headers: { 'Retry-After': '1' }, body: JSON.stringify({ code: attempts === 1 ? 'mol_rate_limited' : 'mol_forbidden', message: 'اختبار رفض', data: { status: attempts === 1 ? 429 : 403 } }) });
  });
  await page.getByLabel('وصف المشكلة').fill('يبقى النص عند الرفض');
  await page.getByRole('button', { name: 'إرسال البلاغ', exact: true }).click();
  await expect(page.getByRole('dialog')).toContainText('حد الإرسال المؤقت');
  await expect(page.getByRole('button', { name: 'إرسال البلاغ', exact: true })).toBeDisabled();
  await expect(page.getByRole('button', { name: 'إرسال البلاغ', exact: true })).toBeEnabled();
  await page.getByRole('button', { name: 'إرسال البلاغ', exact: true }).click();
  await expect(page.getByRole('dialog')).toContainText('سُحبت صلاحية التبليغ');
  await expect(page.getByLabel('وصف المشكلة')).toHaveValue('يبقى النص عند الرفض');
  await expect(page.getByRole('button', { name: 'إرسال البلاغ', exact: true })).toBeDisabled(); expect(attempts).toBe(2);
});

test('independent moderator reviews reports and reloads confirmed state after a lost PATCH response', async ({ page, playwright }, testInfo) => {
  const admin = await managerApi(playwright);
  try {
    const pages = (await (await admin.call('GET', 'chapters/' + fixture.reader_chapter_id + '/pages')).json()).data;
    const created = await admin.call('POST', 'reports', { chapter_id: fixture.reader_chapter_id, page_id: pages[0].id, report_type: 'style', message: 'بلاغ للمراجعة ' + crypto.randomUUID() });
    expect(created.status()).toBe(201); const report = (await created.json()).data;
    await login(page, true); await page.goto('/wp-admin/admin.php?page=mol-reports');
    await page.getByLabel('حالة البلاغ', { exact: true }).selectOption('');
    await page.getByLabel('رقم الفصل', { exact: true }).fill(String(fixture.reader_chapter_id));
    await page.getByRole('button', { name: 'عرض البلاغات', exact: true }).click();
    const card = page.locator(`[data-report-id="${report.id}"]`);
    await expect(card).toContainText(report.message);
    const link = await card.getByRole('link', { name: 'فتح موضع البلاغ' }).getAttribute('href');
    const redirect = await page.request.get(link!, { maxRedirects: 0 });
    expect(redirect.status()).toBe(302); expect(redirect.headers().location).toBe(fixture.reader_url + '#mol-page-' + pages[0].id);
    await page.route('**/reports/' + report.id, async route => {
      if (route.request().method() !== 'PATCH') return route.continue();
      const response = await route.fetch(); expect(response.status()).toBe(200); await route.abort('failed');
    });
    await card.getByLabel('الحالة الجديدة').selectOption('in_review');
    await card.getByRole('button', { name: 'حفظ حالة البلاغ' }).click();
    await expect(page.locator('#mol-reports-notice')).toContainText('تعذر تأكيد الحفظ');
    await expect(card).toHaveCount(0);
    await page.unroute('**/reports/' + report.id);
    await page.getByRole('button', { name: 'إعادة تحميل القائمة' }).click();
    await expect(card.locator('.mol-report-current')).toHaveText('الحالة الحالية: قيد المراجعة');
    await card.getByLabel('الحالة الجديدة').selectOption('resolved');
    await card.getByRole('button', { name: 'حفظ حالة البلاغ' }).click();
    await expect(card.locator('.mol-report-current')).toHaveText('الحالة الحالية: تم الحل');
    await expect(card).toContainText('أغلقه المشرف');
    await page.screenshot({ path: testInfo.outputPath('report-moderation-arabic.png') });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
  } finally { await admin.request.dispose(); }
});

test('guest sees sign-in entry and member cannot open the moderation screen', async ({ page }) => {
  await page.goto(fixture.reader_url);
  await expect(page.getByRole('link', { name: 'سجّل الدخول للإبلاغ' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'الإبلاغ عن مشكلة', exact: true })).toHaveCount(0);
  await login(page);
  const response = await page.goto('/wp-admin/admin.php?page=mol-reports');
  expect(response?.status()).toBe(403); await expect(page.locator('#mol-reports-data')).toHaveCount(0);
});
