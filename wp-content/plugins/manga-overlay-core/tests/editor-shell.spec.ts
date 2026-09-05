import { expect, test, type Page } from '@playwright/test';
import type { EditorElement, StylePreset } from '../editor-src/types';

const requestsByPage = new WeakMap<Page, string[]>();

test.beforeEach(async ({ page }) => {
  const requests: string[] = [];
  requestsByPage.set(page, requests);
  let nextId = 500;
  let conflictTriggered = false;
  let nextPresetId = 800;
  const presets: StylePreset[] = [{
    id: 701,
    scope: 'work',
    owner_user_id: null,
    work_id: 11,
    name: 'فقاعة حمراء',
    element_type: 'bubble',
    style: { color: '#cc0000', backgroundColor: '#fff4f2', fontSizeUnit: 24_000 },
    is_default: true,
    created_by: 9,
    created_at: '2026-09-05T00:00:00Z',
    updated_at: '2026-09-05T00:00:00Z',
  }];
  const stored = new Map<number, EditorElement>([
    [101, {
      id: 101, page_id: 41, target_lang: 'ar', element_type: 'bubble', version: 1,
      x_unit: 100_000, y_unit: 160_000, w_unit: 320_000, h_unit: 180_000,
      rotation_mdeg: 0, z_index: 2, content: 'مرحبا من الفقاعة', style: {},
    }],
    [102, {
      id: 102, page_id: 41, target_lang: 'ar', element_type: 'free_text', version: 4,
      x_unit: 560_000, y_unit: 420_000, w_unit: 250_000, h_unit: 120_000,
      rotation_mdeg: -5_000, z_index: 3,
      content: '<img src=x onerror="window.__molXss=true">نص آمن', style: {},
    }],
  ]);

  await page.route('**/wp-json/mol/v1/**', async (route) => {
    const request = route.request();
    const url = new URL(request.url());
    const path = url.pathname.replace(/^.*\/wp-json\/mol\/v1\/?/, '');
    const scenario = new URL(page.url()).searchParams.get('mol_scenario');
    if (!path.startsWith('presets')) requests.push(`${request.method()} ${path}`);

    if (request.method() === 'GET' && path === 'presets') {
      const type = url.searchParams.get('type');
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: presets.filter((preset) => preset.element_type === type),
          meta: { count: presets.length },
        }),
      });
      return;
    }

    if (request.method() === 'POST' && path === 'presets') {
      const body = request.postDataJSON() as Omit<StylePreset, 'id' | 'owner_user_id' | 'created_by' | 'created_at' | 'updated_at'>;
      const preset: StylePreset = {
        ...body,
        id: ++nextPresetId,
        owner_user_id: body.scope === 'personal' ? 9 : null,
        created_by: 9,
        created_at: '2026-09-05T00:00:00Z',
        updated_at: '2026-09-05T00:00:00Z',
      };
      presets.push(preset);
      await route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({ data: preset, meta: {} }),
      });
      return;
    }

    const pageElementsMatch = /^pages\/(\d+)\/elements$/.exec(path);
    if (request.method() === 'GET' && pageElementsMatch !== null) {
      const pageId = Number(pageElementsMatch[1]);
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [...stored.values()].filter((element) => element.page_id === pageId),
          meta: { page_id: pageId, target_lang: 'ar', count: stored.size },
        }),
      });
      return;
    }

    if (request.method() === 'POST' && path === 'elements') {
      const body = request.postDataJSON() as Omit<EditorElement, 'id' | 'version'>;
      const element = { ...body, id: ++nextId, version: 1 } satisfies EditorElement;
      stored.set(element.id, element);
      await route.fulfill({
        status: 201,
        contentType: 'application/json',
        headers: { ETag: '"1"' },
        body: JSON.stringify({ data: element, meta: {} }),
      });
      return;
    }

    const lockMatch = /^elements\/(\d+)\/lock$/.exec(path);
    if (request.method() === 'POST' && lockMatch !== null) {
      const elementId = Number(lockMatch[1]);
      if (scenario === 'locked' && elementId === 101) {
        await route.fulfill({
          status: 423,
          contentType: 'application/json',
          body: JSON.stringify({
            code: 'mol_element_locked',
            message: 'The element is locked by another editor.',
            data: { status: 423, locked_by: 'سارة' },
          }),
        });
        return;
      }
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            element_id: elementId,
            user_id: 9,
            lock_token: 'a'.repeat(64),
            expires_at: new Date(Date.now() + 45_000).toISOString(),
          },
          meta: {},
        }),
      });
      return;
    }

    if (request.method() === 'PUT' && lockMatch !== null) {
      const elementId = Number(lockMatch[1]);
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            element_id: elementId,
            user_id: 9,
            lock_token: request.headers()['x-mol-lock-token'] ?? 'a'.repeat(64),
            expires_at: new Date(Date.now() + 45_000).toISOString(),
          },
          meta: {},
        }),
      });
      return;
    }

    if (request.method() === 'DELETE' && lockMatch !== null) {
      await route.fulfill({ status: 204, body: '' });
      return;
    }

    const elementMatch = /^elements\/(\d+)$/.exec(path);
    if (elementMatch !== null && request.method() === 'PATCH') {
      const elementId = Number(elementMatch[1]);
      const current = stored.get(elementId);
      if (current === undefined) {
        await route.fulfill({
          status: 404,
          contentType: 'application/json',
          body: JSON.stringify({ code: 'mol_not_found', message: 'Not found.', data: { status: 404 } }),
        });
        return;
      }
      if (scenario === 'conflict' && elementId === 101 && !conflictTriggered) {
        conflictTriggered = true;
        stored.set(101, { ...current, y_unit: 210_000, content: 'النسخة الحالية من سارة', version: 2 });
        await route.fulfill({
          status: 412,
          contentType: 'application/json',
          body: JSON.stringify({
            code: 'mol_version_conflict',
            message: 'The element version is stale.',
            data: { status: 412 },
          }),
        });
        return;
      }
      if (scenario === 'precondition') {
        await route.fulfill({
          status: 428,
          contentType: 'application/json',
          body: JSON.stringify({
            code: 'mol_precondition_required',
            message: 'If-Match is required for this operation.',
            data: { status: 428 },
          }),
        });
        return;
      }
      const body = request.postDataJSON() as Partial<EditorElement>;
      const version = current.version + 1;
      const element = { ...current, ...body, id: elementId, version } satisfies EditorElement;
      stored.set(elementId, element);
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        headers: { ETag: `"${version}"` },
        body: JSON.stringify({ data: element, meta: {} }),
      });
      return;
    }

    if (elementMatch !== null && request.method() === 'DELETE') {
      stored.delete(Number(elementMatch[1]));
      await route.fulfill({ status: 204, body: '' });
      return;
    }

    await route.fulfill({
      status: 404,
      contentType: 'application/json',
      body: JSON.stringify({ code: 'mol_not_found', message: 'Not found.', data: { status: 404 } }),
    });
  });
});

test('loads T-11 safely, routes pages, and previews the reader renderer', async ({ page }) => {
  await page.goto('/tests/editor-shell-fixture.html?mol_page=1');

  await expect(page.getByTestId('editor-shell')).toBeVisible();
  await expect(page.locator('#mol-editor-root')).toHaveAttribute('dir', 'rtl');
  await expect(page.getByText('بداية الحكاية', { exact: true })).toBeVisible();
  await expect(page.getByTestId('save-state')).toHaveText('تم الحفظ');
  await expect(page.getByTestId('add-bubble')).toBeEnabled();

  const firstElement = page.getByTestId('stage-element-101');
  await expect(firstElement).toBeVisible();
  expect(await firstElement.evaluate((node) => (node as HTMLElement).style.left)).toBe('10%');
  expect(await firstElement.evaluate((node) => (node as HTMLElement).style.top)).toBe('16%');
  await expect(firstElement.locator('.mol-element-shape')).toBeVisible();
  await expect(firstElement.locator('.mol-element-text')).toHaveText('مرحبا من الفقاعة');

  await page.getByTestId('layer-102').click();
  await expect(page.getByTestId('property-content')).toHaveValue(
    '<img src=x onerror="window.__molXss=true">نص آمن',
  );
  await expect(page.getByTestId('stage-element-102').locator('.mol-element-text')).toContainText('نص آمن');
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
  await expect(page.locator('.moveable-control-box')).toHaveCount(0);
  await expect(page.getByTestId('stage-element-101')).toBeVisible();
  await expect(page.getByTestId('stage-element-101').locator('.mol-element-shape')).toBeVisible();
});

test('creates, edits, duplicates, reorders, and deletes all four local element types', async ({ page }) => {
  await page.goto('/tests/editor-shell-fixture.html?mol_page=2');

  for (const type of ['bubble', 'narration', 'free_text', 'sfx'] as const) {
    await page.getByTestId(`add-${type}`).click();
  }

  const overlay = page.getByTestId('overlay-layer');
  await expect(overlay.locator('.mol-overlay-element')).toHaveCount(4);
  for (const type of ['bubble', 'narration', 'free_text', 'sfx']) {
    await expect(overlay.locator(`[data-element-type="${type}"]`)).toHaveCount(1);
  }
  await expect(page.getByTestId('save-state')).toHaveAttribute('data-state', 'dirty');

  const selected = overlay.locator('[data-element-type="sfx"]');
  await expect(selected).toHaveAttribute('data-selected', 'true');
  await page.getByTestId('property-content').fill('طَقّ!');
  await page.getByTestId('shape-select').selectOption('burst');
  await page.getByTestId('stroke-color').fill('#cc0000');
  await expect(selected.locator('.mol-element-text')).toHaveText('طَقّ!');
  await expect(selected.locator('svg polygon')).toHaveCount(1);
  await expect(selected.locator('.mol-element-text')).toHaveCSS('-webkit-text-stroke-color', 'rgb(204, 0, 0)');

  await page.getByRole('button', { name: 'طبقة لأسفل' }).click();
  await page.getByRole('button', { name: 'نسخ العنصر' }).click();
  await expect(overlay.locator('.mol-overlay-element')).toHaveCount(5);
  await page.getByRole('button', { name: 'حذف العنصر' }).click();
  await expect(overlay.locator('.mol-overlay-element')).toHaveCount(4);
});

test('autosaves create, update, and delete through the strict REST client', async ({ page }) => {
  await page.goto('/tests/editor-shell-fixture.html?mol_page=2');
  await page.getByTestId('add-bubble').click();
  await page.getByTestId('property-content').fill('حوار محفوظ تلقائيًا');
  await expect(page.getByTestId('save-state')).toHaveAttribute('data-state', 'dirty');
  await expect(page.getByTestId('save-state')).toHaveAttribute('data-state', 'saved', { timeout: 5_000 });

  await expect(page.getByTestId('layer-501')).toBeVisible();
  await page.getByTestId('property-content').fill('تعديل ثانٍ محفوظ');
  await expect(page.getByTestId('save-state')).toHaveAttribute('data-state', 'dirty');
  await expect(page.getByTestId('save-state')).toHaveAttribute('data-state', 'saved', { timeout: 5_000 });
  await page.getByRole('button', { name: 'حذف العنصر' }).click();
  await expect(page.getByTestId('layer-501')).toHaveCount(0);
  await expect(page.getByTestId('save-state')).toHaveAttribute('data-state', 'saved');

  expect(requestsByPage.get(page)).toEqual([
    'POST elements',
    'POST elements/501/lock',
    'PATCH elements/501',
    'DELETE elements/501',
  ]);
});

test('keeps dirty edits in the tab while offline and sends them after reconnect', async ({ page, context }) => {
  await page.goto('/tests/editor-shell-fixture.html?mol_page=2');
  await context.setOffline(true);
  await page.getByTestId('add-free_text').click();
  await page.getByTestId('property-content').fill('نص أثناء انقطاع الشبكة');
  await expect(page.getByTestId('save-state')).toHaveAttribute('data-state', 'offline');
  await expect(page.getByTestId('save-state')).toContainText('غير متصل — لم تُرسل تغييرات هذه الجلسة');

  await context.setOffline(false);
  await page.evaluate(() => window.dispatchEvent(new Event('online')));
  await expect(page.getByTestId('save-state')).toHaveAttribute('data-state', 'saved', { timeout: 5_000 });
  expect(requestsByPage.get(page)).toEqual(['POST elements', 'POST elements/501/lock']);
});

test('commits Moveable drag/resize and supports numeric and keyboard alternatives', async ({ page }) => {
  await page.goto('/tests/editor-shell-fixture.html?mol_page=1');
  const originalElement = await page.getByTestId('stage-element-101').elementHandle();
  if (originalElement === null) throw new Error('Element handle is missing.');
  await originalElement.click();
  await expect.poll(async () => originalElement.evaluate((node) => node.isConnected)).toBe(true);
  await expect.poll(async () => originalElement.getAttribute('data-selected')).toBe('true');
  await expect(page.locator('.moveable-control-box')).toBeVisible();
  await expect(page.locator('.moveable-rotation-control')).toBeVisible();

  const element = page.getByTestId('stage-element-101');
  const beforeDrag = await element.boundingBox();
  if (beforeDrag === null) throw new Error('Element box is missing.');
  await page.mouse.move(beforeDrag.x + beforeDrag.width / 2, beforeDrag.y + beforeDrag.height / 2);
  await page.mouse.down();
  await page.mouse.move(beforeDrag.x + beforeDrag.width / 2 + 36, beforeDrag.y + beforeDrag.height / 2 + 22, { steps: 5 });
  expect(requestsByPage.get(page)).toEqual(['POST elements/101/lock']);
  await page.mouse.up();
  await expect.poll(async () => Number(await page.getByTestId('geometry-x_unit').inputValue())).toBeGreaterThan(10);
  await expect.poll(() => requestsByPage.get(page)).toContain('PATCH elements/101');

  const eastHandle = page.locator('.moveable-control.moveable-e');
  const eastBox = await eastHandle.boundingBox();
  if (eastBox === null) throw new Error('Resize handle is missing.');
  const widthBefore = Number(await page.getByTestId('geometry-w_unit').inputValue());
  await page.mouse.move(eastBox.x + eastBox.width / 2, eastBox.y + eastBox.height / 2);
  await page.mouse.down();
  await page.mouse.move(eastBox.x + eastBox.width / 2 + 32, eastBox.y + eastBox.height / 2, { steps: 5 });
  await page.mouse.up();
  await expect.poll(async () => Number(await page.getByTestId('geometry-w_unit').inputValue())).toBeGreaterThan(widthBefore);

  await page.getByTestId('geometry-rotation').fill('18');
  expect(await element.evaluate((node) => (node as HTMLElement).style.transform)).toContain('rotate(18deg)');
  const beforeKeyboard = Number(await page.getByTestId('geometry-x_unit').inputValue());
  await element.click();
  await page.keyboard.press('ArrowRight');
  await expect.poll(async () => Number(await page.getByTestId('geometry-x_unit').inputValue())).toBeGreaterThan(beforeKeyboard);
});

test('applies presets without changing content or geometry and saves a personal preset', async ({ page }) => {
  await page.goto('/tests/editor-shell-fixture.html?mol_page=1');
  await page.getByTestId('layer-101').click();
  await expect(page.getByTestId('preset-select')).toContainText('فقاعة حمراء');
  const before = await page.getByTestId('geometry-x_unit').inputValue();
  const content = await page.getByTestId('property-content').inputValue();
  await page.getByTestId('apply-preset').click();
  await expect(page.getByTestId('text-color')).toHaveValue('#cc0000');
  await expect(page.getByTestId('geometry-x_unit')).toHaveValue(before);
  await expect(page.getByTestId('property-content')).toHaveValue(content);

  await page.getByTestId('save-preset-toggle').click();
  await page.getByTestId('preset-name').fill('نمط شخصي جديد');
  await page.getByTestId('preset-scope').selectOption('personal');
  await page.getByTestId('save-preset').click();
  await expect(page.getByTestId('preset-select')).toContainText('نمط شخصي جديد');
  await expect(page.getByText('حُفظ النمط.')).toBeVisible();
});

test('auto-fits text without changing its box and Alt temporarily disables snapping', async ({ page }) => {
  await page.goto('/tests/editor-shell-fixture.html?mol_page=1');
  await page.getByTestId('layer-101').click();
  const element = page.getByTestId('stage-element-101');
  const before = await element.boundingBox();
  if (before === null) throw new Error('Element box is missing.');
  await page.getByTestId('property-content').fill('هذا نص عربي طويل لاختبار الملاءمة التلقائية داخل صندوق الفقاعة من دون تغيير أبعاده');
  await expect(element).toHaveAttribute('data-fitted-font-size-unit', /\d+/);
  const after = await element.boundingBox();
  expect(after).toEqual(before);
  const overflow = await element.locator('.mol-element-text').evaluate((text) => ({
    horizontal: text.scrollWidth - text.clientWidth,
    vertical: text.scrollHeight - text.clientHeight,
  }));
  expect(overflow.horizontal).toBeLessThanOrEqual(1);
  expect(overflow.vertical).toBeLessThanOrEqual(1);

  await expect(page.getByTestId('editor-stage')).toHaveAttribute('data-snapping', 'on');
  await page.keyboard.down('Alt');
  await expect(page.getByTestId('editor-stage')).toHaveAttribute('data-snapping', 'off');
  await page.keyboard.up('Alt');
  await expect(page.getByTestId('editor-stage')).toHaveAttribute('data-snapping', 'on');
});

test('keeps an element locked by another editor readable but read-only', async ({ page }) => {
  await page.goto('/tests/editor-shell-fixture.html?mol_page=1&mol_scenario=locked');
  await page.getByTestId('layer-101').click();

  await expect(page.getByTestId('save-state')).toHaveAttribute('data-state', 'locked');
  await expect(page.getByTestId('save-state')).toContainText('سارة');
  await expect(page.getByTestId('locked-notice')).toContainText('يحرر سارة هذا العنصر الآن');
  await expect(page.getByTestId('property-content')).toHaveValue('مرحبا من الفقاعة');
  await expect(page.getByTestId('property-content')).toBeDisabled();
  await expect(page.locator('.moveable-control')).toHaveCount(0);
});

test('shows both versions on 412 and reapplies only the local change', async ({ page }) => {
  await page.goto('/tests/editor-shell-fixture.html?mol_page=1&mol_scenario=conflict');
  await page.getByTestId('layer-101').click();
  await expect(page.getByTestId('property-content')).toBeEnabled();
  await page.getByTestId('property-content').fill('نسختي المحلية');

  const conflict = page.getByTestId('conflict-card');
  await expect(conflict).toBeVisible({ timeout: 5_000 });
  await expect(conflict.getByRole('heading', { name: 'نسختك' })).toBeVisible();
  await expect(conflict.getByText('نسختي المحلية', { exact: true })).toBeVisible();
  await expect(conflict.getByRole('heading', { name: 'النسخة الحالية' })).toBeVisible();
  await expect(conflict.getByText('النسخة الحالية من سارة', { exact: true })).toBeVisible();
  await expect(conflict.getByRole('button', { name: 'استخدام الحالية' })).toBeVisible();
  await conflict.getByRole('button', { name: 'إعادة تطبيق تغييري ثم الحفظ' }).click();

  await expect(page.getByTestId('save-state')).toHaveAttribute('data-state', 'saved', { timeout: 5_000 });
  await expect(page.getByTestId('property-content')).toHaveValue('نسختي المحلية');
  await expect(page.getByTestId('geometry-y_unit')).toHaveValue('21');
  expect(requestsByPage.get(page)).toContain('GET pages/41/elements');
  expect(requestsByPage.get(page)?.filter((request) => request === 'PATCH elements/101')).toHaveLength(2);
});

test('accepts the current server version with a pointer click above Moveable controls', async ({ page }) => {
  await page.goto('/tests/editor-shell-fixture.html?mol_page=1&mol_scenario=conflict');
  await page.getByTestId('layer-101').click();
  await expect(page.getByTestId('property-content')).toBeEnabled();
  await page.getByTestId('property-content').fill('نسخة محلية ستُرفض');

  const conflict = page.getByTestId('conflict-card');
  await expect(conflict).toBeVisible({ timeout: 5_000 });
  await conflict.getByRole('button', { name: 'استخدام الحالية' }).click();

  await expect(conflict).toBeHidden();
  await expect(page.getByTestId('save-state')).toHaveAttribute('data-state', 'saved');
  await expect(page.getByTestId('property-content')).toHaveValue('النسخة الحالية من سارة');
  await expect(page.getByTestId('geometry-y_unit')).toHaveValue('21');
});

test('surfaces a persistent 428 after refreshing the current version once', async ({ page }) => {
  await page.goto('/tests/editor-shell-fixture.html?mol_page=1&mol_scenario=precondition');
  await page.getByTestId('layer-101').click();
  await expect(page.getByTestId('property-content')).toBeEnabled();
  await page.getByTestId('property-content').fill('اختبار If-Match');

  await expect(page.getByTestId('save-state')).toHaveAttribute('data-state', 'error', { timeout: 5_000 });
  await expect(page.getByTestId('save-state')).toContainText('If‑Match');
  expect(requestsByPage.get(page)).toContain('GET pages/41/elements');
  expect(requestsByPage.get(page)?.filter((request) => request === 'PATCH elements/101')).toHaveLength(2);
});

test('renews the selected lease every 15 seconds and releases it on selection change', async ({ page }) => {
  await page.clock.install();
  await page.goto('/tests/editor-shell-fixture.html?mol_page=1');
  await page.getByTestId('layer-101').click();
  await expect(page.getByTestId('property-content')).toBeEnabled();

  await page.clock.fastForward(15_100);
  await expect.poll(() => requestsByPage.get(page)).toContain('PUT elements/101/lock');

  await page.getByTestId('layer-102').click();
  await expect(page.getByTestId('property-content')).toBeEnabled();
  await expect.poll(() => requestsByPage.get(page)).toContain('DELETE elements/101/lock');
  expect(requestsByPage.get(page)).toContain('POST elements/102/lock');
});
