import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { ELEMENT_LABELS } from '../domain/baseStyles';
import type { ElementType } from '../domain/types';
import { EditorApi, EditorError } from './api';
import { activePage, parseRoute, routeHash, type Bootstrap, type EditorRoute } from './state';
import { createElement, duplicateElement, workingCopy, workingLayers, type ElementChange } from './drafts';
import { EditorSession, SAVE_LABELS } from './persistence';
import { useResource } from './useResource';
import { Stage } from './Stage';
import { Presets } from './Presets';
import { defaultPreset, presetStyle } from './presets';
import { Properties } from './Properties';
import { useMobileViewport } from './useMobileViewport';

const types: ElementType[] = ['bubble', 'narration', 'free_text', 'sfx'];
export function App({ boot }: { boot: Bootstrap }) {
  const api = useMemo(() => new EditorApi(boot), [boot]);
  const [route, setRoute] = useState(() => parseRoute(location.hash));
  const [attempt, setAttempt] = useState(0);
  const [pageAttempt, setPageAttempt] = useState(0);
  const [preview, setPreview] = useState(false);
  const root = useRef<HTMLDivElement>(null);
  useMobileViewport(root, preview);
  const [visible, setVisible] = useState(true);
  const [zoom, setZoom] = useState(1);
  const [snapping, setSnapping] = useState(true);
  const [presetAttempt, setPresetAttempt] = useState(0);
  const [panel, setPanel] = useState<'layers' | 'properties' | null>(null);
  const session = useMemo(() => new EditorSession(api), [api]);
  const [, renderSession] = useState(0);
  const [deletedKey, setDeletedKey] = useState<string | null>(null);
  useEffect(() => session.subscribe(() => renderSession(value => value + 1)), [session]);
  useEffect(() => {
    const online = () => session.setOnline(navigator.onLine); online();
    window.addEventListener('online', online); window.addEventListener('offline', online);
    return () => { window.removeEventListener('online', online); window.removeEventListener('offline', online); session.dispose(); };
  }, [session]);
  const [localSelection, setLocalSelection] = useState<{ pageId: number; key: string } | null>(null);
  const textRef = useRef<HTMLTextAreaElement>(null);
  const propertiesButton = useRef<HTMLButtonElement>(null);
  const chapterLoad = useResource('chapter-' + attempt, useCallback((signal: AbortSignal) => api.chapter(signal), [api]));
  const pages = chapterLoad.status === 'ready' ? chapterLoad.data.pages : [];
  const page = activePage(pages, route.pageId);
  const pageId = page?.id ?? null;
  const elementLoad = useResource(pageId === null ? null : `${pageId}-${attempt}-${pageAttempt}`,
    useCallback((signal: AbortSignal) => api.elements(pageId!, signal), [api, pageId]));
  const sourceElements = useMemo(() => elementLoad.status === 'ready' ? elementLoad.data.map(workingCopy) : [], [elementLoad]);
  useEffect(() => { if (pageId !== null && elementLoad.status === 'ready') session.observe(pageId, elementLoad.data); }, [session, pageId, elementLoad]);
  // Never display a cached draft over a loading/error response, especially after access is lost.
  const elements = elementLoad.status === 'ready' ? pageId !== null && session.observed(pageId) ? session.elements(pageId) : sourceElements : [];
  const selectedKey = localSelection?.pageId === pageId ? localSelection.key : route.elementId === null ? null : elements.find(element => element.source?.id === route.elementId)?.key ?? String(route.elementId);
  const selected = elements.find(element => element.key === selectedKey);
  const chapter = chapterLoad.status === 'ready' ? chapterLoad.data.chapter : null;
  const workId = chapter?.work_id ?? null;
  const presetLoad = useResource(workId === null ? null : `${workId}-${presetAttempt}`, useCallback((signal: AbortSignal) => api.presets(workId!, signal), [api, workId]));
  const presets = presetLoad.status === 'ready' ? presetLoad.data : [];
  const error = session.sessionError ? new EditorError(session.sessionError.status) : chapterLoad.status === 'error' ? chapterLoad.error : elementLoad.status === 'error' ? elementLoad.error : null;
  const blocked = error?.status === 401 || error?.status === 403;
  const canEdit = boot.canEdit === true && !!page && elementLoad.status === 'ready' && !error;
  const editing = canEdit && !preview && visible;
  const selectedRecord = selected ? session.records.get(selected.key) : undefined;
  const selectedEditable = editing && !!selected && session.editable(selected.key);
  const dirty = session.dirty;
  const deleted = deletedKey ? session.records.get(deletedKey) : undefined;
  useEffect(() => { session.select(selected?.key ?? null, boot.canEdit && !preview && visible && !blocked); }, [session, selected?.key, boot.canEdit, preview, visible, blocked]);
  useEffect(() => {
    if (selectedRecord && ['locked', 'conflict', 'error', 'offline'].includes(selectedRecord.state) && window.matchMedia('(max-width:800px)').matches) {
      // Expose recovery actions above the canvas without discarding the local edit.
      textRef.current?.blur();
      setPanel(null);
    }
  }, [selected?.key, selectedRecord?.state]);

  const navigate = useCallback((next: EditorRoute, replace = false) => {
    const hash = routeHash(next);
    if (location.hash !== hash) history[replace ? 'replaceState' : 'pushState'](null, '', location.pathname + location.search + hash);
    setRoute(next);
  }, []);
  useEffect(() => {
    const restore = () => { setRoute(parseRoute(location.hash)); setLocalSelection(null); setPanel(null); };
    window.addEventListener('popstate', restore); window.addEventListener('hashchange', restore);
    return () => { window.removeEventListener('popstate', restore); window.removeEventListener('hashchange', restore); };
  }, []);
  useEffect(() => { setZoom(1); setPanel(null); setLocalSelection(current => current?.pageId === pageId ? current : null); }, [pageId]);
  useEffect(() => { if (blocked) { session.block(error!.status); setLocalSelection(null); setPanel(null); } }, [blocked, session, error]);
  useEffect(() => {
    if (chapterLoad.status !== 'ready') return;
    if (pageId !== route.pageId) navigate({ pageId, elementId: null }, true);
    else if (elementLoad.status === 'ready' && route.elementId !== null && !selected) navigate({ pageId, elementId: null }, true);
  }, [chapterLoad.status, elementLoad.status, pageId, route.pageId, route.elementId, selected, navigate]);
  useEffect(() => {
    if (!dirty) return;
    const leave = (event: BeforeUnloadEvent) => { event.preventDefault(); event.returnValue = ''; };
    window.addEventListener('beforeunload', leave);
    return () => window.removeEventListener('beforeunload', leave);
  }, [dirty]);

  function select(key: string | null, open = true) {
    const element = elements.find(item => item.key === key);
    setLocalSelection(key !== null && pageId !== null ? { pageId, key } : null);
    navigate({ pageId, elementId: element?.source?.id ?? null });
    if (key !== null && open) setPanel('properties');
    else if (key === null) setPanel(null);
  }
  function change(key: string, patch: ElementChange, immediate = false) {
    if (editing) session.change(key, patch, immediate);
  }
  function focusText() { window.setTimeout(() => textRef.current?.focus(), 0); }
  function add(type: ElementType) {
    if (!editing || pageId === null || presetLoad.status !== 'ready') return;
    const element = createElement(type, elements, 'draft:' + crypto.randomUUID());
    element.style = presetStyle(type, defaultPreset(type, presets));
    session.add(pageId, element);
    setLocalSelection({ pageId, key: element.key }); navigate({ pageId, elementId: null }); setPanel('properties'); focusText();
  }
  function duplicate() {
    if (!selectedEditable || !selected || pageId === null) return;
    const copy = duplicateElement(selected, elements, 'draft:' + crypto.randomUUID());
    session.add(pageId, copy);
    setLocalSelection({ pageId, key: copy.key }); navigate({ pageId, elementId: null }); setPanel('properties');
  }
  function remove() {
    if (!selectedEditable || !selected || !boot.canDelete) return;
    if (selected.source && !window.confirm('حذف هذا العنصر من الترجمة؟')) return;
    session.delete(selected.key); setDeletedKey(selected.key); select(null);
  }
  function undoDelete() {
    if (!deletedKey || !deleted || !session.undo(deletedKey)) return;
    setLocalSelection({ pageId: deleted.pageId, key: deletedKey });
    navigate({ pageId: deleted.pageId, elementId: deleted.value.source?.id ?? null }); setPanel('properties'); setDeletedKey(null);
  }
  useEffect(() => {
    const keydown = (event: KeyboardEvent) => {
      if (event.isComposing || event.defaultPrevented || event.target instanceof HTMLElement && event.target.closest('input, textarea, select, button, a, [contenteditable]')) return;
      if (preview || !visible || error) return;
      if (event.key === 'Escape') { event.preventDefault(); select(null); return; }
      if (!selected || !selectedEditable) return;
      if (event.key === 'Delete' && boot.canDelete) { event.preventDefault(); remove(); }
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'd') { event.preventDefault(); duplicate(); }
      if (event.ctrlKey || event.metaKey || event.altKey) return;
      const step = event.shiftKey ? 10000 : 1000;
      const moves: Record<string, [number, number]> = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
      if (moves[event.key]) { event.preventDefault(); const [x, y] = moves[event.key]; change(selected.key, { x_unit: selected.x_unit + x, y_unit: selected.y_unit + y }); }
    };
    window.addEventListener('keydown', keydown);
    return () => window.removeEventListener('keydown', keydown);
  });
  const pageIndex = pages.findIndex(item => item.id === pageId);
  return <div ref={root} className={`mol-editor${preview ? ' mol-editor-preview' : ''}${panel ? ' mol-editor-open-' + panel : ''}`} dir="rtl">
    <header className="mol-editor-header">
      <a href={boot.backUrl}>{boot.backLabel}</a>
      <div className="mol-editor-title"><p dir="auto">{boot.workTitle}</p><h1>{chapter ? `الفصل ${chapter.chapter_label}${chapter.title ? ' · ' + chapter.title : ''}` : 'محرر الترجمة'}</h1></div>
      <button onClick={() => { setPreview(value => !value); setPanel(null); }} aria-pressed={preview} disabled={!page || !!error}>{preview ? 'إغلاق المعاينة' : 'معاينة'}</button>
      <p className="mol-editor-save-state" role="status">{blocked ? 'توقفت الجلسة — حدّث الصفحة' : !boot.canEdit ? 'عرض فقط — ليست لديك صلاحية تعديل الترجمة' : SAVE_LABELS[session.state]}</p>
    </header>
    {!error && [...session.records.values()].some(record => record.error) && <nav className="mol-editor-recovery" aria-label="عناصر تحتاج إلى مراجعة">
      {[...session.records.values()].filter(record => record.error && !record.deleting).map(record => <button key={record.value.key} onClick={() => { setLocalSelection({ pageId: record.pageId, key: record.value.key }); navigate({ pageId: record.pageId, elementId: record.value.source?.id ?? null }); }}>مراجعة العنصر: {record.value.content.slice(0, 30) || 'بلا نص'}</button>)}
    </nav>}
    {!error && selectedRecord && ['locked', 'conflict', 'error', 'offline'].includes(selectedRecord.state) && <section className="mol-editor-recovery" role="alert">
      <p>{selectedRecord.error?.message || SAVE_LABELS[selectedRecord.state]}</p>
      {selectedRecord.state === 'conflict' && selectedRecord.current !== undefined ? <>
        <div className="mol-editor-comparison"><div><h2>نسختك</h2><p dir="auto">{selectedRecord.value.content}</p><details><summary>الموضع والنمط</summary><pre>{JSON.stringify({ x: selectedRecord.value.x_unit, y: selectedRecord.value.y_unit, width: selectedRecord.value.w_unit, height: selectedRecord.value.h_unit, rotation: selectedRecord.value.rotation_mdeg, style: selectedRecord.value.style }, null, 2)}</pre></details></div>
          <div><h2>النسخة الحالية</h2><p dir="auto">{selectedRecord.current?.content ?? 'حُذف العنصر'}</p><details><summary>الموضع والنمط</summary><pre>{JSON.stringify(selectedRecord.current, null, 2)}</pre></details></div></div>
        <button onClick={() => session.resolve(selectedRecord.value.key, false)}>استخدام الحالية</button>
        {selectedRecord.current && <button onClick={() => session.resolve(selectedRecord.value.key, true)}>إعادة تطبيق تغييري على الحالية ثم الحفظ</button>}
      </> : <button onClick={() => void session.retry(selectedRecord.value.key)}>إعادة المحاولة</button>}
    </section>}
    {!error && session.dirty && !selectedRecord?.error && ['error', 'offline', 'locked'].includes(session.state) && <button className="mol-editor-recovery" onClick={() => { for (const record of session.records.values()) if (record.dirty) void session.retry(record.value.key); }}>إعادة محاولة حفظ التغييرات</button>}
    <nav className="mol-editor-controls" aria-label="أدوات مساحة الترجمة">
      {!preview && <div className="mol-editor-page-controls">
        <button aria-label="الصفحة السابقة" disabled={pageIndex <= 0 || blocked} onClick={() => navigate({ pageId: pages[pageIndex - 1].id, elementId: null })}>السابق</button>
        <label htmlFor="mol-editor-page">الصفحة</label><select id="mol-editor-page" value={pageId ?? ''} disabled={!pages.length || blocked} onChange={event => navigate({ pageId: Number(event.target.value), elementId: null })}>
          {!pages.length && <option value="">—</option>}{pages.map(item => <option key={item.id} value={item.id}>{item.page_index + 1} من {pages.length}</option>)}
        </select>
        <button aria-label="الصفحة التالية" disabled={pageIndex < 0 || pageIndex >= pages.length - 1 || blocked} onClick={() => navigate({ pageId: pages[pageIndex + 1].id, elementId: null })}>التالي</button>
      </div>}
      <button onClick={() => setVisible(value => !value)} aria-pressed={visible} disabled={!page || !!error}>الترجمة العربية</button>
      {!preview && <button aria-pressed={snapping} title="اضغط Alt لتعطيل الالتقاط مؤقتًا أثناء السحب" onClick={() => setSnapping(value => !value)}>التقاط المحاذاة</button>}
      {!preview && <div className="mol-editor-zoom"><button aria-label="تصغير الصفحة" disabled={!page || zoom <= .5} onClick={() => setZoom(value => Math.max(.5, value - .25))}>−</button><button aria-label="ملاءمة عرض الصفحة" disabled={!page} onClick={() => setZoom(1)}>{Math.round(zoom * 100)}%</button><button aria-label="تكبير الصفحة" disabled={!page || zoom >= 3} onClick={() => setZoom(value => Math.min(3, value + .25))}>+</button></div>}
    </nav>
    {chapterLoad.status === 'loading' ? <Message text="جارٍ تحميل الفصل وصفحاته…" />
      : error && (chapterLoad.status === 'error' || blocked) ? <ErrorMessage error={error} retry={() => setAttempt(value => value + 1)} />
        : !page ? <Message text="لم تُرفع صفحات لهذا الفصل بعد." />
          : <main className="mol-editor-workspace" aria-label="محرر الترجمة">
            {!preview && <Properties key={selected?.key ?? 'empty'} element={selected} editable={selectedEditable} canDelete={selectedEditable && boot.canDelete} textRef={textRef}
              presets={selected && workId !== null ? <Presets api={api} boot={boot} workId={workId} element={selected} editable={selectedEditable} presets={presets} loading={presetLoad.status === 'loading'} loadError={presetLoad.status === 'error' ? presetLoad.error.message : undefined} reload={() => setPresetAttempt(value => value + 1)} onChange={patch => change(selected.key, patch)} /> : undefined}
              onFreezeFit={() => { if (!selected) return; const node = document.querySelector<HTMLElement>(`[data-element-key="${CSS.escape(selected.key)}"] .mol-element-text`); const unit = Number(node?.dataset.fittedFontUnit); if (Number.isFinite(unit) && unit > 0) change(selected.key, { style: { ...selected.style, autoFit: false, fontSizeUnit: Math.max(1000, Math.min(200000, Math.round(unit))) } }); }}
              onChange={patch => { if (selected) change(selected.key, patch); }} onDuplicate={duplicate} onDelete={remove}
              onClose={() => { setPanel(null); propertiesButton.current?.focus(); }} />}
            <section className="mol-editor-page-area" aria-label="الصفحة الحالية" aria-busy={elementLoad.status === 'loading'}>
              {elementLoad.status === 'loading' ? <Message text="جارٍ تحميل طبقات الصفحة…" /> : elementLoad.status === 'error' ? <ErrorMessage error={elementLoad.error} retry={() => setPageAttempt(value => value + 1)} />
                : <Stage key={page.id} page={page} elements={elements} selectedKey={selected?.key ?? null} preview={preview} visible={visible} zoom={zoom} onZoom={setZoom} snapping={snapping} canEdit={selectedEditable}
                  onSelect={key => select(key, false)} onEditText={key => { select(key); if (selectedEditable) focusText(); }} onTransform={(key, geometry) => change(key, geometry, true)} />}
            </section>
            {!preview && <aside className="mol-editor-layers" aria-label="طبقات الصفحة">
              <details open><summary>الطبقات <span>{elements.length}</span></summary>
                {elementLoad.status === 'ready' && !elements.length ? <p className="mol-editor-muted">لا توجد عناصر ترجمة في هذه الصفحة.</p> : <ol>{workingLayers(elements).map(element => <li key={element.key}>
                  <button aria-pressed={selected?.key === element.key} onClick={() => select(element.key)}><strong>{ELEMENT_LABELS[element.element_type]}{!element.source ? ' · جديد' : ''}</strong><span>{element.content || 'عنصر بلا نص'}</span></button>
                </li>)}</ol>}
              </details><button className="mol-editor-mobile" onClick={() => setPanel(null)}>إغلاق الطبقات</button>
            </aside>}
          </main>}
    {!preview && !selected && presetLoad.status === 'error' && <div role="alert" className="mol-editor-message"><p>{presetLoad.error.message}</p><button onClick={() => setPresetAttempt(value => value + 1)}>إعادة تحميل الأنماط</button></div>}
    {!preview && <footer className="mol-editor-bottom">
      <span id="mol-editor-touch-help" className="mol-editor-touch-help">إصبعان للتكبير؛ اسحب المساحة الفارغة لتحريك الصفحة.</span>
      {boot.canEdit && <nav className="mol-editor-tools" aria-label="إضافة عناصر الترجمة"><button disabled={!editing} onClick={() => select(null)}>تحديد</button>{types.map(type => <button key={type} disabled={!editing || presetLoad.status !== 'ready'} onClick={() => add(type)} aria-label={'إضافة ' + ELEMENT_LABELS[type]}>{ELEMENT_LABELS[type]}</button>)}</nav>}
      <div className="mol-editor-panel-buttons"><button className="mol-editor-mobile" disabled={!!error} aria-expanded={panel === 'layers'} onClick={() => setPanel(value => value === 'layers' ? null : 'layers')}>الطبقات</button><button ref={propertiesButton} className="mol-editor-mobile" disabled={!selected} aria-expanded={panel === 'properties'} onClick={() => setPanel(value => value === 'properties' ? null : 'properties')}>الخصائص</button>
        {deleted?.deleting && deleted.state !== 'removed' && !deleted.busy && !error && <button disabled={!editing} onClick={undoDelete}>تراجع عن حذف العنصر</button>}</div>
    </footer>}
  </div>;
}
function Message({ text }: { text: string }) { return <div className="mol-editor-message" role="status"><p>{text}</p></div>; }
function ErrorMessage({ error, retry }: { error: EditorError; retry: () => void }) {
  const session = error.status === 401 || error.status === 403;
  return <div className="mol-editor-message" role="alert"><p>{error.message}</p>{session ? <a href={location.href}>تحديث الجلسة</a> : <button onClick={retry}>إعادة المحاولة</button>}</div>;
}
