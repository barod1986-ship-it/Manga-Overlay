import type { components } from '../generated/api';
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
}
