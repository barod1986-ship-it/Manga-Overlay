import { useEffect, useRef, useState } from 'react';
import type { components } from '../generated/api';

type Create = components['schemas']['ReportCreate'];
interface Props {
  chapterId: number; pages: components['schemas']['Page'][]; overlays: components['schemas']['ChapterElementsResponse']['data'];
  api: string; nonce: string | null; canReport: boolean; loginUrl: string; currentPage: () => number | undefined;
}
const types: Record<Create['report_type'], string> = { translation: 'خطأ ترجمة', placement: 'موضع الترجمة', style: 'المظهر والخط', missing: 'ترجمة ناقصة', other: 'مشكلة أخرى' };
export function ReportForm({ chapterId, pages, overlays, api, nonce, canReport, loginUrl, currentPage }: Props) {
  const dialog = useRef<HTMLDialogElement>(null), trigger = useRef<HTMLButtonElement>(null), inFlight = useRef(false);
  const [scope, setScope] = useState<'chapter' | 'page' | 'element'>(pages.length ? 'page' : 'chapter');
  const [pageId, setPageId] = useState(pages[0]?.id ?? 0), [elementId, setElementId] = useState(0);
  const [type, setType] = useState<Create['report_type']>('translation'), [message, setMessage] = useState('');
  const [phase, setPhase] = useState<'editing' | 'sending' | 'sent' | 'unknown' | 'blocked'>('editing');
  const [notice, setNotice] = useState(''), [retryAt, setRetryAt] = useState(0), [remaining, setRemaining] = useState(0);
  const elements = overlays.find(page => page.page_id === pageId)?.elements ?? [];
  useEffect(() => {
    const update = () => setRemaining(Math.max(0, Math.ceil((retryAt - Date.now()) / 1000)));
    update(); const timer = window.setInterval(update, 1000); return () => clearInterval(timer);
  }, [retryAt]);
  function open() {
    if (phase === 'editing' && !message) { const id = currentPage() ?? pages[0]?.id ?? 0; setPageId(id); setElementId(0); setScope(id ? 'page' : 'chapter'); }
    dialog.current?.showModal();
  }
  async function send() {
    if (inFlight.current || phase !== 'editing' || Date.now() < retryAt || !message.trim() || !nonce || !canReport || scope === 'element' && !elementId) return;
    const payload: Create = { chapter_id: chapterId, report_type: type, message: message.trim(), ...(scope !== 'chapter' ? { page_id: pageId } : {}), ...(scope === 'element' ? { element_id: elementId } : {}) };
    inFlight.current = true; setPhase('sending'); setNotice('جارٍ إرسال البلاغ…');
    try {
      const response = await fetch(api + 'reports', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce }, body: JSON.stringify(payload) });
      if (response.status === 429) {
        const seconds = Number(response.headers.get('Retry-After'));
        setRetryAt(Date.now() + (Number.isFinite(seconds) && seconds > 0 ? seconds : 60) * 1000);
        setPhase('editing'); setNotice('وصلت إلى حد الإرسال المؤقت. يمكنك المحاولة بعد انتهاء الانتظار.'); return;
      }
      if (response.status === 401 || response.status === 403) { setPhase('blocked'); setNotice('انتهت الجلسة أو سُحبت صلاحية التبليغ. احتفظ بالنص وحدّث الصفحة.'); return; }
      if (response.status === 400) {
        const result = await response.json(); setPhase('editing'); setNotice(typeof result.message === 'string' ? result.message : 'تحقق من بيانات البلاغ.'); return;
      }
      if (response.status !== 201) throw new Error('unconfirmed');
      const result = await response.json() as components['schemas']['ReportResponse'];
      if (!Number.isInteger(result.data?.id) || result.data.id < 1) throw new Error('unconfirmed');
      setPhase('sent'); setNotice(`وصل بلاغك #${result.data.id} إلى المشرفين. شكرًا لتوضيح المشكلة.`);
    } catch {
      // ReportCreate has no idempotency contract. Never silently repeat an ambiguous POST.
      setPhase('unknown'); setNotice('تعذر تأكيد وصول البلاغ. قد يكون وصل بالفعل؛ احتفظ بالنص وتحقق مع المشرف قبل إرساله مجددًا.');
    } finally { inFlight.current = false; }
  }
  if (!nonce) return <a href={loginUrl}>سجّل الدخول للإبلاغ</a>;
  if (!canReport) return null;
  const locked = phase !== 'editing';
  return <><button ref={trigger} type="button" onClick={open}>الإبلاغ عن مشكلة</button>
    <dialog ref={dialog} className="mol-report-dialog" aria-labelledby="mol-report-title" dir="rtl" onCancel={event => { if (inFlight.current) event.preventDefault(); }} onClose={() => trigger.current?.focus()}>
      <header><h2 id="mol-report-title">الإبلاغ عن مشكلة</h2><button autoFocus type="button" disabled={phase === 'sending'} onClick={() => dialog.current?.close()}>إغلاق البلاغ</button></header>
      <form onSubmit={event => { event.preventDefault(); void send(); }}>
        <fieldset disabled={locked}>
          <label htmlFor="mol-report-scope">موضع المشكلة</label><select id="mol-report-scope" value={scope} onChange={event => { setScope(event.target.value as typeof scope); setElementId(elements[0]?.id ?? 0); }}>
            <option value="chapter">الفصل كاملًا</option>{pages.length > 0 && <><option value="page">صفحة</option><option value="element">عنصر ترجمة</option></>}
          </select>
          {scope !== 'chapter' && <><label htmlFor="mol-report-page">صفحة البلاغ</label><select id="mol-report-page" value={pageId} onChange={event => { const id = Number(event.target.value); setPageId(id); setElementId(overlays.find(page => page.page_id === id)?.elements[0]?.id ?? 0); }}>{pages.map(page => <option key={page.id} value={page.id}>{page.page_index + 1}</option>)}</select></>}
          {scope === 'element' && <><label htmlFor="mol-report-element">عنصر الترجمة</label><select id="mol-report-element" value={elementId} required onChange={event => setElementId(Number(event.target.value))}>
            {!elements.length && <option value={0}>لا توجد عناصر في هذه الصفحة</option>}{elements.map((element, index) => <option key={element.id} value={element.id}>{index + 1} · {element.content.slice(0, 90) || 'عنصر بلا نص'}</option>)}
          </select></>}
          <label htmlFor="mol-report-type">نوع المشكلة</label><select id="mol-report-type" value={type} onChange={event => setType(event.target.value as Create['report_type'])}>{Object.entries(types).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select>
        </fieldset>
        <label htmlFor="mol-report-message">وصف المشكلة</label><textarea id="mol-report-message" value={message} onChange={event => setMessage(event.target.value)} readOnly={locked} required maxLength={4000} rows={4} />
        {phase !== 'sent' && <button type="submit" disabled={locked || remaining > 0 || !message.trim() || scope === 'element' && !elementId}>{phase === 'sending' ? 'جارٍ الإرسال…' : 'إرسال البلاغ'}</button>}
        {notice && <p role={['unknown', 'blocked'].includes(phase) ? 'alert' : 'status'}>{notice}</p>}
        {remaining > 0 && <p role="status">يمكن إعادة المحاولة بعد {remaining} ثانية.</p>}
        {phase === 'sent' && <button type="button" onClick={() => { setPhase('editing'); setMessage(''); setNotice(''); }}>كتابة بلاغ آخر</button>}
      </form>
    </dialog>
  </>;
}
