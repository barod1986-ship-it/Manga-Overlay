import { changeElement, workingCopy, type ElementChange, type WorkingElement } from './drafts.ts';
import type { Element } from './state.ts';
import type { components } from '../generated/api';

export type Lease = components['schemas']['LockLease'];
export type CreateBody = components['schemas']['ElementCreate'];
export type PatchBody = Partial<Pick<WorkingElement, 'x_unit' | 'y_unit' | 'w_unit' | 'h_unit' | 'rotation_mdeg' | 'z_index' | 'content' | 'style'>> & { element_type?: WorkingElement['element_type'] };
export type SaveState = 'clean' | 'dirty' | 'locking' | 'saving' | 'saved' | 'offline' | 'locked' | 'conflict' | 'error' | 'removed';
export interface PersistenceApi {
  create(body: CreateBody, key: string): Promise<Element>;
  patch(id: number, body: PatchBody, version: number, token: string): Promise<Element>;
  remove(id: number, version: number, token: string): Promise<void>;
  acquire(id: number): Promise<Lease>;
  renew(id: number, token: string): Promise<Lease>;
  release(id: number, token: string): Promise<void>;
  elements(pageId: number, signal: AbortSignal): Promise<Element[]>;
}
export class SaveError extends Error {
  readonly status: number; readonly code: string; readonly retryAfter: number;
  constructor(status: number, message: string, code = '', retryAfter = 0) { super(message); this.status = status; this.code = code; this.retryAfter = retryAfter; }
}
export interface RecordState {
  value: WorkingElement; pageId: number; state: SaveState; revision: number; dirty: boolean; deleting: boolean;
  busy: boolean; lease?: Lease; error?: SaveError; current?: Element | null; retryAt: number;
  envelope?: { body: CreateBody; key: string; revision: number };
  timer?: ReturnType<typeof setTimeout>;
}
const fields = ['x_unit', 'y_unit', 'w_unit', 'h_unit', 'rotation_mdeg', 'z_index', 'content', 'style'] as const;
const equal = (a: unknown, b: unknown) => JSON.stringify(a) === JSON.stringify(b);
function styleDifference(value: Record<string, unknown>, base: Record<string, unknown>): Record<string, unknown> {
  const result: Record<string, unknown> = {};
  for (const [key, next] of Object.entries(value)) {
    const previous = base[key];
    if (next && typeof next === 'object' && previous && typeof previous === 'object') {
      const nested = styleDifference(next as Record<string, unknown>, previous as Record<string, unknown>);
      if (Object.keys(nested).length) result[key] = nested;
    } else if (!equal(next, previous)) result[key] = next;
  }
  return result;
}
export function patchFor(value: WorkingElement): PatchBody {
  const patch: PatchBody = {};
  for (const field of fields) {
    if (field === 'style') {
      const style = styleDifference(value.style, value.source?.style ?? {});
      if (Object.keys(style).length) { patch.style = style; patch.element_type = value.element_type; }
    } else if (!value.source || !equal(value[field], value.source[field] ?? (field === 'rotation_mdeg' || field === 'z_index' ? 0 : undefined))) Object.assign(patch, { [field]: value[field] });
  }
  return patch;
}
function mergeStyle(base: Record<string, unknown>, patch: Record<string, unknown>): Record<string, unknown> {
  const result = structuredClone(base);
  for (const [key, value] of Object.entries(patch)) result[key] = value && typeof value === 'object' && result[key] && typeof result[key] === 'object' ? mergeStyle(result[key] as Record<string, unknown>, value as Record<string, unknown>) : value;
  return result;
}
export const SAVE_LABELS: Record<SaveState, string> = {
  clean: 'جاهز للتحرير', dirty: 'تغييرات غير محفوظة', locking: 'جارٍ حجز العنصر…', saving: 'جارٍ الحفظ…', saved: 'تم الحفظ',
  offline: 'غير متصل — تغييرات هذه الجلسة لم تُرسل بعد', locked: 'العنصر مقفل — التعديل متوقف', conflict: 'تعارض — قارن النسختين', error: 'تعذر الحفظ', removed: 'حُذف العنصر',
};

/** One serialized writer per element. Unsent content and lease tokens exist only in memory. */
export class EditorSession {
  readonly records = new Map<string, RecordState>();
  private pages = new Set<number>();
  private listeners = new Set<() => void>();
  private selected: string | null = null;
  private disposed = false;
  private interval: ReturnType<typeof setInterval>;
  online = true;
  sessionError: SaveError | null = null;
  private api: PersistenceApi; private debounce: number;
  constructor(api: PersistenceApi, debounce = 1200) { this.api = api; this.debounce = debounce; this.interval = setInterval(() => void this.renewActive(), 15000); }
  subscribe(listener: () => void) { this.listeners.add(listener); return () => { this.listeners.delete(listener); }; }
  private emit() { if (!this.disposed) for (const listener of this.listeners) listener(); }
  observe(pageId: number, elements: Element[]) {
    if (this.pages.has(pageId)) return;
    this.pages.add(pageId);
    for (const element of elements) this.insert(pageId, workingCopy(element), false);
    this.emit();
  }
  private insert(pageId: number, value: WorkingElement, dirty: boolean) {
    const record: RecordState = { pageId, value, state: dirty ? 'dirty' : 'clean', revision: 0, dirty, deleting: false, busy: false, retryAt: 0 };
    this.records.set(value.key, record); return record;
  }
  elements(pageId: number) { return [...this.records.values()].filter(record => record.pageId === pageId && !record.deleting && record.state !== 'removed').map(record => record.value); }
  observed(pageId: number) { return this.pages.has(pageId); }
  get dirty() { return [...this.records.values()].some(record => record.dirty || record.busy && record.state === 'saving'); }
  get state(): SaveState {
    const states = [...this.records.values()].map(record => record.state);
    return ['offline', 'conflict', 'locked', 'error', 'saving', 'dirty', 'locking', 'saved'].find(state => states.includes(state as SaveState)) as SaveState ?? 'clean';
  }
  editable(key: string) {
    const record = this.records.get(key);
    return !!record && !this.sessionError && !record.deleting && !['locked', 'conflict', 'locking', 'removed'].includes(record.state)
      && (!record.value.source || !!record.lease && Date.parse(record.lease.expires_at) > Date.now());
  }
  add(pageId: number, value: WorkingElement) { const record = this.insert(pageId, value, true); this.schedule(record); this.emit(); }
  change(key: string, patch: ElementChange, immediate = false) {
    const record = this.records.get(key);
    if (!record || !this.editable(key)) return;
    record.value = changeElement(record.value, patch); ++record.revision; record.dirty = true;
    if (!record.busy) record.state = this.online ? 'dirty' : 'offline';
    this.schedule(record, immediate ? 0 : this.debounce); this.emit();
  }
  delete(key: string) {
    const record = this.records.get(key);
    if (!record || !this.editable(key)) return;
    record.deleting = true; record.dirty = true; ++record.revision;
    this.schedule(record); this.emit();
  }
  undo(key: string) {
    const record = this.records.get(key);
    if (!record || !record.deleting || record.busy || record.state === 'removed') return false;
    record.deleting = false; ++record.revision;
    record.dirty = !record.value.source || Object.keys(patchFor(record.value)).length > 0;
    record.state = record.dirty ? 'dirty' : 'saved'; this.schedule(record); this.emit(); return true;
  }
  select(key: string | null, acquire = true) {
    const previous = this.selected; this.selected = acquire ? key : null;
    if (previous && previous !== this.selected) {
      const record = this.records.get(previous);
      if (record && !record.dirty && !record.busy) void this.release(record);
    }
    const record = key ? this.records.get(key) : undefined;
    if (acquire && record?.value.source && !record.busy && !record.lease && !['conflict', 'locked', 'error'].includes(record.state)) void this.acquire(record);
  }
  private async acquire(record: RecordState) {
    if (!record.value.source || record.busy || this.disposed || this.sessionError) return;
    const idleState = record.state === 'saved' ? 'saved' : 'clean';
    record.busy = true; record.state = 'locking'; this.emit();
    try { record.lease = await this.api.acquire(record.value.source.id); record.state = record.dirty ? 'dirty' : idleState; record.error = undefined; }
    catch (error) { await this.fail(record, error); }
    finally { record.busy = false; if (record.dirty && record.state === 'dirty') this.schedule(record); this.emit(); }
  }
  private schedule(record: RecordState, delay = this.debounce) {
    clearTimeout(record.timer);
    if (!record.dirty || this.disposed || this.sessionError) return;
    record.timer = setTimeout(() => { record.timer = undefined; void this.save(record.value.key); }, delay);
  }
  async save(key: string): Promise<void> {
    const record = this.records.get(key);
    if (!record || !record.dirty || record.busy || this.disposed || this.sessionError || ['locked', 'conflict', 'error'].includes(record.state)) return;
    if (!this.online) { record.state = 'offline'; this.emit(); return; }
    if (record.retryAt > Date.now()) { this.schedule(record, record.retryAt - Date.now()); return; }
    record.busy = true; record.state = 'saving'; const revision = record.revision; this.emit();
    try {
      if (record.value.source && (!record.lease || Date.parse(record.lease.expires_at) <= Date.now())) record.lease = await this.api.acquire(record.value.source.id);
      if (record.deleting && !record.value.source && !record.envelope) { record.state = 'removed'; record.dirty = false; return; }
      if (record.deleting && record.value.source) {
        try { await this.api.remove(record.value.source.id, record.value.source.version, record.lease!.lock_token); }
        catch (error) { if (!(error instanceof SaveError) || error.status !== 404) throw error; }
        record.state = 'removed'; record.dirty = false; record.lease = undefined; return;
      }
      let saved: Element;
      let sentRevision = revision;
      if (!record.value.source) {
        // Keep both request bytes and key stable across ambiguous POST failures, even if typing continues.
        record.envelope ??= { key: crypto.randomUUID(), revision, body: structuredClone({ page_id: record.pageId, target_lang: record.value.target_lang,
          element_type: record.value.element_type, ...Object.fromEntries(fields.map(field => [field, record.value[field]])) }) as CreateBody };
        sentRevision = record.envelope.revision;
        saved = await this.api.create(record.envelope.body, record.envelope.key);
        record.envelope = undefined;
      } else {
        const patch = patchFor(record.value);
        if (!Object.keys(patch).length) { record.dirty = false; record.state = 'saved'; return; }
        saved = await this.api.patch(record.value.source.id, patch, record.value.source.version, record.lease!.lock_token);
      }
      if (record.revision === sentRevision && !record.deleting) record.value = { ...workingCopy(saved), key };
      else record.value = { ...record.value, source: structuredClone(saved) };
      record.dirty = record.revision !== sentRevision || record.deleting;
      record.state = record.dirty ? 'dirty' : 'saved'; record.error = undefined;
    } catch (error) { await this.fail(record, error); }
    finally {
      record.busy = false;
      if (record.dirty && record.state === 'dirty') this.schedule(record);
      else if (!record.dirty && this.selected !== key) void this.release(record);
      // Newly persisted selected elements need a lease before another edit.
      if (this.selected === key && record.value.source && !record.lease && record.state === 'saved') void this.acquire(record);
      this.emit();
    }
  }
  private async fail(record: RecordState, error: unknown) {
    record.error = error instanceof SaveError ? error : new SaveError(0, 'تعذر الاتصال. تبقى التغييرات في هذه الجلسة لإعادة المحاولة.');
    const { status, retryAfter } = record.error;
    record.retryAt = Date.now() + retryAfter * 1000;
    record.state = status === 412 || status === 428 ? 'conflict' : status === 409 || status === 423 ? 'locked' : status === 0 ? 'offline' : 'error';
    if (status === 401 || status === 403) { this.sessionError = record.error; for (const entry of this.records.values()) clearTimeout(entry.timer); }
    if (record.state === 'locked') record.lease = undefined;
    if (record.state === 'conflict') { record.deleting = false; await this.fetchCurrent(record); }
  }
  private async fetchCurrent(record: RecordState) {
    try { record.current = (await this.api.elements(record.pageId, new AbortController().signal)).find(element => element.id === record.value.source?.id) ?? null; }
    catch { record.current = undefined; }
  }
  async retry(key: string) {
    const record = this.records.get(key);
    if (!record || record.busy || this.sessionError || Date.now() < record.retryAt) return;
    if (record.state === 'conflict') { await this.fetchCurrent(record); this.emit(); return; }
    // Re-read version after ambiguous PATCH/network failures. Never apply local changes to a newer version silently.
    if (record.value.source && record.dirty && !record.deleting) {
      await this.fetchCurrent(record);
      if (record.current === undefined) { this.emit(); return; }
      if (record.current?.version !== record.value.source.version) { record.state = 'conflict'; this.emit(); return; }
    }
    record.state = record.dirty ? 'dirty' : 'clean'; record.error = undefined;
    if (record.value.source && !record.lease) await this.acquire(record);
    if (record.dirty) await this.save(key); this.emit();
  }
  resolve(key: string, reapply: boolean) {
    const record = this.records.get(key);
    if (!record || record.state !== 'conflict' || record.current === undefined) return;
    if (!record.current) { if (!reapply) { record.state = 'removed'; record.dirty = false; record.deleting = true; record.error = undefined; this.emit(); } return; }
    const patch = patchFor(record.value);
    record.value = { ...workingCopy(record.current), key };
    if (reapply) record.value = changeElement(record.value, { ...patch, ...(patch.style ? { style: mergeStyle(record.value.style, patch.style) } : {}) } as ElementChange);
    record.dirty = reapply; ++record.revision; record.current = undefined; record.error = undefined;
    record.state = reapply ? 'dirty' : 'saved'; this.schedule(record); this.emit();
  }
  private async renewActive() {
    if (this.disposed || !this.online || this.sessionError) return;
    for (const [key, record] of this.records) {
      if (!record.lease || record.busy || record.state === 'removed' || !record.value.source || key !== this.selected && !record.dirty) continue;
      record.busy = true;
      try { record.lease = await this.api.renew(record.value.source.id, record.lease.lock_token); }
      catch (error) { await this.fail(record, error); record.lease = undefined; }
      finally { record.busy = false; if (record.dirty && record.state === 'dirty') this.schedule(record); this.emit(); }
    }
  }
  private async release(record: RecordState) {
    const lease = record.lease;
    if (!lease || record.busy) return;
    record.busy = true; record.lease = undefined;
    try { await this.api.release(lease.element_id, lease.lock_token); } catch { /* Lease expires after 45 seconds. */ }
    finally {
      record.busy = false;
      if (!this.disposed && this.selected === record.value.key) void this.acquire(record);
      else if (record.dirty) this.schedule(record);
      this.emit();
    }
  }
  setOnline(online: boolean) {
    this.online = online;
    for (const record of this.records.values()) if (record.dirty && !record.busy) {
      if (!online && !['conflict', 'locked', 'error'].includes(record.state)) record.state = 'offline';
      else if (online && record.state === 'offline') void this.retry(record.value.key);
    }
    this.emit();
  }
  block(status: number) {
    if (this.sessionError) return;
    this.sessionError = new SaveError(status, 'انتهت الجلسة أو تغيرت الصلاحيات.');
    for (const record of this.records.values()) clearTimeout(record.timer); this.emit();
  }
  dispose() { this.disposed = true; clearInterval(this.interval); for (const record of this.records.values()) { clearTimeout(record.timer); if (!record.busy) void this.release(record); } }
}
