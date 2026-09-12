import { test, expect, type Page } from '@playwright/test';
import { fixture, resetEditor } from './editor-fixture';

test.beforeEach(async ({ playwright }) => resetEditor(playwright));

async function login(page: Page, member = false) {
  await page.request.get('/wp-login.php');
  const response = await page.request.post('/wp-login.php', {
    form: { log: member ? fixture.member_username : fixture.editor_username,
      pwd: member ? fixture.member_password : fixture.editor_password, testcookie: '1', 'wp-submit': 'Log In' },
    maxRedirects: 0,
  });
  expect(response.status()).toBe(302);
}

function nonce(policy: string) {
  const value = policy.match(/'nonce-([A-Za-z0-9+/]{32})'/)?.[1];
  expect(value).toBeDefined();
  return value!;
}

test('editor enforces a fresh CSP and trusted WordPress scripts still initialize without violations', async ({ page }) => {
  await page.addInitScript(() => {
    document.addEventListener('securitypolicyviolation', event => {
      document.documentElement.dataset.molCspViolation = event.effectiveDirective;
    });
  });
  await login(page);
  const response = (await page.goto(fixture.editor_url))!;
  expect(response.status()).toBe(200);
  const headers = response.headers();
  const policy = headers['content-security-policy'];
  expect(policy).toContain("frame-ancestors 'self'");
  expect(policy).toContain("object-src 'none'");
  expect(policy).toContain("base-uri 'self'");
  expect(policy).toContain("form-action 'self'");
  expect(policy).not.toContain('unsafe-inline');
  expect(policy).not.toContain('unsafe-eval');
  expect(headers['x-content-type-options']).toBe('nosniff');
  expect(headers['x-frame-options']).toBe('SAMEORIGIN');
  expect(headers['referrer-policy']).toBe('same-origin');
  expect(headers['cache-control']).toContain('private');
  expect(headers['cache-control']).toContain('no-store');
  const current = nonce(policy);
  expect(await page.locator('#mol-csp-inline-fixture').evaluate(node => (node as HTMLScriptElement).nonce)).toBe(current);
  await expect(page.locator('html')).toHaveAttribute('data-mol-trusted-inline', 'ready');
  await expect(page.locator('.mol-editor-stage')).toBeVisible();
  expect(await page.locator('.mol-editor-stage img').evaluate(node => (node as HTMLImageElement).naturalWidth)).toBeGreaterThan(0);
  await page.evaluate(async () => { await document.fonts.ready; });
  await expect(page.locator('html')).not.toHaveAttribute('data-mol-csp-violation');
  const fresh = await page.request.get(fixture.editor_url);
  expect(nonce(fresh.headers()['content-security-policy'])).not.toBe(current);
});

test('browser blocks injected inline scripts, handlers, external scripts and eval in the editor', async ({ page }) => {
  await login(page);
  await page.goto(fixture.editor_url);
  await expect(page.locator('.mol-editor-stage')).toBeVisible();
  await page.evaluate(() => {
    const root = document.documentElement;
    document.addEventListener('securitypolicyviolation', event => {
      root.dataset.molBlocked = (root.dataset.molBlocked ?? '') + ' ' + event.effectiveDirective;
    });
    const inline = document.createElement('script');
    inline.textContent = "document.documentElement.dataset.molInjected = 'executed'";
    document.body.append(inline);
    const external = document.createElement('script');
    external.src = 'https://mol-csp.invalid/probe.js';
    document.body.append(external);
    const button = document.createElement('button');
    button.textContent = 'CSP handler probe';
    Object.assign(button.style, { position: 'fixed', left: '8px', bottom: '8px', zIndex: '2147483647' });
    button.setAttribute('onclick', "document.documentElement.dataset.molHandler = 'executed'");
    document.body.append(button);
    // Run eval from a genuinely executed, authorized script. DevTools evaluation
    // alone may bypass CSP and would not establish enforcement in page scripts.
    const trusted = document.createElement('script');
    trusted.nonce = (document.getElementById('mol-csp-inline-fixture') as HTMLScriptElement).nonce;
    trusted.textContent = "try { new Function(\"document.documentElement.dataset.molEval = 'executed'\")(); } catch (error) { document.documentElement.dataset.molEval = error.name; }";
    document.body.append(trusted);
  });
  await page.getByRole('button', { name: 'CSP handler probe', exact: true }).click();
  await expect(page.locator('html')).toHaveAttribute('data-mol-eval', 'EvalError');
  await expect(page.locator('html')).toHaveAttribute('data-mol-blocked', /script-src-elem/);
  await expect(page.locator('html')).toHaveAttribute('data-mol-blocked', /script-src-attr/);
  await expect(page.locator('html')).not.toHaveAttribute('data-mol-injected');
  await expect(page.locator('html')).not.toHaveAttribute('data-mol-handler');
  await expect(page.locator('.mol-editor-stage')).toBeVisible();
});

test('frame-ancestors alone allows the same origin and blocks a different origin', async ({ page }) => {
  await login(page);
  const editor = await page.request.get(fixture.editor_url);
  expect(editor.status()).toBe(200);
  const headers = { ...editor.headers() };
  // Isolate CSP enforcement from the legacy X-Frame-Options fallback. The editor
  // HTML and CSP are the real authenticated response; no test policy is invented.
  delete headers['x-frame-options'];
  await page.route(fixture.editor_url, route => route.fulfill({ response: editor, headers }));
  const parentPath = '/mol-security-frame-parent';
  await page.route('**' + parentPath, route => route.fulfill({ contentType: 'text/html', body:
    '<!doctype html><p id="frame-state">waiting</p><iframe id="editor-frame" src="' + fixture.editor_url + '" onload="document.getElementById(\'frame-state\').textContent=\'loaded\'"></iframe>' }));
  await page.goto('http://localhost:8080' + parentPath);
  await expect(page.locator('#frame-state')).toHaveText('loaded');
  await expect(page.frameLocator('#editor-frame').locator('#mol-editor-root')).toHaveCount(1);
  await page.goto('http://127.0.0.1:8080' + parentPath);
  await expect(page.locator('#frame-state')).toHaveText('loaded');
  await expect(page.frameLocator('#editor-frame').locator('#mol-editor-root')).toHaveCount(0);
});

test('editor redirects and access errors retain protection without imposing its policy on public pages', async ({ page }) => {
  const anonymous = await page.request.get(fixture.editor_url, { maxRedirects: 0 });
  expect(anonymous.status()).toBe(302);
  expect(anonymous.headers()['content-security-policy']).toContain("frame-ancestors 'self'");
  await login(page, true);
  const denied = await page.request.get(fixture.editor_url);
  expect(denied.status()).toBe(403);
  expect(denied.headers()['content-security-policy']).toContain("script-src-attr 'none'");
  expect(await denied.text()).not.toContain('mol-editor-data');
  const library = await page.request.get('/library/');
  expect(library.status()).toBe(200);
  expect(library.headers()['content-security-policy']).toBeUndefined();
});
