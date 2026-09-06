import type { components } from '../generated/api';
export type ReaderMode = components['schemas']['ReadingProgress']['reader_mode'];
export type Progress = components['schemas']['ReadingProgress'];
export type ProgressUpdate = components['schemas']['ReadingProgressUpdate'];
export interface ReaderPreferences { mode?: ReaderMode; translation?: boolean }

export function readerMode(saved: unknown, override: ReaderMode | null | undefined, work: ReaderMode): ReaderMode {
  return saved === 'webtoon' || saved === 'paged' ? saved : override ?? work;
}
export function pageDelta(key: string, direction: 'rtl' | 'ltr'): number {
  if (key === 'ArrowLeft') return direction === 'rtl' ? 1 : -1;
  if (key === 'ArrowRight') return direction === 'rtl' ? -1 : 1;
  return 0;
}
export function validProgress(value: unknown, chapterId: number, pageCount: number): Progress | null {
  if (!value || typeof value !== 'object') return null;
  const p = value as Partial<Progress>;
  if (p.chapter_id !== chapterId || !Number.isInteger(p.page_index) || !Number.isInteger(p.progress_unit) ||
    (p.page_index ?? -1) < 0 || (p.page_index ?? pageCount) >= pageCount || (p.progress_unit ?? -1) < 0 || (p.progress_unit ?? 1_000_001) > 1_000_000 ||
    !['webtoon', 'paged'].includes(p.reader_mode ?? '') || typeof p.updated_at !== 'string' || !Number.isFinite(Date.parse(p.updated_at))) return null;
  return p as Progress;
}
export function readLocal(key: string): unknown {
  try { return JSON.parse(localStorage.getItem(key) ?? 'null') as unknown; } catch { return null; }
}
export function writeLocal(key: string, value: unknown): boolean {
  try { localStorage.setItem(key, JSON.stringify(value)); return true; } catch { return false; }
}
