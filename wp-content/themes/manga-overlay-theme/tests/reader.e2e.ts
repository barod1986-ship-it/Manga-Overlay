import path from 'node:path';
import { expect, test } from '@playwright/test';

const readerScript = path.resolve(
  process.cwd(),
  'wp-content/themes/manga-overlay-theme/assets/js/reader.js',
);
const readerStyles = path.resolve(
  process.cwd(),
  'wp-content/themes/manga-overlay-theme/assets/css/reader.css',
);
const pageImage = 'data:image/svg+xml,' + encodeURIComponent(
  '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="1200"><rect width="800" height="1200" fill="#eee"/></svg>',
);

function readerMarkup(direction: 'rtl' | 'ltr' = 'rtl'): string {
  const payload = {
    chapterId: 41,
    workId: 9,
    defaultMode: 'webtoon',
    direction,
    hasTranslation: true,
    targetLanguage: 'ar',
    isAuthenticated: false,
    initialProgress: null,
    progressEndpoint: '',
    restNonce: '',
    elementGroups: [
      {
        page_id: 101,
        page_index: 0,
        elements: [
          {
            id: 501,
            page_id: 101,
            target_lang: 'ar',
            element_type: 'bubble',
            x_unit: 250000,
            y_unit: 100000,
            w_unit: 500000,
            h_unit: 200000,
            rotation_mdeg: 0,
            z_index: 2,
            content: '<img src=x onerror="window.readerHacked=true">مرحبا',
            style: {
              fontId: 'cairo',
              fontSizeUnit: 30000,
              fontWeight: 700,
              lineHeight: 1.3,
              textAlign: 'center',
              color: '#111111',
              backgroundColor: '#ffffff',
              backgroundOpacity: 1,
              borderColor: '#111111',
              borderWidthUnit: 1000,
              paddingUnit: 10000,
              shape: 'ellipse',
            },
          },
        ],
      },
      { page_id: 102, page_index: 1, elements: [] },
    ],
  };

  const frame = (id: number, index: number) => `
    <figure class="mol-reader-frame" data-mol-page data-page-id="${id}" data-page-index="${index}">
      <div class="mol-reader-viewport" data-mol-page-viewport>
        <div class="mol-reader-surface" data-mol-page-surface>
          <img class="mol-reader-image" src="${pageImage}" width="800" height="1200">
          <div class="mol-overlay-layer" data-mol-overlay-layer></div>
        </div>
      </div>
    </figure>`;

  return `<!doctype html><html><body class="mol-reader-page">
    <main class="mol-reader" data-mol-reader data-mode="webtoon" data-direction="${direction}">
      <button data-mol-mode="webtoon" aria-pressed="true">webtoon</button>
      <button data-mol-mode="paged" aria-pressed="false">paged</button>
      <button data-mol-translation-toggle aria-pressed="true"><span>الترجمة ظاهرة</span></button>
      <span data-mol-progress-status></span>
      <section>${frame(101, 0)}${frame(102, 1)}</section>
      <nav class="mol-reader-page-controls">
        <button data-mol-page-previous>previous</button>
        <span data-mol-page-counter></span>
        <button data-mol-page-next>next</button>
      </nav>
      <div class="mol-reader-zoom">
        <button data-mol-zoom-out>zoom out</button>
        <output data-mol-zoom-level>100%</output>
        <button data-mol-zoom-in>zoom in</button>
        <button data-mol-zoom-reset>reset</button>
      </div>
      <script type="application/json" id="mol-reader-data">${JSON.stringify(payload).replaceAll('<', '\\u003c')}</script>
    </main>
  </body></html>`;
}

test.beforeEach(async ({ page }) => {
  await page.setContent(readerMarkup());
  await page.addStyleTag({ path: readerStyles });
  await page.addScriptTag({ path: readerScript });
  await expect(page.locator('[data-element-id="501"]')).toBeVisible();
});

test('renders plain Arabic text at normalized geometry without executing content', async ({ page }) => {
  const element = page.locator('[data-element-id="501"]');
  const geometry = await element.evaluate((node) => {
    const layer = node.parentElement;
    const style = window.getComputedStyle(node);
    if (!layer) throw new Error('Overlay layer is missing.');
    return {
      left: Number.parseFloat(style.left) / layer.clientWidth,
      width: Number.parseFloat(style.width) / layer.clientWidth,
    };
  });
  expect(geometry.left).toBeCloseTo(0.25, 3);
  expect(geometry.width).toBeCloseTo(0.5, 3);
  await expect(element.locator('.mol-element-text')).toContainText('مرحبا');
  await expect(element.locator('img')).toHaveCount(0);
  expect(await page.evaluate(() => (window as typeof window & { readerHacked?: boolean }).readerHacked)).toBeUndefined();
});

test('toggles every translation surface without changing image sources', async ({ page }) => {
  const image = page.locator('.mol-reader-image').first();
  const source = await image.getAttribute('src');
  await page.locator('[data-mol-translation-toggle]').click();
  await expect(page.locator('[data-mol-overlay-layer]').first()).toBeHidden();
  await expect(page.locator('[data-mol-translation-toggle]')).toHaveAttribute('aria-pressed', 'false');
  expect(await image.getAttribute('src')).toBe(source);
});

test('preloads only the nearby page image', async ({ page }) => {
  await expect(page.locator('.mol-reader-image').nth(1)).toHaveAttribute('loading', 'eager');
  await expect(page.locator('.mol-reader-image').nth(1)).toHaveAttribute('fetchpriority', 'low');
});

test('switches to paged RTL navigation and resets zoom on page change', async ({ page }) => {
  await page.locator('[data-mol-mode="paged"]').click();
  await expect(page.locator('[data-mol-page]').nth(0)).toBeVisible();
  await expect(page.locator('[data-mol-page]').nth(1)).toBeHidden();

  await page.locator('[data-mol-zoom-in]').click();
  await expect(page.locator('[data-mol-zoom-level]')).toHaveText('125%');
  await page.keyboard.press('ArrowLeft');
  await expect(page.locator('[data-mol-page]').nth(0)).toBeHidden();
  await expect(page.locator('[data-mol-page]').nth(1)).toBeVisible();
  await expect(page.locator('[data-mol-zoom-level]')).toHaveText('100%');
});

test('uses the opposite forward arrow for LTR paged reading', async ({ page }) => {
  await page.setContent(readerMarkup('ltr'));
  await page.addStyleTag({ path: readerStyles });
  await page.addScriptTag({ path: readerScript });
  await page.locator('[data-mol-mode="paged"]').click();
  await page.keyboard.press('ArrowRight');
  await expect(page.locator('[data-mol-page]').nth(1)).toBeVisible();
});
