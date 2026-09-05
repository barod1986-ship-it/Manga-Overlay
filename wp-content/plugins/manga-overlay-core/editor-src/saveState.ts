import { ElementApiError } from './elementApi';
import type { SaveState, SaveStateKind } from './types';

const LABELS: Readonly<Record<SaveStateKind, string>> = {
  dirty: 'تغييرات غير محفوظة',
  saving: 'جارٍ الحفظ',
  saved: 'تم الحفظ',
  offline: 'غير متصل — لم تُرسل تغييرات هذه الجلسة',
  locked: 'العنصر قيد التحرير لدى مستخدم آخر',
  conflict: 'تعارض مع نسخة أحدث',
  error: 'تعذر الحفظ — يمكنك إعادة المحاولة',
};

export function saveState(kind: SaveStateKind, message = LABELS[kind]): SaveState {
  return { kind, message, canRetry: kind === 'offline' || kind === 'locked' || kind === 'error' };
}

export function stateForError(error: unknown, online: boolean): SaveState {
  if (!online || (error instanceof ElementApiError && error.status === 0)) {
    return saveState('offline');
  }
  if (error instanceof ElementApiError && error.status === 423) {
    return saveState('locked');
  }
  if (error instanceof ElementApiError && error.status === 412) {
    return saveState('conflict');
  }
  if (error instanceof ElementApiError && error.status === 428) {
    return saveState('error', 'تعذر إرسال شرط النسخة If‑Match — تحقق من إعدادات الخادم ثم أعد المحاولة');
  }

  return saveState('error');
}
