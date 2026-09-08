import { test, expect, type Page } from '@playwright/test';
import { fixture, managerApi, readElements, resetEditor, openPropertySection } from './editor-fixture';
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
  await expect(page.getByLabel('النص العربي', { exact: true })).toBeEditable();
  for (const label of ['الخط والمحاذاة', 'الموضع والحجم', 'الأنماط المحفوظة']) await openPropertySection(page, label);
  await expect(page.getByRole('button', { name: 'تطبيق النمط', exact: true })).toBeEnabled();
  return JSON.parse((await page.locator('#mol-editor-data').textContent())!);
}
async function close(page: Page, mobile: boolean) {
  if (mobile) await page.getByRole('button', { name: 'إغلاق الخصائص', exact: true }).click();
}

test('personal preset is saved, applied without geometry/content changes, used by new elements, and deleted independently', async ({ page, isMobile }, testInfo) => {
  const boot = await open(page, isMobile);
  let id: number | undefined;
  try {
    const original = (await readElements(page.request, boot.api, boot.nonce, pageId)).find((e: any) => e.id === bubble);
    await page.getByLabel('لون النص', { exact: true }).fill('#335577');
    await page.getByText('حفظ وإدارة الأنماط', { exact: true }).click();
    expect(await page.getByLabel('نطاق النمط الجديد').locator('option').allTextContents()).toEqual(['شخصي']);
    await page.getByLabel('اسم النمط', { exact: true }).fill('نمط مترجم تجريبي');
    await page.getByLabel('افتراضي لهذا النوع والنطاق').check();
    const created = page.waitForResponse(r => r.request().method() === 'POST' && r.url().endsWith('/presets'));
    await page.getByRole('button', { name: 'حفظ كنمط جديد', exact: true }).click();
    const response = await created; expect(response.status()).toBe(201); id = (await response.json()).data.id;
    await expect(page.getByLabel('النمط', { exact: true })).toHaveValue(String(id));
    await page.getByLabel('لون النص', { exact: true }).fill('#992211');
    await page.getByRole('button', { name: 'تطبيق النمط', exact: true }).click();
    await expect(page.getByLabel('لون النص', { exact: true })).toHaveValue('#335577');
    await expect(page.locator('.mol-editor-save-state')).toContainText('تم الحفظ');
    const saved = (await readElements(page.request, boot.api, boot.nonce, pageId)).find((e: any) => e.id === bubble);
    for (const key of ['content', 'x_unit', 'y_unit', 'w_unit', 'h_unit', 'rotation_mdeg', 'z_index']) expect(saved[key]).toBe(original[key]);
    expect(saved.style.color).toBe('#335577');
    await close(page, isMobile);
    await page.getByRole('button', { name: 'إضافة فقاعة', exact: true }).click();
    await expect(page.getByLabel('لون النص', { exact: true })).toHaveValue('#335577');
    await page.getByLabel('النص العربي', { exact: true }).fill('نص بنمط افتراضي');
    await expect(page.locator('.mol-editor-save-state')).toContainText('تم الحفظ');
    await openPropertySection(page, 'الأنماط المحفوظة');
    await page.getByLabel('النمط', { exact: true }).selectOption(String(id));
    await page.getByText('حفظ وإدارة الأنماط', { exact: true }).click();
    await page.getByLabel('اسم النمط', { exact: true }).fill('نمط مترجم محدث');
    await page.getByLabel('افتراضي لهذا النوع والنطاق').uncheck();
    const patched = page.waitForResponse(r => r.request().method() === 'PATCH' && r.url().endsWith('/presets/' + id));
    await page.getByRole('button', { name: 'تحديث النمط المحدد من العنصر', exact: true }).click();
    expect((await patched).status()).toBe(200);
    await expect(page.getByLabel('النمط', { exact: true }).locator('option:checked')).toHaveText('نمط مترجم محدث · شخصي');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
    await page.screenshot({ path: testInfo.outputPath('presets-arabic.png') });
    page.once('dialog', dialog => void dialog.accept());
    const deleted = page.waitForResponse(r => r.request().method() === 'DELETE' && r.url().endsWith('/presets/' + id));
    await page.getByRole('button', { name: 'حذف النمط المحدد', exact: true }).click(); expect((await deleted).status()).toBe(204);
    await expect(page.getByLabel('النمط', { exact: true }).locator(`option[value="${id}"]`)).toHaveCount(0); id = undefined;
    const after = await readElements(page.request, boot.api, boot.nonce, pageId);
    expect(after.find((e: any) => e.content === 'نص بنمط افتراضي').style.color).toBe('#335577');
    await page.reload();
    await expect(page.locator('.mol-element-text').filter({ hasText: 'نص بنمط افتراضي' })).toHaveCount(1);
  } finally {
    if (id) await page.request.delete(boot.api + 'presets/' + id, { headers: { 'X-WP-Nonce': boot.nonce } });
  }
});

test('auto-fit shrinks within minimum, preserves the box and can be frozen through autosave', async ({ page, isMobile }) => {
  const boot = await open(page, isMobile);
  await page.getByLabel('العرض (%)', { exact: true }).fill('40');
  await page.getByLabel('الارتفاع (%)', { exact: true }).fill('12');
  await page.getByLabel('حجم الخط (% عرض الصفحة)', { exact: true }).fill('20');
  await page.getByLabel('ملاءمة النص تلقائيًا').check();
  await page.getByLabel('أقل حجم (% عرض الصفحة)', { exact: true }).fill('1');
  await page.getByLabel('النص العربي', { exact: true }).fill('تجربة عربية لملاءمة النص داخل الفقاعة. '.repeat(6));
  const text = page.locator(`[data-element-key="${bubble}"] .mol-element-text`);
  await expect(text).toHaveAttribute('data-overflow', 'false');
  const fitted = Number(await text.getAttribute('data-fitted-font-unit'));
  expect(fitted).toBeGreaterThanOrEqual(10000); expect(fitted).toBeLessThan(200000);
  await page.getByLabel('ملاءمة النص تلقائيًا').uncheck();
  await expect.poll(async () => Number(await page.getByLabel('حجم الخط (% عرض الصفحة)', { exact: true }).inputValue())).toBeCloseTo(Math.round(fitted) / 10000, 3);
  await expect(page.locator('.mol-editor-save-state')).toContainText('تم الحفظ');
  const saved = (await readElements(page.request, boot.api, boot.nonce, pageId)).find((e: any) => e.id === bubble);
  expect(saved.style.autoFit).toBe(false); expect(saved.style.fontSizeUnit).toBe(Math.round(fitted));
  expect(saved.w_unit).toBe(400000); expect(saved.h_unit).toBe(120000);
  await page.getByRole('button', { name: 'توسيط أفقي في الصفحة' }).click();
  await page.getByRole('button', { name: 'توسيط عمودي في الصفحة' }).click();
  await expect(page.getByLabel('X (%)', { exact: true })).toHaveValue('30');
  await expect(page.getByLabel('Y (%)', { exact: true })).toHaveValue('44');
  await close(page, isMobile);
  await page.getByRole('button', { name: 'معاينة', exact: true }).click();
  await expect(page.locator('.moveable-guideline,.moveable-control')).toHaveCount(0);
  await expect(text).toHaveAttribute('data-overflow', 'false');
});

test('snapping aligns to page and other elements after zoom; Alt disables it temporarily', async ({ page, playwright, isMobile }) => {
  test.skip(isMobile, 'Mouse snapping is verified on desktop; numeric alignment works on both layouts.');
  const admin = await managerApi(playwright);
  try {
    // Establish an isolated reference away from page center and the other fixture elements.
    const referenceId = fixture.editor_element_ids[1] as number;
    const current = (await (await admin.call('GET', 'pages/' + pageId + '/elements')).json()).data.find((e: any) => e.id === referenceId);
    const lease = (await (await admin.call('POST', `elements/${referenceId}/lock`)).json()).data;
    expect((await admin.call('PATCH', `elements/${referenceId}`, { x_unit: 150000, y_unit: 520000, w_unit: 180000, h_unit: 100000, rotation_mdeg: 0 }, { 'If-Match': `"${current.version}"`, 'X-MOL-Lock-Token': lease.lock_token })).status()).toBe(200);
    await admin.call('DELETE', `elements/${referenceId}/lock`);
    await open(page, false);
    for (const [label, value] of [['العرض (%)', '20'], ['الارتفاع (%)', '12'], ['X (%)', '40'], ['Y (%)', '36'], ['الدوران (°)', '0']]) await page.getByLabel(label, { exact: true }).fill(value);
    await page.getByRole('button', { name: 'تكبير الصفحة', exact: true }).click();
    await expect(page.getByLabel('X (%)', { exact: true })).toHaveValue('40');
    const element = page.locator(`[data-element-key="${bubble}"]`);
    const stage = (await page.locator('.mol-editor-stage').boundingBox())!;
    const box = (await element.boundingBox())!;
    await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2); await page.mouse.down();
    await page.mouse.move(box.x + box.width / 2 + 3, box.y + box.height / 2, { steps: 5 });
    await expect(page.locator('.moveable-guideline')).not.toHaveCount(0);
    await page.mouse.up();
    await expect.poll(async () => Math.abs(Number(await page.getByLabel('X (%)', { exact: true }).inputValue()) / 100 - .4) * stage.width).toBeLessThan(1);
    await page.keyboard.down('Alt');
    const moved = (await element.boundingBox())!;
    await page.mouse.move(moved.x + moved.width / 2, moved.y + moved.height / 2); await page.mouse.down();
    await page.mouse.move(moved.x + moved.width / 2 + 3, moved.y + moved.height / 2, { steps: 5 }); await page.mouse.up(); await page.keyboard.up('Alt');
    await expect.poll(async () => Number(await page.getByLabel('X (%)', { exact: true }).inputValue())).toBeGreaterThan(40.1);
    const after = (await element.boundingBox())!;
    // Align selected left edge near the reference's left edge, within five displayed pixels.
    await page.mouse.move(after.x + after.width / 2, after.y + after.height / 2); await page.mouse.down();
    await page.mouse.move(stage.x + stage.width * .15 + 3 + after.width / 2, after.y + after.height / 2, { steps: 10 }); await page.mouse.up();
    await expect.poll(async () => Math.abs(Number(await page.getByLabel('X (%)', { exact: true }).inputValue()) / 100 - .15) * stage.width).toBeLessThan(1);
    await page.getByRole('button', { name: 'التقاط المحاذاة', exact: true }).click();
    await expect(page.getByRole('button', { name: 'التقاط المحاذاة', exact: true })).toHaveAttribute('aria-pressed', 'false');
  } finally { await admin.request.dispose(); }
});
