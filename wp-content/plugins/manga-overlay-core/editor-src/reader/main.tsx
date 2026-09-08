import { createRoot, type Root } from 'react-dom/client';
import '@fontsource-variable/cairo';
import '@fontsource-variable/noto-sans-arabic';
import '@fontsource-variable/noto-kufi-arabic';
import '@fontsource/tajawal/400.css';
import '@fontsource/tajawal/500.css';
import '@fontsource/tajawal/700.css';
import '@fontsource/tajawal/800.css';
import '@fontsource/tajawal/900.css';
import { OverlayElement } from '../renderer/OverlayElement';
import type { components } from '../generated/api';
import { pageDelta, readerMode, readLocal, validProgress, writeLocal, type Progress, type ProgressUpdate, type ReaderPreferences } from './state';
import './reader.css';
import { ReportForm } from './ReportForm';

interface Bootstrap {
  chapter: components['schemas']['Chapter'];
  work: components['schemas']['WorkDetail'];
  pages: components['schemas']['Page'][];
  overlays: components['schemas']['ChapterElementsResponse']['data'];
  progress: Progress | null;
  api: string;
  nonce: string | null;
  canReport: boolean;
  loginUrl: string;
}
const data = document.getElementById('mol-reader-data');
if (data?.textContent) start(JSON.parse(data.textContent) as Bootstrap);

function start(boot: Bootstrap) {
  const node = <T extends HTMLElement>(id: string) => {
    const element = document.getElementById(id);
    if (!element) throw new Error(`Missing reader control: ${id}`);
    return element as T;
  };
  const viewport = node('mol-reader-viewport');
  const track = node('mol-reader-track');
  const toolbar = node('mol-reader-toolbar');
  const reveal = node<HTMLButtonElement>('mol-show-toolbar');
  const modeSelect = node<HTMLSelectElement>('mol-reader-mode');
  const pageSelect = node<HTMLSelectElement>('mol-reader-page-select');
  const toggle = node<HTMLButtonElement>('mol-reader-toggle');
  const previous = node<HTMLButtonElement>('mol-page-previous');
  const next = node<HTMLButtonElement>('mol-page-next');
  const zoomIn = node<HTMLButtonElement>('mol-zoom-in');
  const zoomOut = node<HTMLButtonElement>('mol-zoom-out');
  const zoomReset = node<HTMLButtonElement>('mol-zoom-reset');
  const status = node('mol-reader-status');
  const pages = Array.from(track.querySelectorAll<HTMLElement>('.mol-reader-page'));
  const roots = new Map<HTMLElement, Root>();
  const preferencesKey = `mol_reader_${boot.work.id}`;
  const rawPreferences = readLocal(preferencesKey);
  const preferences: ReaderPreferences = rawPreferences && typeof rawPreferences === 'object' ? rawPreferences : {};
  const progressKey = `mol_progress_${boot.chapter.id}`;
  // Signed-in progress is supplied only for the current account. Guest device progress is separate.
  const saved = validProgress(boot.nonce ? boot.progress : readLocal(progressKey), boot.chapter.id, pages.length);
  let mode = readerMode(preferences.mode ?? saved?.reader_mode, boot.chapter.reader_mode_override, boot.work.default_reader_mode);
  const direction = boot.chapter.direction_override ?? boot.work.reading_direction;
  let current = saved?.page_index ?? 0;
  const linkedPage = /^#mol-page-([1-9][0-9]*)$/.exec(location.hash);
  const linkedIndex = linkedPage ? boot.pages.findIndex(page => page.id === Number(linkedPage[1])) : -1;
  if (linkedIndex >= 0) current = linkedIndex;
  let translation = typeof preferences.translation === 'boolean' ? preferences.translation : boot.overlays.some(p => p.elements.length > 0);
  let zoom = 1;
  let interacted = false;
  let scrollTimer = 0;
  let saveTimer = 0;
  let pending: ProgressUpdate | null = null;
  let sending = false;
  let lastSent = '';
  const pageElements = new Map(boot.overlays.map(page => [page.page_id, page.elements]));
  const hasTranslation = boot.overlays.some(page => page.elements.length > 0);

  const renderOverlay = (page: HTMLElement) => {
    const host = page.querySelector<HTMLElement>('.mol-reader-overlay')!;
    host.hidden = !translation;
    if (!translation || page.hidden || !page.clientWidth) return;
    let root = roots.get(page);
    if (!root) { root = createRoot(host); roots.set(page, root); }
    const entry = boot.pages.find(item => item.id === Number(page.dataset.pageId));
    if (!entry) return;
    const size = { width: page.clientWidth, height: page.clientWidth * entry.natural_height / entry.natural_width };
    root.render(<>{(pageElements.get(entry.id) ?? []).map(element => <OverlayElement key={element.id} element={{ ...element, rotation_mdeg: element.rotation_mdeg ?? 0, z_index: element.z_index ?? 0, key: String(element.id) }} size={size} />)}</>);
  };
  const observer = new ResizeObserver(entries => entries.forEach(entry => renderOverlay(entry.target as HTMLElement)));
  pages.forEach(page => observer.observe(page));

  function rememberPreferences() { writeLocal(preferencesKey, { mode, translation }); }
  function updateZoom(value: number) {
    zoom = Math.min(3, Math.max(1, value));
    track.style.width = `${Math.min(viewport.clientWidth, 860) * zoom}px`;
    zoomReset.textContent = `${Math.round(zoom * 100)}%`;
    zoomOut.disabled = zoom <= 1;
    zoomIn.disabled = !pages.length || zoom >= 3;
    zoomReset.disabled = pages.length === 0;
  }
  new ResizeObserver(() => updateZoom(zoom)).observe(viewport);

  function display() {
    viewport.dataset.mode = mode;
    node('mol-page-navigation').hidden = mode !== 'paged' || !pages.length;
    pages.forEach((page, index) => {
      page.hidden = mode === 'paged' && index !== current;
      // Keep the original image nodes throughout toggle/mode changes.
      const image = page.querySelector('img')!;
      image.loading = index === current ? 'eager' : 'lazy';
      renderOverlay(page);
    });
    modeSelect.value = mode;
    modeSelect.disabled = !pages.length;
    pageSelect.value = String(current);
    previous.disabled = current === 0;
    next.disabled = current >= pages.length - 1;
    node('mol-page-navigation').style.direction = direction;
    toggle.setAttribute('aria-pressed', String(translation));
    toggle.disabled = !hasTranslation;
    toggle.textContent = hasTranslation ? 'الترجمة العربية' : 'لا توجد ترجمة بعد';
  }
  function positionProgress(): ProgressUpdate {
    const bounds = pages[current]?.getBoundingClientRect();
    const unit = mode === 'webtoon' && bounds ? Math.round(Math.max(0, Math.min(1, (100 - bounds.top) / bounds.height)) * 1_000_000) : 0;
    return { chapter_id: boot.chapter.id, page_index: current, progress_unit: unit, reader_mode: mode };
  }
  async function flush(keepalive = false) {
    if (!pending || sending || !boot.nonce) return;
    const outgoing = pending;
    pending = null;
    const signature = JSON.stringify(outgoing);
    if (signature === lastSent) return;
    sending = true;
    try {
      const response = await fetch(boot.api.replace(/\/$/, '') + '/reading-progress', {
        method: 'PUT', credentials: 'same-origin', keepalive,
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': boot.nonce }, body: signature,
      });
      if (!response.ok) throw new Error('progress');
      const result = await response.json() as components['schemas']['ReadingProgressResponse'];
      if (!validProgress(result.data, boot.chapter.id, pages.length)) throw new Error('progress');
      lastSent = signature;
      status.textContent = pending && JSON.stringify(pending) !== signature ? 'جارٍ حفظ موضع القراءة…' : 'تم حفظ موضع القراءة';
    } catch {
      status.textContent = 'تعذر حفظ موضع القراءة في حسابك. ستُعاد المحاولة عند متابعة القراءة.';
      pending ??= outgoing;
    } finally {
      sending = false;
      if (pending && JSON.stringify(pending) !== signature) {
        window.clearTimeout(saveTimer);
        saveTimer = window.setTimeout(() => { saveTimer = 0; void flush(); }, 1500);
      }
    }
  }
  function save() {
    if (!pages.length || !interacted) return;
    const progress = positionProgress();
    if (boot.nonce) {
      pending = progress;
      if (JSON.stringify(progress) !== lastSent) status.textContent = 'جارٍ حفظ موضع القراءة…';
      if (!saveTimer) saveTimer = window.setTimeout(() => { saveTimer = 0; void flush(); }, 1500);
    } else {
      const ok = writeLocal(progressKey, { ...progress, updated_at: new Date().toISOString() });
      status.textContent = ok ? 'تم حفظ موضع القراءة على هذا الجهاز' : 'حفظ التقدم غير متاح في هذا المتصفح';
    }
  }
  function go(index: number) {
    if (!pages.length) return;
    current = Math.max(0, Math.min(pages.length - 1, index));
    interacted = true;
    updateZoom(1);
    viewport.scrollLeft = 0;
    display();
    if (mode === 'webtoon') pages[current]?.scrollIntoView({ block: 'start' });
    save();
  }
  previous.addEventListener('click', () => go(current - 1));
  next.addEventListener('click', () => go(current + 1));
  pageSelect.addEventListener('change', () => go(Number(pageSelect.value)));
  modeSelect.addEventListener('change', () => {
    window.clearTimeout(scrollTimer);
    scrollTimer = 0;
    mode = modeSelect.value === 'paged' ? 'paged' : 'webtoon';
    rememberPreferences();
    go(current);
  });
  toggle.addEventListener('click', () => { translation = !translation; rememberPreferences(); display(); });
  node<HTMLSelectElement>('mol-reader-chapter').addEventListener('change', event => {
    const url = new URL((event.target as HTMLSelectElement).value, location.href);
    if (url.origin === location.origin) location.assign(url.href);
  });
  node('mol-hide-toolbar').addEventListener('click', () => { toolbar.hidden = true; reveal.hidden = false; reveal.focus(); });
  reveal.addEventListener('click', () => { toolbar.hidden = false; reveal.hidden = true; modeSelect.focus(); });
  zoomIn.addEventListener('click', () => updateZoom(zoom + .25));
  zoomOut.addEventListener('click', () => updateZoom(zoom - .25));
  zoomReset.addEventListener('click', () => updateZoom(1));
  window.addEventListener('keydown', event => {
    if (mode !== 'paged' || event.altKey || event.ctrlKey || event.metaKey || (event.target instanceof HTMLElement && event.target.closest('input,select,textarea,button,a,[contenteditable="true"]'))) return;
    const delta = pageDelta(event.key, direction);
    if (delta) { event.preventDefault(); go(current + delta); }
  });
  window.addEventListener('scroll', () => {
    if (mode !== 'webtoon' || scrollTimer || !pages.length) return;
    scrollTimer = window.setTimeout(() => {
      scrollTimer = 0;
      // A scroll queued in webtoon mode must not overwrite a later paged selection.
      if (mode !== 'webtoon') return;
      const marker = Math.min(window.innerHeight * .3, 250);
      const visible = pages.findIndex(page => page.getBoundingClientRect().bottom > marker);
      current = visible < 0 ? pages.length - 1 : visible;
      pageSelect.value = String(current);
      save();
    }, 400);
  }, { passive: true });
  for (const event of ['wheel', 'touchstart', 'pointerdown', 'keydown']) window.addEventListener(event, () => { interacted = true; }, { passive: true });
  window.addEventListener('pagehide', () => { save(); void flush(true); });
  document.addEventListener('visibilitychange', () => { if (document.hidden) { save(); void flush(true); } });
  display();
  updateZoom(1);
  const reportHost = document.getElementById('mol-report-root');
  if (reportHost) createRoot(reportHost).render(<ReportForm chapterId={boot.chapter.id} pages={boot.pages} overlays={boot.overlays} api={boot.api} nonce={boot.nonce} canReport={boot.canReport} loginUrl={boot.loginUrl} currentPage={() => boot.pages[current]?.id} />);
  if ((saved || linkedIndex >= 0) && mode === 'webtoon') requestAnimationFrame(() => {
    const page = pages[current];
    window.scrollTo({ top: page.getBoundingClientRect().top + window.scrollY + page.clientHeight * (linkedIndex >= 0 ? 0 : saved?.progress_unit ?? 0) / 1_000_000 - 100 });
  });
}
