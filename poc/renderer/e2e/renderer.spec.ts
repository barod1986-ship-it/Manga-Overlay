import { expect, test } from '@playwright/test';

test('keeps overlay geometry attached to the physical image', async ({ page }) => {
  await page.goto('/');

  const image = page.locator('#page-image');
  const layer = page.locator('#overlay-layer');
  const bubble = page.locator('[data-element-id="101"]');

  await image.evaluate(async (node: HTMLImageElement) => node.decode());
  await expect(bubble).toBeVisible();
  await expect(page.locator('.mol-overlay-element')).toHaveCount(4);

  const metrics = await bubble.evaluate((node) => {
    const element = node as HTMLElement;
    const overlay = element.parentElement;
    const text = element.querySelector<HTMLElement>('.mol-element-text');
    if (overlay === null || text === null) {
      throw new Error('Renderer output is incomplete');
    }

    const computed = window.getComputedStyle(element);
    return {
      leftRatio: Number.parseFloat(computed.left) / overlay.clientWidth,
      topRatio: Number.parseFloat(computed.top) / overlay.clientHeight,
      widthRatio: Number.parseFloat(computed.width) / overlay.clientWidth,
      heightRatio: Number.parseFloat(computed.height) / overlay.clientHeight,
      direction: window.getComputedStyle(text).direction,
      text: text.textContent,
    };
  });

  expect(metrics.leftRatio).toBeCloseTo(0.565, 3);
  expect(metrics.topRatio).toBeCloseTo(0.052, 3);
  expect(metrics.widthRatio).toBeCloseTo(0.33, 3);
  expect(metrics.heightRatio).toBeCloseTo(0.145, 3);
  expect(metrics.direction).toBe('rtl');
  expect(metrics.text).toBe('انتظر… هل سمعت ذلك الصوت؟');

  const [imageBox, layerBox] = await Promise.all([image.boundingBox(), layer.boundingBox()]);
  expect(imageBox).not.toBeNull();
  expect(layerBox).not.toBeNull();
  expect(layerBox?.width).toBeCloseTo(imageBox?.width ?? 0, 1);
  expect(layerBox?.height).toBeCloseTo(imageBox?.height ?? 0, 1);
});

test('hides every translation surface without reloading the image', async ({ page }) => {
  await page.goto('/');

  const image = page.locator('#page-image');
  const layer = page.locator('#overlay-layer');
  const toggle = page.locator('#translation-toggle');
  await image.evaluate(async (node: HTMLImageElement) => node.decode());
  await expect(layer).toBeVisible();

  const sourceBeforeToggle = await image.getAttribute('src');
  await toggle.click();

  await expect(layer).toBeHidden();
  await expect(toggle).toHaveAttribute('aria-pressed', 'false');
  await expect(toggle).toHaveText('الترجمة مخفية');
  expect(await image.getAttribute('src')).toBe(sourceBeforeToggle);
});
