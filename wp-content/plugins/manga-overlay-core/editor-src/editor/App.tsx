import { useCallback, useEffect, useMemo, useState } from 'react';
import { ELEMENT_LABELS } from '../domain/baseStyles';
import { EditorApi, EditorError } from './api';
import { activePage, layers, parseRoute, routeHash, type Bootstrap, type EditorRoute } from './state';
import { useResource } from './useResource';
import { Stage } from './Stage';
import { Properties } from './Properties';

export function App({ boot }: { boot: Bootstrap }) {
  const api = useMemo(() => new EditorApi(boot), [boot]);
  const [route, setRoute] = useState(() => parseRoute(location.hash));
  const [attempt, setAttempt] = useState(0);
  const [pageAttempt, setPageAttempt] = useState(0);
  const [preview, setPreview] = useState(false);
  const [visible, setVisible] = useState(true);
  const [zoom, setZoom] = useState(1);
  const [panel, setPanel] = useState<'layers' | 'properties' | null>(null);
  const chapterLoad = useResource('chapter-' + attempt, useCallback((signal: AbortSignal) => api.chapter(signal), [api]));
  const pages = chapterLoad.status === 'ready' ? chapterLoad.data.pages : [];
  const page = activePage(pages, route.pageId);
  const pageId = page?.id ?? null;
  const elementLoad = useResource(pageId === null ? null : `${pageId}-${attempt}-${pageAttempt}`,
    useCallback((signal: AbortSignal) => api.elements(pageId!, signal), [api, pageId]));
  const elements = elementLoad.status === 'ready' ? elementLoad.data : [];
  const selected = elements.find(element => element.id === route.elementId);
  const chapter = chapterLoad.status === 'ready' ? chapterLoad.data.chapter : null;

  const navigate = useCallback((next: EditorRoute, replace = false) => {
    const hash = routeHash(next);
    if (location.hash !== hash) history[replace ? 'replaceState' : 'pushState'](null, '', location.pathname + location.search + hash);
    setRoute(next);
  }, []);
  useEffect(() => {
    const restore = () => { setRoute(parseRoute(location.hash)); setPanel(null); };
    window.addEventListener('popstate', restore); window.addEventListener('hashchange', restore);
    return () => { window.removeEventListener('popstate', restore); window.removeEventListener('hashchange', restore); };
  }, []);
  useEffect(() => { setZoom(1); setPanel(null); }, [pageId]);
  useEffect(() => {
    if (chapterLoad.status !== 'ready') return;
    if (pageId !== route.pageId) navigate({ pageId, elementId: null }, true);
    else if (elementLoad.status === 'ready' && route.elementId !== null && !selected) navigate({ pageId, elementId: null }, true);
  }, [chapterLoad.status, elementLoad.status, pageId, route.pageId, route.elementId, selected, navigate]);

  function select(id: number | null) {
    navigate({ pageId, elementId: id });
    if (id !== null) setPanel('properties');
  }
  const pageIndex = pages.findIndex(item => item.id === pageId);
  const error = chapterLoad.status === 'error' ? chapterLoad.error : elementLoad.status === 'error' ? elementLoad.error : null;
  const blocked = error?.status === 401 || error?.status === 403;
  return <div className={`mol-editor${preview ? ' mol-editor-preview' : ''}${panel ? ' mol-editor-open-' + panel : ''}`} dir="rtl">
    <header className="mol-editor-header">
      <a href={boot.backUrl}>{boot.backLabel}</a>
      <div className="mol-editor-title"><p dir="auto">{boot.workTitle}</p><h1>{chapter ? `الفصل ${chapter.chapter_label}${chapter.title ? ' · ' + chapter.title : ''}` : 'محرر الترجمة'}</h1></div>
      <button onClick={() => { setPreview(value => !value); setPanel(null); }} aria-pressed={preview} disabled={!page || !!error}>{preview ? 'إغلاق المعاينة' : 'معاينة'}</button>
      <p className="mol-editor-save-state" role="status">عرض فقط — التعديل والنشر غير متاحين بعد</p>
    </header>
    <nav className="mol-editor-controls" aria-label="أدوات مساحة الترجمة">
      {!preview && <div className="mol-editor-page-controls">
        <button aria-label="الصفحة السابقة" disabled={pageIndex <= 0 || blocked} onClick={() => navigate({ pageId: pages[pageIndex - 1].id, elementId: null })}>السابق</button>
        <label>الصفحة<select value={pageId ?? ''} disabled={!pages.length || blocked} onChange={event => navigate({ pageId: Number(event.target.value), elementId: null })}>
          {!pages.length && <option value="">—</option>}{pages.map(item => <option key={item.id} value={item.id}>{item.page_index + 1} من {pages.length}</option>)}
        </select></label>
        <button aria-label="الصفحة التالية" disabled={pageIndex < 0 || pageIndex >= pages.length - 1 || blocked} onClick={() => navigate({ pageId: pages[pageIndex + 1].id, elementId: null })}>التالي</button>
      </div>}
      <button onClick={() => setVisible(value => !value)} aria-pressed={visible} disabled={!page || !!error}>الترجمة العربية</button>
      {!preview && <div className="mol-editor-zoom"><button aria-label="تصغير الصفحة" disabled={!page || zoom <= .5} onClick={() => setZoom(value => Math.max(.5, value - .25))}>−</button><button aria-label="ملاءمة عرض الصفحة" disabled={!page} onClick={() => setZoom(1)}>{Math.round(zoom * 100)}%</button><button aria-label="تكبير الصفحة" disabled={!page || zoom >= 3} onClick={() => setZoom(value => Math.min(3, value + .25))}>+</button></div>}
    </nav>
    {chapterLoad.status === 'loading' ? <Message text="جارٍ تحميل الفصل وصفحاته…" />
      : error && (chapterLoad.status === 'error' || blocked) ? <ErrorMessage error={error} retry={() => setAttempt(value => value + 1)} />
        : !page ? <Message text="لم تُرفع صفحات لهذا الفصل بعد." />
          : <main className="mol-editor-workspace" aria-label="محرر الترجمة">
            {!preview && <Properties element={selected} onClose={() => setPanel(null)} />}
            <section className="mol-editor-page-area" aria-label="الصفحة الحالية" aria-busy={elementLoad.status === 'loading'}>
              {elementLoad.status === 'loading' ? <Message text="جارٍ تحميل طبقات الصفحة…" /> : elementLoad.status === 'error' ? <ErrorMessage error={elementLoad.error} retry={() => setPageAttempt(value => value + 1)} />
                : <Stage key={page.id} page={page} elements={elements} selectedId={selected?.id ?? null} preview={preview} visible={visible} zoom={zoom} onSelect={select} />}
            </section>
            {!preview && <aside className="mol-editor-layers" aria-label="طبقات الصفحة">
              <details open><summary>الطبقات <span>{elements.length}</span></summary>
                {elementLoad.status === 'ready' && !elements.length ? <p className="mol-editor-muted">لا توجد عناصر ترجمة في هذه الصفحة.</p> : <ol>{layers(elements).map(element => <li key={element.id}>
                  <button aria-pressed={selected?.id === element.id} onClick={() => select(element.id)}><strong>{ELEMENT_LABELS[element.element_type]}</strong><span>{element.content || 'عنصر بلا نص'}</span></button>
                </li>)}</ol>}
              </details><button className="mol-editor-mobile" onClick={() => setPanel(null)}>إغلاق الطبقات</button>
            </aside>}
          </main>}
    {!preview && <footer className="mol-editor-bottom"><span>العناصر فوق الصورة الأصلية</span><button className="mol-editor-mobile" aria-expanded={panel === 'layers'} onClick={() => setPanel(value => value === 'layers' ? null : 'layers')}>الطبقات</button><button className="mol-editor-mobile" disabled={!selected} aria-expanded={panel === 'properties'} onClick={() => setPanel(value => value === 'properties' ? null : 'properties')}>الخصائص</button></footer>}
  </div>;
}

function Message({ text }: { text: string }) { return <div className="mol-editor-message" role="status"><p>{text}</p></div>; }
function ErrorMessage({ error, retry }: { error: EditorError; retry: () => void }) {
  const session = error.status === 401 || error.status === 403;
  return <div className="mol-editor-message" role="alert"><p>{error.message}</p>{session ? <a href={location.href}>تحديث الجلسة</a> : <button onClick={retry}>إعادة المحاولة</button>}</div>;
}
