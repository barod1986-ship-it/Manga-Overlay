import { test, expect, type Page } from '@playwright/test';
import { readFileSync } from 'node:fs';

// Generated credentials stay in runner temp. Never log or attach this fixture.
const fixture = JSON.parse(readFileSync(process.env.MOL_HTTP_FIXTURE!, 'utf8')) as {
  editor_url: string; editor_empty_url: string; draft_reader_url: string;
  editor_username: string; editor_password: string; member_username: string; member_password: string;
  editor_page_ids: number[]; editor_element_ids: number[];
};
const [firstPage, secondPage] = fixture.editor_page_ids;
const [bubble] = fixture.editor_element_ids;
async function login(page: Page, member = false) {
  await page.goto('/wp-login.php?redirect_to=' + encodeURIComponent(fixture.editor_url));
  await page.locator('#user_login').fill(member ? fixture.member_username : fixture.editor_username);
  await page.locator('#user_pass').fill(member ? fixture.member_password : fixture.editor_password);
  await Promise.all([
    page.waitForURL(fixture.editor_url, { waitUntil: 'domcontentloaded' }),
    page.locator('#wp-submit').click(),
  ]);
}

test('editor route authenticates before disclosure and denies a member without the editor capability', async ({ page }) => {
  const anonymous = await page.request.get(fixture.editor_url, { maxRedirects: 0 });
  expect(anonymous.status()).toBe(302);
  expect(anonymous.headers().location).toContain('/wp-login.php');
  expect(await anonymous.text()).not.toContain('mol-editor-data');
  await login(page, true);
  const denied = await page.goto(fixture.editor_url);
  expect(denied?.status()).toBe(403);
  await expect(page.locator('#mol-editor-data')).toHaveCount(0);
  await expect(page.locator('body')).toContainText('لا تملك صلاحية');
  await expect(page.locator('body')).not.toContainText('فصل مسودة لا يظهر');
  const draft = await page.goto(fixture.draft_reader_url);
  expect(draft?.status()).toBe(404);
});

test('translator inspects live layers and properties, preview preserves the original and page links restore selection', async ({ page, isMobile }, testInfo) => {
  const writes: string[] = [];
  const errors: string[] = [];
  page.on('request', request => { if (request.url().includes('/mol/v1/') && request.method() !== 'GET') writes.push(request.method()); });
  page.on('pageerror', error => errors.push(error.message));
  await login(page);
  await expect(page.locator('.mol-editor-stage')).toHaveAttribute('data-page-id', String(firstPage));
  await expect(page.locator('.mol-element')).toHaveCount(4);
  const response = await page.request.get(fixture.editor_url);
  expect(response.headers()['cache-control']).toContain('no-store');
  expect(response.headers()['cache-control']).toContain('private');
  const boot = JSON.parse((await page.locator('#mol-editor-data').textContent())!);
  expect(boot).not.toHaveProperty('pages');
  expect(boot).not.toHaveProperty('elements');
  if (isMobile) await page.getByRole('button', { name: 'الطبقات', exact: true }).click();
  await page.locator('.mol-editor-layers button').filter({ hasText: 'نص خاص داخل المحرر' }).click();
  await expect(page.getByLabel('النص العربي', { exact: true })).toHaveValue('نص خاص داخل المحرر');
  await expect(page.getByLabel('النص العربي', { exact: true })).toHaveAttribute('readonly', '');
  await expect(page).toHaveURL(fixture.editor_url + `#page=${firstPage}&element=${bubble}`);
  const image = page.locator('.mol-editor-stage img');
  await image.evaluate(node => node.setAttribute('data-original-node', 'retained'));
  await page.getByRole('button', { name: 'معاينة', exact: true }).click();
  await expect(page.locator('.mol-editor-properties,.mol-editor-layers,.mol-element-selected')).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'الترجمة العربية', exact: true })).toHaveAttribute('aria-pressed', 'true');
  await page.getByRole('button', { name: 'الترجمة العربية', exact: true }).click();
  await expect(page.locator('.mol-overlay-layer')).toBeHidden();
  await expect(image).toHaveAttribute('data-original-node', 'retained');
  await page.getByRole('button', { name: 'الترجمة العربية', exact: true }).click();
  await expect(page.locator('.mol-overlay-layer')).toBeVisible();
  await page.getByRole('button', { name: 'إغلاق المعاينة', exact: true }).click();
  await expect(image).toHaveAttribute('data-original-node', 'retained');
  await page.getByRole('button', { name: 'تكبير الصفحة', exact: true }).click();
  await expect(page.getByRole('button', { name: 'ملاءمة عرض الصفحة', exact: true })).toHaveText('125%');
  await page.getByRole('button', { name: 'الصفحة التالية', exact: true }).click();
  await expect(page.locator('.mol-editor-stage')).toHaveAttribute('data-page-id', String(secondPage));
  await expect(page.locator('.mol-element-text')).toHaveText('الصفحة العريضة');
  await expect(page.getByRole('button', { name: 'ملاءمة عرض الصفحة', exact: true })).toHaveText('100%');
  const size = await page.locator('.mol-editor-stage').boundingBox();
  expect(size!.width / size!.height).toBeCloseTo(960 / 420, 2);
  await page.goBack();
  await expect(page.locator('.mol-element-selected')).toHaveAttribute('data-element-key', String(bubble));
  await page.reload();
  await expect(page.locator('.mol-element-selected')).toHaveAttribute('data-element-key', String(bubble));
  await expect(page.locator('.mol-editor-save-state')).toContainText('عرض فقط');
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
  expect(await page.evaluate(() => Object.keys(localStorage).filter(key => key.includes('editor')))).toEqual([]);
  expect(writes).toEqual([]); expect(errors).toEqual([]);
  await page.evaluate(async () => { await document.fonts.ready; });
  await page.screenshot({ path: testInfo.outputPath('editor-shell.png') });
});

test('editor recovers a failed page read and handles empty chapters and removed page links', async ({ page }) => {
  await login(page);
  let first = true;
  await page.route(`**/pages/${firstPage}/elements`, async route => {
    if (first) { first = false; await route.fulfill({ status: 500, json: { code: 'internal_server_error', message: 'Temporary test failure', data: { status: 500 } } }); }
    else await route.continue();
  });
  await page.reload();
  await expect(page.getByRole('alert')).toContainText('تعذر تحميل المحتوى');
  await expect(page.locator('.mol-editor-stage')).toHaveCount(0);
  await page.getByRole('button', { name: 'إعادة المحاولة', exact: true }).click();
  await expect(page.locator('.mol-element')).toHaveCount(4);
  await page.goto(fixture.editor_empty_url);
  await expect(page.locator('.mol-editor')).toContainText('لم تُرفع صفحات لهذا الفصل بعد.');
  await expect(page.getByLabel('الصفحة', { exact: true })).toBeDisabled();
  await page.goto(fixture.editor_url + '#page=99999999&element=99999999');
  await expect(page.locator('.mol-editor-stage')).toHaveAttribute('data-page-id', String(firstPage));
  await expect(page).toHaveURL(fixture.editor_url + '#page=' + firstPage);
});

test('page navigation cancels a delayed old overlay response without displaying the wrong page layers', async ({ page }) => {
  await login(page);
  let release!: () => void;
  let started!: () => void;
  const gate = new Promise<void>(resolve => { release = resolve; });
  const seen = new Promise<void>(resolve => { started = resolve; });
  await page.route(`**/pages/${firstPage}/elements`, async route => {
    started(); await gate;
    await route.continue().catch(() => { /* The request was intentionally aborted by navigation. */ });
  });
  try {
    await page.reload(); await seen;
    await expect(page.locator('.mol-editor-page-area')).toHaveAttribute('aria-busy', 'true');
    await page.getByLabel('الصفحة', { exact: true }).selectOption({ value: String(secondPage) });
    await expect(page.locator('.mol-editor-stage')).toHaveAttribute('data-page-id', String(secondPage));
    release();
    await expect(page.locator('.mol-element-text')).toHaveText('الصفحة العريضة');
    await expect(page.locator('.mol-editor-page-area')).not.toContainText('نص خاص داخل المحرر');
  } finally { release(); }
});

test('expired REST session clears the stage and offers a real session refresh', async ({ page }) => {
  await login(page);
  await expect(page.locator('.mol-element')).toHaveCount(4);
  await page.route(`**/pages/${secondPage}/elements`, route => route.fulfill({ status: 403, json: { code: 'mol_forbidden', message: 'Expired test nonce', data: { status: 403 } } }));
  await page.getByRole('button', { name: 'الصفحة التالية', exact: true }).click();
  await expect(page.getByRole('alert')).toContainText('انتهت الجلسة');
  await expect(page.locator('.mol-editor-stage,.mol-editor-properties,.mol-editor-layers')).toHaveCount(0);
  await expect(page.getByRole('link', { name: 'تحديث الجلسة', exact: true })).toBeVisible();
  await expect(page.getByLabel('الصفحة', { exact: true })).toBeDisabled();
});
