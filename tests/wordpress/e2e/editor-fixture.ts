import { expect, type APIRequestContext, type APIRequest, type Page } from '@playwright/test';
import { readFileSync } from 'node:fs';
export const fixture = JSON.parse(readFileSync(process.env.MOL_HTTP_FIXTURE!, 'utf8')) as Record<string, any>;
export async function managerApi(playwright: { request: APIRequest }) {
  const request = await playwright.request.newContext({ baseURL: 'http://localhost:8080' });
  await request.get('/wp-login.php');
  expect((await request.post('/wp-login.php', { form: { log: fixture.username, pwd: fixture.password, 'wp-submit': 'Log In', testcookie: '1' }, maxRedirects: 0 })).status()).toBe(302);
  const html = await (await request.get('/wp-admin/admin.php?page=manga-overlay')).text();
  const boot = JSON.parse(html.match(/<script[^>]+id="mol-content-data"[^>]*>([\s\S]*?)<\/script>/)![1]);
  return { request, boot, call: async (method: string, path: string, data?: unknown, headers: Record<string, string> = {}) => request.fetch(boot.api + path, { method, data, headers: { 'X-WP-Nonce': boot.nonce, ...headers } }) };
}
export async function resetEditor(playwright: { request: APIRequest }) {
  const admin = await managerApi(playwright);
  try {
    for (const pageId of fixture.editor_page_ids as number[]) {
      const current = (await (await admin.call('GET', 'pages/' + pageId + '/elements')).json()).data;
      for (const element of current) {
        await admin.call('DELETE', `elements/${element.id}/lock`);
        const lock = await admin.call('POST', `elements/${element.id}/lock`); expect(lock.status()).toBe(200);
        const token = (await lock.json()).data.lock_token;
        const baseline = fixture.editor_original_elements.find((value: any) => value.id === element.id);
        const headers = { 'If-Match': `"${element.version}"`, 'X-MOL-Lock-Token': token };
        if (baseline) {
          const fields = ['x_unit', 'y_unit', 'w_unit', 'h_unit', 'rotation_mdeg', 'z_index', 'content', 'style', 'element_type'];
          const patch = Object.fromEntries(fields.map(key => [key, baseline[key]]));
          patch.style = { ...patch.style, shadow: null, ...(baseline.element_type === 'bubble' ? { tail: null } : {}), ...(baseline.element_type === 'sfx' ? { burst: null, scaleX: 1, scaleY: 1 } : {}) };
          expect((await admin.call('PATCH', 'elements/' + element.id, patch, headers)).status()).toBe(200);
          await admin.call('DELETE', `elements/${element.id}/lock`);
        } else expect((await admin.call('DELETE', 'elements/' + element.id, undefined, headers)).status()).toBe(204);
      }
    }
  } finally { await admin.request.dispose(); }
}
export async function readElements(request: APIRequestContext, api: string, nonce: string, pageId: number) {
  const response = await request.get(api + 'pages/' + pageId + '/elements', { headers: { 'X-WP-Nonce': nonce } });
  expect(response.status()).toBe(200); return (await response.json()).data;
}

export async function openPropertySection(page: Page, label: string) {
  const section = page.locator('details[data-property-section]').filter({ has: page.locator('summary', { hasText: new RegExp('^' + label + '$') }) });
  if (!await section.evaluate(node => (node as HTMLDetailsElement).open)) await section.locator(':scope > summary').click();
}
