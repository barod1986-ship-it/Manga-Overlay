import type { Preset, PresetCreate, PresetPatch } from './presets';
import type { components } from '../generated/api';
import { SaveError, type CreateBody, type PatchBody, type Lease } from './persistence';
import type { Bootstrap, Chapter, Page, Element } from './state';

export class EditorError extends Error {
  constructor(readonly status: number) {
    super(status === 401 || status === 403 ? 'انتهت الجلسة أو لم تعد لديك صلاحية الوصول. حدّث الصفحة للمتابعة.'
      : status === 404 ? 'هذا المحتوى غير متاح. ربما حُذف أو تغيرت صلاحية الوصول إليه.'
        : status === 429 ? 'طلبات كثيرة في وقت قصير. انتظر قليلًا ثم أعد المحاولة.' : 'تعذر تحميل المحتوى. تحقق من الاتصال وأعد المحاولة.');
  }
}

export class EditorApi {
  constructor(private readonly boot: Bootstrap) {}

  private async get<T>(path: string, signal: AbortSignal): Promise<T> {
    // Appending to the WordPress base preserves both pretty and ?rest_route= URLs.
    const response = await fetch(this.boot.api.replace(/\/$/, '') + '/' + path, {
      credentials: 'same-origin', cache: 'no-store', signal, headers: { 'X-WP-Nonce': this.boot.nonce },
    });
    if (!response.ok) throw new EditorError(response.status);
    return await response.json() as T;
  }

  async chapter(signal: AbortSignal): Promise<{ chapter: Chapter; pages: Page[] }> {
    const [chapter, pages] = await Promise.all([
      this.get<components['schemas']['ChapterResponse']>('chapters/' + this.boot.chapterId, signal),
      this.get<components['schemas']['PageListResponse']>('chapters/' + this.boot.chapterId + '/pages', signal),
    ]);
    if (chapter.data.id !== this.boot.chapterId || !Array.isArray(pages.data) || pages.data.some(page => page.chapter_id !== this.boot.chapterId)) throw new EditorError(500);
    return { chapter: chapter.data, pages: pages.data };
  }

  async elements(pageId: number, signal: AbortSignal): Promise<Element[]> {
    const response = await this.get<components['schemas']['PageElementsResponse']>('pages/' + pageId + '/elements', signal);
    if (!Array.isArray(response.data) || response.data.some(element => element.page_id !== pageId)) throw new EditorError(500);
    return response.data;
  }

  private async write<T>(path: string, method: string, body?: unknown, headers: Record<string, string> = {}): Promise<T> {
    const response = await fetch(this.boot.api.replace(/\/$/, '') + '/' + path, { method, credentials: 'same-origin', cache: 'no-store', signal: AbortSignal.timeout(20000),
      headers: { 'X-WP-Nonce': this.boot.nonce, ...(body === undefined ? {} : { 'Content-Type': 'application/json' }), ...headers },
      body: body === undefined ? undefined : JSON.stringify(body) });
    const result = response.status === 204 ? null : await response.json();
    if (!response.ok) throw new SaveError(response.status, result?.message || 'تعذر إكمال الحفظ.', result?.code || '', Number(response.headers.get('Retry-After') || 0));
    if (result?.data?.version !== undefined && response.headers.get('ETag') !== `"${result.data.version}"`) throw new SaveError(500, 'تعذر تأكيد نسخة الحفظ. أعد تحميل بيانات العنصر قبل المحاولة.');
    return result?.data as T;
  }
  async presets(workId: number, signal: AbortSignal): Promise<Preset[]> {
    const query = new URLSearchParams({ work_id: String(workId) });
    const response = await this.get<components['schemas']['PresetListResponse']>('presets' + (this.boot.api.includes('?') ? '&' : '?') + query, signal);
    return response.data;
  }
  createPreset(body: PresetCreate) { return this.write<Preset>('presets', 'POST', body); }
  updatePreset(id: number, body: PresetPatch) { return this.write<Preset>('presets/' + id, 'PATCH', body); }
  deletePreset(id: number) { return this.write<void>('presets/' + id, 'DELETE'); }
  create(body: CreateBody, key: string) { return this.write<Element>('elements', 'POST', body, { 'MOL-Idempotency-Key': key }); }
  patch(id: number, body: PatchBody, version: number, token: string) { return this.write<Element>('elements/' + id, 'PATCH', body, { 'If-Match': `"${version}"`, 'X-MOL-Lock-Token': token }); }
  remove(id: number, version: number, token: string) { return this.write<void>('elements/' + id, 'DELETE', undefined, { 'If-Match': `"${version}"`, 'X-MOL-Lock-Token': token }); }
  acquire(id: number) { return this.write<Lease>('elements/' + id + '/lock', 'POST'); }
  renew(id: number, token: string) { return this.write<Lease>('elements/' + id + '/lock', 'PUT', undefined, { 'X-MOL-Lock-Token': token }); }
  release(id: number, token: string) { return this.write<void>('elements/' + id + '/lock', 'DELETE', undefined, { 'X-MOL-Lock-Token': token }); }

}
