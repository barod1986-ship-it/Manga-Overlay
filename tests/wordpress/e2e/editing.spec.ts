import { resetEditor } from './editor-fixture';
import { test, expect, type Page } from '@playwright/test';
import { readFileSync } from 'node:fs';
const fixture = JSON.parse(readFileSync(process.env.MOL_HTTP_FIXTURE!, 'utf8')) as Record<string, any>;
test.beforeEach(async ({ playwright }) => resetEditor(playwright));
test.beforeEach(async ({ page }) => { page.on('dialog', dialog => void dialog.accept()); });
const [firstPage, secondPage] = fixture.editor_page_ids as number[];
const bubble = fixture.editor_element_ids[0] as number;
const originalText = 'نص خاص داخل المحرر';

async function login(page: Page, kind: 'translator' | 'viewer' | 'writer' = 'translator') {
  const prefix = kind === 'translator' ? 'editor' : 'editor_' + kind;
  await page.request.get('/wp-login.php');
  const response = await page.request.post('/wp-login.php', { form: {
    log: fixture[prefix + '_username'], pwd: fixture[prefix + '_password'], 'wp-submit': 'Log In', redirect_to: fixture.editor_url, testcookie: '1',
  }, maxRedirects: 0 });
  expect(response.status()).toBe(302);
  expect((await page.context().cookies()).some(cookie => cookie.name.startsWith('wordpress_logged_in_'))).toBe(true);
  await page.goto(fixture.editor_url);
  await expect(page.locator('.mol-element')).toHaveCount(4);
}
async function selectBubble(page: Page, mobile: boolean) {
  if (mobile) await page.getByRole('button', { name: 'الطبقات', exact: true }).click();
  await page.locator('.mol-editor-layers button').filter({ hasText: originalText }).click();
  await expect(page.getByLabel('النص العربي', { exact: true })).toHaveValue(originalText);
}
async function closeProperties(page: Page, mobile: boolean) {
  if (mobile && await page.getByRole('button', { name: 'إغلاق الخصائص', exact: true }).isVisible()) await page.getByRole('button', { name: 'إغلاق الخصائص', exact: true }).click();
}
async function serverElements(page: Page) {
  const boot = JSON.parse((await page.locator('#mol-editor-data').textContent())!);
  const response = await page.request.get(boot.api + 'pages/' + firstPage + '/elements', { headers: { 'X-WP-Nonce': boot.nonce } });
  expect(response.status()).toBe(200);
  return (await response.json()).data;
}

test('four types edit as plain Arabic, duplicate/delete/undo and keep page drafts after confirmed autosave and reload', async ({ page, isMobile }, testInfo) => {
  const errors: string[] = [];
  page.on('pageerror', error => errors.push(error.message));
  await login(page);
  const injected = 'ترجمة عربية <img src=x onerror="window.molInjected=true">';
  for (const label of ['فقاعة', 'سرد', 'نص حر', 'مؤثر صوتي']) {
    await page.getByRole('button', { name: 'إضافة ' + label, exact: true }).click();
    await page.getByLabel('النص العربي', { exact: true }).fill(label + ' ' + injected);
    await expect(page.locator('.mol-element-selected .mol-element-text')).toHaveText(label + ' ' + injected);
    expect(new URL(page.url()).hash).not.toContain('draft');
    await closeProperties(page, isMobile);
  }
  await expect(page.locator('.mol-element')).toHaveCount(8);
  if (isMobile) await page.getByRole('button', { name: 'الخصائص', exact: true }).click();
  await page.getByRole('button', { name: 'نسخ العنصر', exact: true }).click();
  await expect(page.locator('.mol-element')).toHaveCount(9);
  await page.getByRole('button', { name: 'حذف العنصر', exact: true }).click();
  await expect(page.locator('.mol-element')).toHaveCount(8);
  await page.getByRole('button', { name: 'تراجع عن حذف العنصر', exact: true }).click();
  await expect(page.locator('.mol-element')).toHaveCount(9);
  await closeProperties(page, isMobile);
  await page.getByRole('button', { name: 'الصفحة التالية', exact: true }).click();
  await expect(page.locator('.mol-element-text')).toHaveText('الصفحة العريضة');
  await page.getByRole('button', { name: 'الصفحة السابقة', exact: true }).click();
  await expect(page.locator('.mol-element')).toHaveCount(9);
  await expect.poll(async () => (await serverElements(page)).length).toBe(9);
  await expect(page.locator('.mol-editor-save-state')).toContainText('تم الحفظ');
  await expect(page.locator('.mol-element img')).toHaveCount(0);
  expect(await page.evaluate(() => 'molInjected' in window)).toBe(false);
  expect(await page.evaluate(() => Object.keys(localStorage).filter(key => key.includes('editor')))).toEqual([]);
  expect(await page.evaluate(() => Object.keys(sessionStorage).filter(key => key.includes('editor')))).toEqual([]);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
  await page.screenshot({ path: testInfo.outputPath('connected-element-editing.png') });
  await page.reload();
  await expect(page.locator('.mol-element')).toHaveCount(9);
  expect((await serverElements(page)).length).toBe(9);
  expect(errors).toEqual([]);
});

test('numeric transforms, step buttons, layer order and shortcuts survive preview and page navigation', async ({ page, isMobile }) => {
  await login(page); await selectBubble(page, isMobile);
  await page.getByLabel('العرض (%)', { exact: true }).fill('20');
  await page.getByLabel('X (%)', { exact: true }).fill('40');
  await page.getByLabel('Y (%)', { exact: true }).fill('15');
  await page.getByLabel('الدوران (°)', { exact: true }).fill('12');
  await page.getByLabel('ترتيب الطبقة', { exact: true }).fill('99');
  await page.getByRole('button', { name: 'زيادة العرض', exact: true }).click();
  await page.getByRole('button', { name: 'دوران +1°', exact: true }).click();
  const element = page.locator(`[data-element-key="${bubble}"]`);
  await expect(element).toHaveAttribute('style', /width: 20.1%/);
  await expect(element).toHaveAttribute('style', /rotate\(13deg\)/);
  await expect(page.locator('.mol-editor-layers li').first()).toContainText(originalText);
  const text = page.getByLabel('النص العربي', { exact: true });
  await text.focus(); await text.press('Delete');
  await expect(page.locator('.mol-element')).toHaveCount(4);
  await closeProperties(page, isMobile);
  await element.focus(); await element.press('ArrowRight');
  await expect(element).toHaveAttribute('style', /left: 40.1%/);
  await element.dispatchEvent('keydown', { key: 'Delete', isComposing: true });
  await expect(page.locator('.mol-element')).toHaveCount(4);
  await page.getByRole('button', { name: 'تكبير الصفحة', exact: true }).click();
  await expect(element).toHaveAttribute('style', /left: 40.1%/);
  await page.getByRole('button', { name: 'معاينة', exact: true }).click();
  await expect(page.locator('.moveable-control,.mol-editor-properties,.mol-editor-tools')).toHaveCount(0);
  await expect(element).toHaveAttribute('style', /rotate\(13deg\)/);
  await page.getByRole('button', { name: 'إغلاق المعاينة', exact: true }).click();
  await page.getByRole('button', { name: 'الصفحة التالية', exact: true }).click();
  await expect(page.locator('.mol-editor-stage')).toHaveAttribute('data-page-id', String(secondPage));
  await page.goBack();
  await expect(element).toHaveAttribute('style', /left: 40.1%/);
  await element.focus(); await element.press('Control+d');
  await expect(page.locator('.mol-element')).toHaveCount(5);
  await closeProperties(page, isMobile);
  await page.locator('.mol-element-selected').focus(); await page.keyboard.press('Delete');
  await expect(page.locator('.mol-element')).toHaveCount(4);
});

test('shape options are specific to bubble/narration/text/SFX and render structured effects', async ({ page, isMobile }) => {
  await login(page); await selectBubble(page, isMobile);
  await page.getByText('الشكل والخلفية', { exact: true }).click();
  await page.getByLabel('الشكل', { exact: true }).selectOption('cloud');
  await page.getByLabel('ذيل الفقاعة', { exact: true }).check();
  await page.getByLabel('زاوية الذيل (°)', { exact: true }).fill('45');
  await page.getByLabel('طول الذيل (% عرض الصفحة)', { exact: true }).fill('6');
  await expect(page.locator('.mol-element-selected svg path')).toHaveCount(1);
  await expect(page.locator('.mol-element-selected svg polygon')).toHaveCount(1);
  await closeProperties(page, isMobile);
  await page.getByRole('button', { name: 'إضافة سرد', exact: true }).click();
  await page.getByText('الشكل والخلفية', { exact: true }).click();
  expect(await page.getByLabel('الشكل', { exact: true }).locator('option').evaluateAll(nodes => nodes.map(node => (node as HTMLOptionElement).value))).toEqual(['rect', 'rounded_rect']);
  await expect(page.getByLabel('ذيل الفقاعة', { exact: true })).toHaveCount(0);
  await closeProperties(page, isMobile);
  await page.getByRole('button', { name: 'إضافة مؤثر صوتي', exact: true }).click();
  await page.getByText('الشكل والخلفية', { exact: true }).click();
  await page.getByLabel('الشكل', { exact: true }).selectOption('impact');
  await page.getByLabel('رؤوس الانفجار', { exact: true }).selectOption('12');
  await page.getByLabel('مقياس X', { exact: true }).fill('1.5');
  await page.getByLabel('عمق الانفجار', { exact: true }).fill('0.6');
  const polygon = page.locator('.mol-element-selected svg polygon');
  expect((await polygon.getAttribute('points'))!.split(' ')).toHaveLength(24);
  await expect(page.locator('.mol-element-selected .mol-element-text')).toHaveAttribute('style', /scale\(1.5, 1\)/);
});

test('shell-only individual grant permits inspection but no mutation controls', async ({ page, isMobile }) => {
  await login(page, 'viewer'); await selectBubble(page, isMobile);
  await expect(page.getByLabel('النص العربي', { exact: true })).toHaveAttribute('readonly', '');
  await expect(page.getByLabel('X (%)', { exact: true })).toBeDisabled();
  await expect(page.getByRole('button', { name: 'نسخ العنصر', exact: true })).toBeDisabled();
  await expect(page.locator('.mol-editor-tools,.moveable-control')).toHaveCount(0);
  await expect(page.locator('.mol-editor-save-state')).toContainText('عرض فقط');
  await closeProperties(page, isMobile);
  await page.locator('.mol-element-selected').focus(); await page.keyboard.press('Delete');
  await expect(page.locator('.mol-element')).toHaveCount(4);
});

test('independent edit grant enables text and duplicate while deletion stays disabled', async ({ page, isMobile }) => {
  await login(page, 'writer'); await selectBubble(page, isMobile);
  await page.getByLabel('النص العربي', { exact: true }).fill('تعديل بصلاحية فردية');
  await expect(page.locator('.mol-element-selected .mol-element-text')).toHaveText('تعديل بصلاحية فردية');
  await expect(page.getByRole('button', { name: 'حذف العنصر', exact: true })).toBeDisabled();
  await page.getByRole('button', { name: 'نسخ العنصر', exact: true }).click();
  await expect(page.locator('.mol-element')).toHaveCount(5);
  await closeProperties(page, isMobile);
  await page.locator('.mol-element-selected').focus(); await page.keyboard.press('Delete');
  await expect(page.locator('.mol-element')).toHaveCount(5);
});

test('Moveable commits drag/resize/rotation in normalized coordinates after zoom', async ({ page, isMobile }) => {
  test.skip(isMobile, 'Mouse gestures are tested on desktop; physical touch remains an open device gate.');
  await login(page); await selectBubble(page, false);
  await page.getByRole('button', { name: 'تكبير الصفحة', exact: true }).click();
  const element = page.locator(`[data-element-key="${bubble}"]`);
  const drag = async (x: number, y: number, dx: number, dy: number) => {
    await page.mouse.move(x, y); await page.mouse.down(); await page.mouse.move(x + dx, y + dy, { steps: 10 }); await page.mouse.up();
  };
  const box = (await element.boundingBox())!;
  const beforeX = Number(await page.getByLabel('X (%)', { exact: true }).inputValue());
  await drag(box.x + box.width / 2, box.y + box.height / 2, 30, 20);
  await expect.poll(async () => Number(await page.getByLabel('X (%)', { exact: true }).inputValue())).toBeGreaterThan(beforeX + 1);
  const handle = (await page.locator('.moveable-se').boundingBox())!;
  const beforeWidth = Number(await page.getByLabel('العرض (%)', { exact: true }).inputValue());
  await drag(handle.x + handle.width / 2, handle.y + handle.height / 2, 30, 15);
  await expect.poll(async () => Number(await page.getByLabel('العرض (%)', { exact: true }).inputValue())).toBeGreaterThan(beforeWidth + 1);
  const rotation = (await page.locator('.moveable-rotation-control').boundingBox())!;
  await drag(rotation.x + rotation.width / 2, rotation.y + rotation.height / 2, 50, 20);
  await expect.poll(async () => Math.abs(Number(await page.getByLabel('الدوران (°)', { exact: true }).inputValue()))).toBeGreaterThan(3);
  const normalized = await element.getAttribute('style');
  expect(normalized).toMatch(/left: [\d.]+%/); expect(normalized).toMatch(/width: [\d.]+%/);
  await page.getByRole('button', { name: 'معاينة', exact: true }).click();
  await page.getByRole('button', { name: 'إغلاق المعاينة', exact: true }).click();
  await expect(element).toHaveAttribute('style', normalized!);
});
