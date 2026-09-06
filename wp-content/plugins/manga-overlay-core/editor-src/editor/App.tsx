import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { ELEMENT_LABELS } from '../domain/baseStyles';
import type { ElementType } from '../domain/types';
import { EditorApi, EditorError } from './api';
import { activePage, parseRoute, routeHash, type Bootstrap, type EditorRoute } from './state';
import { changeElement, createElement, duplicateElement, workingCopy, workingLayers, type Drafts, type ElementChange, type PageDraft } from './drafts';
import { useResource } from './useResource';
import { Stage } from './Stage';
import { Properties } from './Properties';

const types: ElementType[] = ['bubble', 'narration', 'free_text', 'sfx'];
export function App({ boot }: { boot: Bootstrap }) {
  const api = useMemo(() => new EditorApi(boot), [boot]);
  const [route, setRoute] = useState(() => parseRoute(location.hash));
  const [attempt, setAttempt] = useState(0);
  const [pageAttempt, setPageAttempt] = useState(0);
  const [preview, setPreview] = useState(false);
  const [visible, setVisible] = useState(true);
  const [zoom, setZoom] = useState(1);
  const [panel, setPanel] = useState<'layers' | 'properties' | null>(null);
  const [drafts, setDrafts] = useState<Drafts>({});
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
  const pageDraft = pageId !== null ? drafts[pageId] : undefined;
  // Never display a cached draft over a loading/error response, especially after access is lost.
  const elements = elementLoad.status === 'ready' ? pageDraft?.elements ?? sourceElements : [];
  const selectedKey = localSelection?.pageId === pageId ? localSelection.key : route.elementId === null ? null : String(route.elementId);
  const selected = elements.find(element => element.key === selectedKey);
  const chapter = chapterLoad.status === 'ready' ? chapterLoad.data.chapter : null;
  const error = chapterLoad.status === 'error' ? chapterLoad.error : elementLoad.status === 'error' ? elementLoad.error : null;
  const blocked = error?.status === 401 || error?.status === 403;
  const canEdit = boot.canEdit === true && !!page && elementLoad.status === 'ready' && !error;
  const editing = canEdit && !preview && visible;
  const dirty = Object.keys(drafts).length > 0;

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
  useEffect(() => { setZoom(1); setPanel(null); setLocalSelection(null); }, [pageId]);
  useEffect(() => { if (blocked) { setDrafts({}); setLocalSelection(null); setPanel(null); } }, [blocked]);
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
    setLocalSelection(key !== null && !element?.source && pageId !== null ? { pageId, key } : null);
    navigate({ pageId, elementId: element?.source?.id ?? null });
    if (key !== null && open) setPanel('properties');
    else if (key === null) setPanel(null);
  }
  function update(action: (draft: PageDraft) => PageDraft) {
    if (!editing || pageId === null) return;
    setDrafts(previous => ({ ...previous, [pageId]: action(previous[pageId] ?? { elements: sourceElements, deleted: null }) }));
  }
  function change(key: string, patch: ElementChange) {
    update(draft => ({ ...draft, elements: draft.elements.map(element => element.key === key ? changeElement(element, patch) : element) }));
  }
  function focusText() { window.setTimeout(() => textRef.current?.focus(), 0); }
  function add(type: ElementType) {
    if (!editing || pageId === null) return;
    const element = createElement(type, elements, 'draft:' + crypto.randomUUID());
    update(draft => ({ ...draft, elements: [...draft.elements, element] }));
    setLocalSelection({ pageId, key: element.key }); navigate({ pageId, elementId: null }); setPanel('properties'); focusText();
  }
  function duplicate() {
    if (!editing || !selected || pageId === null) return;
    const copy = duplicateElement(selected, elements, 'draft:' + crypto.randomUUID());
    update(draft => ({ ...draft, elements: [...draft.elements, copy] }));
    setLocalSelection({ pageId, key: copy.key }); navigate({ pageId, elementId: null }); setPanel('properties');
  }
  function remove() {
    if (!editing || !selected || !boot.canDelete) return;
    update(draft => ({ elements: draft.elements.filter(element => element.key !== selected.key), deleted: selected }));
    select(null);
  }
  function undoDelete() {
    if (!pageDraft?.deleted || !editing || pageId === null) return;
    const element = pageDraft.deleted;
    update(draft => ({ elements: [...draft.elements, element], deleted: null }));
    setLocalSelection(element.source ? null : { pageId, key: element.key });
    navigate({ pageId, elementId: element.source?.id ?? null }); setPanel('properties');
  }
  useEffect(() => {
    const keydown = (event: KeyboardEvent) => {
      if (event.isComposing || event.defaultPrevented || event.target instanceof HTMLElement && event.target.closest('input, textarea, select, button, a, [contenteditable]')) return;
      if (preview || !visible || error) return;
      if (event.key === 'Escape') { event.preventDefault(); select(null); return; }
      if (!selected || !editing) return;
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
  return <div className={`mol-editor${preview ? ' mol-editor-preview' : ''}${panel ? ' mol-editor-open-' + panel : ''}`} dir="rtl">
    <header className="mol-editor-header">
      <a href={boot.backUrl}>{boot.backLabel}</a>
      <div className="mol-editor-title"><p dir="auto">{boot.workTitle}</p><h1>{chapter ? `الفصل ${chapter.chapter_label}${chapter.title ? ' · ' + chapter.title : ''}` : 'محرر الترجمة'}</h1></div>
      <button onClick={() => { setPreview(value => !value); setPanel(null); }} aria-pressed={preview} disabled={!page || !!error}>{preview ? 'إغلاق المعاينة' : 'معاينة'}</button>
      <p className="mol-editor-save-state" role="status">{blocked ? 'توقفت الجلسة — حدّث الصفحة' : !boot.canEdit ? 'عرض فقط — ليست لديك صلاحية تعديل الترجمة' : dirty ? 'غير محفوظ — تغييرات هذه الجلسة لم تُرسل وتُفقد عند المغادرة' : 'تحرير تجريبي — الحفظ والنشر غير متاحين بعد'}</p>
    </header>
    <nav className="mol-editor-controls" aria-label="أدوات مساحة الترجمة">
      {!preview && <div className="mol-editor-page-controls">
        <button aria-label="الصفحة السابقة" disabled={pageIndex <= 0 || blocked} onClick={() => navigate({ pageId: pages[pageIndex - 1].id, elementId: null })}>السابق</button>
        <label htmlFor="mol-editor-page">الصفحة</label><select id="mol-editor-page" value={pageId ?? ''} disabled={!pages.length || blocked} onChange={event => navigate({ pageId: Number(event.target.value), elementId: null })}>
          {!pages.length && <option value="">—</option>}{pages.map(item => <option key={item.id} value={item.id}>{item.page_index + 1} من {pages.length}</option>)}
        </select>
        <button aria-label="الصفحة التالية" disabled={pageIndex < 0 || pageIndex >= pages.length - 1 || blocked} onClick={() => navigate({ pageId: pages[pageIndex + 1].id, elementId: null })}>التالي</button>
      </div>}
      <button onClick={() => setVisible(value => !value)} aria-pressed={visible} disabled={!page || !!error}>الترجمة العربية</button>
      {!preview && <div className="mol-editor-zoom"><button aria-label="تصغير الصفحة" disabled={!page || zoom <= .5} onClick={() => setZoom(value => Math.max(.5, value - .25))}>−</button><button aria-label="ملاءمة عرض الصفحة" disabled={!page} onClick={() => setZoom(1)}>{Math.round(zoom * 100)}%</button><button aria-label="تكبير الصفحة" disabled={!page || zoom >= 3} onClick={() => setZoom(value => Math.min(3, value + .25))}>+</button></div>}
    </nav>
    {chapterLoad.status === 'loading' ? <Message text="جارٍ تحميل الفصل وصفحاته…" />
      : error && (chapterLoad.status === 'error' || blocked) ? <ErrorMessage error={error} retry={() => setAttempt(value => value + 1)} />
        : !page ? <Message text="لم تُرفع صفحات لهذا الفصل بعد." />
          : <main className="mol-editor-workspace" aria-label="محرر الترجمة">
            {!preview && <Properties key={selected?.key ?? 'empty'} element={selected} editable={editing} canDelete={editing && boot.canDelete} textRef={textRef}
              onChange={patch => { if (selected) change(selected.key, patch); }} onDuplicate={duplicate} onDelete={remove}
              onClose={() => { setPanel(null); propertiesButton.current?.focus(); }} />}
            <section className="mol-editor-page-area" aria-label="الصفحة الحالية" aria-busy={elementLoad.status === 'loading'}>
              {elementLoad.status === 'loading' ? <Message text="جارٍ تحميل طبقات الصفحة…" /> : elementLoad.status === 'error' ? <ErrorMessage error={elementLoad.error} retry={() => setPageAttempt(value => value + 1)} />
                : <Stage key={page.id} page={page} elements={elements} selectedKey={selected?.key ?? null} preview={preview} visible={visible} zoom={zoom} canEdit={editing}
                  onSelect={key => select(key, false)} onEditText={key => { select(key); if (editing) focusText(); }} onTransform={change} />}
            </section>
            {!preview && <aside className="mol-editor-layers" aria-label="طبقات الصفحة">
              <details open><summary>الطبقات <span>{elements.length}</span></summary>
                {elementLoad.status === 'ready' && !elements.length ? <p className="mol-editor-muted">لا توجد عناصر ترجمة في هذه الصفحة.</p> : <ol>{workingLayers(elements).map(element => <li key={element.key}>
                  <button aria-pressed={selected?.key === element.key} onClick={() => select(element.key)}><strong>{ELEMENT_LABELS[element.element_type]}{!element.source ? ' · جديد' : ''}</strong><span>{element.content || 'عنصر بلا نص'}</span></button>
                </li>)}</ol>}
              </details><button className="mol-editor-mobile" onClick={() => setPanel(null)}>إغلاق الطبقات</button>
            </aside>}
          </main>}
    {!preview && <footer className="mol-editor-bottom">
      {boot.canEdit && <nav className="mol-editor-tools" aria-label="إضافة عناصر الترجمة"><button disabled={!editing} onClick={() => select(null)}>تحديد</button>{types.map(type => <button key={type} disabled={!editing} onClick={() => add(type)} aria-label={'إضافة ' + ELEMENT_LABELS[type]}>{ELEMENT_LABELS[type]}</button>)}</nav>}
      <div className="mol-editor-panel-buttons"><button className="mol-editor-mobile" disabled={!!error} aria-expanded={panel === 'layers'} onClick={() => setPanel(value => value === 'layers' ? null : 'layers')}>الطبقات</button><button ref={propertiesButton} className="mol-editor-mobile" disabled={!selected} aria-expanded={panel === 'properties'} onClick={() => setPanel(value => value === 'properties' ? null : 'properties')}>الخصائص</button>
        {pageDraft?.deleted && !error && <button disabled={!editing} onClick={undoDelete}>تراجع عن حذف العنصر</button>}</div>
    </footer>}
  </div>;
}
function Message({ text }: { text: string }) { return <div className="mol-editor-message" role="status"><p>{text}</p></div>; }
function ErrorMessage({ error, retry }: { error: EditorError; retry: () => void }) {
  const session = error.status === 401 || error.status === 403;
  return <div className="mol-editor-message" role="alert"><p>{error.message}</p>{session ? <a href={location.href}>تحديث الجلسة</a> : <button onClick={retry}>إعادة المحاولة</button>}</div>;
}
