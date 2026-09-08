const boot = JSON.parse(document.getElementById('mol-reports-data').textContent);
const node = id => document.getElementById(id);
const list = node('mol-reports-list'), notice = node('mol-reports-notice'), filters = node('mol-report-filters');
const statuses = { open: 'مفتوح', in_review: 'قيد المراجعة', resolved: 'تم الحل', rejected: 'مرفوض' };
const types = { translation: 'خطأ ترجمة', placement: 'موضع الترجمة', style: 'المظهر والخط', missing: 'ترجمة ناقصة', other: 'مشكلة أخرى' };
let page = 1, pages = 0, loading = false, saving = false, blocked = false, generation = 0;
let readController;
const element = (tag, text) => { const item = document.createElement(tag); if (text !== undefined) item.textContent = text; return item; };
function controls() {
  for (const item of filters.elements) item.disabled = saving || blocked;
  node('mol-reports-previous').disabled = loading || saving || blocked || page <= 1;
  node('mol-reports-next').disabled = loading || saving || blocked || page >= pages;
  for (const item of list.querySelectorAll('button,select')) item.disabled = saving || loading || blocked;
}
async function api(path, options = {}) {
  const response = await fetch(boot.api + path, { credentials: 'same-origin', ...options, headers: { 'X-WP-Nonce': boot.nonce, 'Content-Type': 'application/json' } });
  const body = await response.json().catch(() => null);
  if (!response.ok) { const error = new Error(body?.message || 'تعذر إكمال العملية.'); error.status = response.status; throw error; }
  if (!body) throw new Error('Invalid response');
  return body;
}
async function load() {
  const sequence = ++generation;
  readController?.abort(); readController = new AbortController();
  loading = true; list.replaceChildren(); list.setAttribute('aria-busy', 'true'); notice.textContent = 'جارٍ تحميل البلاغات…'; node('mol-reports-retry').hidden = true; controls();
  const query = new URLSearchParams({ page: String(page), per_page: '20' });
  if (node('mol-report-status').value) query.set('status', node('mol-report-status').value);
  if (node('mol-report-chapter').value) query.set('chapter_id', node('mol-report-chapter').value);
  try {
    const result = await api('reports?' + query, { signal: readController.signal });
    if (sequence !== generation) return;
    pages = result.meta.total_pages;
    if (page > pages && page > 1) { page = Math.max(1, pages); void load(); return; }
    for (const report of result.data) {
      const article = element('article'); article.dataset.reportId = String(report.id);
      const heading = element('h2', `بلاغ #${report.id} · ${types[report.report_type]}`);
      const context = element('p', `الفصل #${report.chapter_id}${report.page_id ? ` · الصفحة #${report.page_id}` : ''}${report.element_id ? ` · العنصر #${report.element_id}` : ''}`);
      context.className = 'mol-report-context';
      const link = element('a', 'فتح موضع البلاغ'); link.href = boot.contextUrl + report.id; link.target = '_blank'; link.rel = 'noopener';
      const message = element('p', report.message); message.className = 'mol-report-message'; message.dir = 'auto';
      const details = element('p', `المبلّغ #${report.reporter_id} · ${new Date(report.created_at).toLocaleString('ar')}`);
      const status = element('p', 'الحالة الحالية: ' + statuses[report.status]); status.className = 'mol-report-current';
      article.append(heading, context, link, message, details, status);
      if (report.resolved_by) article.append(element('p', `أغلقه المشرف #${report.resolved_by} · ${new Date(report.resolved_at).toLocaleString('ar')}`));
      const select = element('select'); select.id = `mol-moderate-${report.id}`;
      for (const [value, label] of Object.entries(statuses)) { const option = element('option', label); option.value = value; select.append(option); }
      select.value = report.status;
      const label = element('label', 'الحالة الجديدة'); label.htmlFor = select.id;
      const save = element('button', 'حفظ حالة البلاغ'); save.type = 'button'; save.className = 'button';
      save.addEventListener('click', async () => {
        if (saving || blocked || select.value === report.status) return;
        saving = true; controls(); notice.textContent = 'جارٍ حفظ حالة البلاغ…';
        try { await api('reports/' + report.id, { method: 'PATCH', body: JSON.stringify({ status: select.value }) }); saving = false; if (await load()) notice.textContent = 'حُفظت حالة البلاغ.'; }
        catch (error) {
          blocked = error.status === 401 || error.status === 403;
          // A lost response may have committed: refresh the authoritative list before another write.
          list.replaceChildren(); notice.textContent = blocked ? 'انتهت الجلسة أو سُحبت صلاحية المراجعة. حدّث الصفحة.' : 'تعذر تأكيد الحفظ. أعد تحميل القائمة لمراجعة الحالة قبل المحاولة مجددًا.';
          node('mol-reports-retry').hidden = blocked;
        } finally { saving = false; controls(); }
      });
      const actions = element('div'); actions.className = 'mol-report-actions'; actions.append(label, select, save); article.append(actions); list.append(article);
    }
    notice.textContent = result.data.length ? '' : 'لا توجد بلاغات مطابقة.';
    node('mol-reports-count').textContent = pages ? `${page} / ${pages} · ${result.meta.total} بلاغ` : '0 بلاغ';
    return true;
  } catch (error) {
    if (sequence !== generation || error.name === 'AbortError') return;
    pages = 0; blocked = error.status === 401 || error.status === 403;
    notice.textContent = blocked ? 'انتهت الجلسة أو سُحبت صلاحية المراجعة. حدّث الصفحة.' : 'تعذر تحميل البلاغات.';
    node('mol-reports-count').textContent = ''; node('mol-reports-retry').hidden = blocked;
  } finally { if (sequence === generation) { loading = false; list.setAttribute('aria-busy', 'false'); controls(); } }
}
filters.addEventListener('submit', event => { event.preventDefault(); if (!saving && !blocked) { page = 1; void load(); } });
node('mol-reports-retry').addEventListener('click', () => void load());
node('mol-reports-previous').addEventListener('click', () => { if (page > 1) { --page; void load(); } });
node('mol-reports-next').addEventListener('click', () => { if (page < pages) { ++page; void load(); } });
void load();
