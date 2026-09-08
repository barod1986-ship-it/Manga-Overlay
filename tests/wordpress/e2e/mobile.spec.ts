import { test, expect, type Page, type CDPSession } from '@playwright/test';
import { fixture, openPropertySection, readElements, resetEditor } from './editor-fixture';

const pageId = fixture.editor_page_ids[0] as number;
const bubble = fixture.editor_element_ids[0] as number;
test.beforeEach(async ({ playwright, isMobile }) => { test.skip(!isMobile, 'Native touch sequence uses the Chromium mobile project.'); await resetEditor(playwright); });
async function open(page: Page) {
  await page.request.get('/wp-login.php');
  expect((await page.request.post('/wp-login.php', { form: { log: fixture.editor_username, pwd: fixture.editor_password, testcookie: '1', 'wp-submit': 'Log In' }, maxRedirects: 0 })).status()).toBe(302);
  await page.goto(fixture.editor_url);
  await expect(page.locator('.mol-element')).toHaveCount(4);
  await page.getByRole('button', { name: 'الطبقات', exact: true }).click();
  await page.locator('.mol-editor-layers button').filter({ hasText: 'نص خاص داخل المحرر' }).click();
  await expect(page.getByLabel('النص العربي', { exact: true })).toBeEditable();
  return JSON.parse((await page.locator('#mol-editor-data').textContent())!);
}
type Finger = { x: number; y: number; id: number };
async function touch(cdp: CDPSession, page: Page, type: 'touchStart' | 'touchMove' | 'touchEnd' | 'touchCancel', points: Finger[]) {
  await cdp.send('Input.dispatchTouchEvent', { type, touchPoints: points });
  await page.evaluate(() => new Promise<void>(resolve => requestAnimationFrame(() => resolve())));
}
const center = (box: { x: number; y: number; width: number; height: number }, id = 1): Finger => ({ x: box.x + box.width / 2, y: box.y + box.height / 2, id });
async function drag(cdp: CDPSession, page: Page, start: Finger, dx: number, dy: number, cancel = false) {
  await touch(cdp, page, 'touchStart', [start]);
  for (let step = 1; step <= 6; step++) await touch(cdp, page, 'touchMove', [{ ...start, x: start.x + dx * step / 6, y: start.y + dy * step / 6 }]);
  await touch(cdp, page, cancel ? 'touchCancel' : 'touchEnd', []);
}

test('one finger transforms save once at release; touchcancel restores the confirmed geometry', async ({ page, context }) => {
  const boot = await open(page);
  await page.getByRole('button', { name: 'إغلاق الخصائص', exact: true }).click();
  await page.getByRole('button', { name: 'التقاط المحاذاة', exact: true }).click();
  const cdp = await context.newCDPSession(page);
  const element = page.locator(`[data-element-key="${bubble}"]`);
  const read = async () => (await readElements(page.request, boot.api, boot.nonce, pageId)).find((e: any) => e.id === bubble);
  let writes = 0;
  page.on('request', request => { if (request.method() === 'PATCH' && request.url().endsWith('/elements/' + bubble)) writes++; });
  const original = await read();
  const scrolling = await page.locator('.mol-editor-viewport').evaluate(node => [node.scrollLeft, node.scrollTop]);
  await drag(cdp, page, center((await element.boundingBox())!), 18, 15);
  await expect.poll(async () => (await read()).x_unit).toBeGreaterThan(original.x_unit + 10000);
  expect(writes).toBe(1);
  expect(await page.locator('.mol-editor-viewport').evaluate(node => [node.scrollLeft, node.scrollTop])).toEqual(scrolling);
  const moved = await read();
  await drag(cdp, page, center((await page.locator('.moveable-se').boundingBox())!), 15, 10);
  await expect.poll(async () => (await read()).w_unit).toBeGreaterThan(moved.w_unit + 10000);
  expect(writes).toBe(2);
  await drag(cdp, page, center((await page.locator('.moveable-rotation-control').boundingBox())!), 30, 12);
  await expect.poll(async () => Math.abs((await read()).rotation_mdeg)).toBeGreaterThan(3000);
  expect(writes).toBe(3);
  const confirmed = await read(), css = await element.getAttribute('style');
  await drag(cdp, page, center((await element.boundingBox())!), -20, 10, true);
  await expect(element).toHaveAttribute('style', css!);
  expect((await read()).version).toBe(confirmed.version); expect(writes).toBe(3);
});

test('a second finger cancels element drag, anchors zoom and pans without writing overlays', async ({ page, context }, testInfo) => {
  const boot = await open(page);
  await page.getByRole('button', { name: 'إغلاق الخصائص', exact: true }).click();
  await page.getByRole('button', { name: 'التقاط المحاذاة', exact: true }).click();
  const cdp = await context.newCDPSession(page), element = page.locator(`[data-element-key="${bubble}"]`), stage = page.locator('.mol-editor-stage');
  const before = await readElements(page.request, boot.api, boot.nonce, pageId);
  let writes = 0;
  page.on('request', request => { if (['POST', 'PATCH', 'DELETE'].includes(request.method()) && /\/elements(?:\/\d+)?$/.test(request.url())) writes++; });
  const first = center((await element.boundingBox())!);
  await touch(cdp, page, 'touchStart', [first]);
  const moved = { ...first, x: first.x - 15, y: first.y + 10 };
  await touch(cdp, page, 'touchMove', [moved]);
  const second = { x: moved.x + 90, y: moved.y + 10, id: 2 };
  const initial = (await stage.boundingBox())!;
  const anchor = { x: ((moved.x + second.x) / 2 - initial.x) / initial.width, y: ((moved.y + second.y) / 2 - initial.y) / initial.height };
  await touch(cdp, page, 'touchStart', [moved, second]);
  const spread = [{ ...moved, x: moved.x - 45 }, { ...second, x: second.x + 25 }];
  await touch(cdp, page, 'touchMove', spread);
  await expect.poll(async () => (await stage.boundingBox())!.width / initial.width).toBeGreaterThan(1.6);
  const afterZoom = (await stage.boundingBox())!;
  expect(Math.abs(afterZoom.x + anchor.x * afterZoom.width - (spread[0].x + spread[1].x) / 2)).toBeLessThan(2);
  expect(Math.abs(afterZoom.y + anchor.y * afterZoom.height - (spread[0].y + spread[1].y) / 2)).toBeLessThan(2);
  // Supplying the remaining active point releases the omitted finger; terminal touchEnd is empty.
  await touch(cdp, page, 'touchMove', [spread[0]]);
  await touch(cdp, page, 'touchMove', [{ ...spread[0], x: spread[0].x - 20 }]);
  await touch(cdp, page, 'touchEnd', []);
  await expect(element).toHaveClass(/mol-element-selected/);
  expect(writes).toBe(0);
  expect(await readElements(page.request, boot.api, boot.nonce, pageId)).toEqual(before);
  const viewport = page.locator('.mol-editor-viewport');
  // Blank top-right image area: pan changes the viewport, not selection or element units.
  await viewport.evaluate(node => { node.scrollLeft = 0; node.scrollTop = 0; });
  const bounds = (await viewport.boundingBox())!;
  const blank = { x: bounds.x + bounds.width - 30, y: bounds.y + 52, id: 1 };
  await drag(cdp, page, blank, -55, -18);
  await expect.poll(async () => viewport.evaluate(node => node.scrollLeft)).toBeGreaterThan(40);
  expect(writes).toBe(0);
  await page.screenshot({ path: testInfo.outputPath('mobile-touch-zoom.png') });
  await page.getByRole('button', { name: 'ملاءمة عرض الصفحة', exact: true }).click();
  await expect.poll(async () => (await stage.boundingBox())!.width).toBeCloseTo(initial.width, 0);
  expect(await readElements(page.request, boot.api, boot.nonce, pageId)).toEqual(before);
});

test('45/85 percent sheet follows a simulated keyboard viewport and keeps the focused Arabic field visible', async ({ page }, testInfo) => {
  const boot = await open(page);
  const sheet = page.locator('.mol-editor-properties');
  expect(await sheet.locator('[data-property-section][open]').count()).toBe(0);
  const initial = await page.evaluate(() => visualViewport!.height);
  expect((await sheet.boundingBox())!.height).toBeCloseTo(initial * .45, 0);
  await page.getByRole('button', { name: 'توسيع الخصائص', exact: true }).click();
  const toolbar = (await page.locator('.mol-editor-bottom').boundingBox())!;
  expect((await sheet.boundingBox())!.height).toBeCloseTo(Math.min(initial * .85, initial - toolbar.height - 8), 0);
  await openPropertySection(page, 'الموضع والحجم');
  await expect(page.getByLabel('X (%)', { exact: true })).toBeVisible();
  const input = page.getByLabel('النص العربي', { exact: true });
  await input.fill('كتابة عربية أثناء فتح لوحة المفاتيح');
  // A deterministic VisualViewport resize models OSK geometry; it is not physical keyboard acceptance.
  await page.evaluate(() => {
    Object.defineProperty(visualViewport, 'height', { configurable: true, value: 440 });
    Object.defineProperty(visualViewport, 'offsetTop', { configurable: true, value: 32 });
    visualViewport!.dispatchEvent(new Event('resize')); visualViewport!.dispatchEvent(new Event('scroll'));
  });
  await expect.poll(async () => (await page.locator('.mol-editor').boundingBox())!.height).toBe(440);
  await expect(input).toBeFocused();
  const field = (await input.boundingBox())!, panel = (await sheet.boundingBox())!, header = (await sheet.locator('.mol-editor-panel-title').boundingBox())!;
  expect(field.y).toBeGreaterThanOrEqual(header.y + header.height - 1);
  expect(field.y + field.height).toBeLessThanOrEqual(panel.y + panel.height - 8);
  const bottom = (await page.locator('.mol-editor-bottom').boundingBox())!;
  expect(bottom.y + bottom.height).toBeCloseTo(472, 0);
  expect(panel.y + panel.height).toBeCloseTo(bottom.y, 0);
  await page.screenshot({ path: testInfo.outputPath('mobile-keyboard-viewport.png') });
  await expect(page.locator('.mol-editor-save-state')).toContainText('تم الحفظ');
  expect((await readElements(page.request, boot.api, boot.nonce, pageId)).find((e: any) => e.id === bubble).content).toBe('كتابة عربية أثناء فتح لوحة المفاتيح');
  await page.evaluate(() => {
    Reflect.deleteProperty(visualViewport!, 'height'); Reflect.deleteProperty(visualViewport!, 'offsetTop'); visualViewport!.dispatchEvent(new Event('resize'));
  });
  await page.getByRole('button', { name: 'إغلاق الخصائص', exact: true }).click();
  await expect(page.getByRole('button', { name: 'الخصائص', exact: true })).toBeFocused();
  await expect(sheet).not.toBeVisible();
});
