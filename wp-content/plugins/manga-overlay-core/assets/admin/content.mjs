import { UploadQueue } from './upload-queue.mjs';

const boot = document.getElementById('mol-content-data');
if (boot) {
  const config = JSON.parse(boot.textContent);
  const $ = id => document.getElementById(id);
  const form = $('mol-chapter-form');
  const chapterSelect = $('mol-chapter');
  let chapters = config.chapters;
  let chapterId = 0;
  let pages = [];
  let formDirty = false;
  let orderDirty = false;
  let loadingSequence = 0;
  const previews = new Map();
  const queue = new UploadQueue({ concurrency: config.manage ? 2 : 1, onChange: renderQueue });

  function notice(message, error = false) {
    const area = $('mol-notice');
    area.textContent = message;
    area.className = message ? (error ? 'mol-notice mol-error' : 'mol-notice') : '';
  }

  async function api(path, method = 'GET', body) {
    const headers = { 'X-WP-Nonce': config.nonce };
    if (body !== undefined) headers['Content-Type'] = 'application/json';
    let response;
    try {
      response = await fetch(config.api.replace(/\/$/, '') + '/' + path, {
        method, headers, credentials: 'same-origin', cache: 'no-store',
        body: body === undefined ? undefined : JSON.stringify(body),
      });
    } catch {
      throw new Error('تعذر الاتصال. تحقق من الشبكة ثم أعد المحاولة.');
    }
    if (response.status === 204) return null;
    const result = await response.json();
    if (!response.ok) throw new Error(result.message || 'تعذر إكمال العملية.');
    return result;
  }

  function renderChapters() {
    chapterSelect.replaceChildren(new Option('اختر فصلًا', ''));
    for (const chapter of chapters) {
      const title = chapter.chapter_label + (chapter.title ? ' — ' + chapter.title : '') + (chapter.is_published ? '' : ' (مسودة)');
      chapterSelect.add(new Option(title, String(chapter.id)));
    }
    chapterSelect.value = chapterId ? String(chapterId) : '';
  }

  function loadForm(chapter) {
    form.reset();
    const editor = $('mol-open-editor');
    if (editor) {
      editor.hidden = !chapter || !config.editorBaseUrl;
      // Chapter slugs are already canonical and URL-encoded by WordPress.
      if (!editor.hidden) editor.href = config.editorBaseUrl + chapter.slug + '/edit/';
      else editor.removeAttribute('href');
    }
    for (const control of form.elements) {
      if (!control.name) continue;
      if (control.type === 'checkbox') control.checked = chapter?.[control.name] ?? false;
      else control.value = chapter?.[control.name] ?? (control.name === 'sort_order' ? 0 : control.name === 'translation_status' ? 'untranslated' : '');
    }
    formDirty = false;
    if ($('mol-delete-chapter')) $('mol-delete-chapter').disabled = !chapterId;
    for (const button of document.querySelectorAll('[data-review]')) button.disabled = !chapterId;
  }

  function button(label, action) {
    const element = document.createElement('button');
    element.type = 'button';
    element.className = 'button';
    element.textContent = label;
    element.addEventListener('click', action);
    return element;
  }

  function renderPages() {
    $('mol-pages').replaceChildren();
    $('mol-page-count').textContent = pages.length ? '(' + pages.length + ')' : '';
    if (!pages.length) {
      const empty = document.createElement('p');
      empty.className = 'mol-empty';
      empty.textContent = chapterId ? 'لا توجد صفحات لهذا الفصل بعد.' : 'اختر فصلًا لعرض صفحاته.';
      $('mol-pages').append(empty);
    }
    pages.forEach((page, index) => {
      const card = document.createElement('article');
      card.className = 'mol-page-card';
      const img = document.createElement('img');
      img.src = page.image.url;
      img.alt = 'الصفحة ' + (index + 1);
      img.loading = 'lazy';
      img.width = page.natural_width;
      img.height = page.natural_height;
      card.append(img);
      const caption = document.createElement('p');
      caption.textContent = 'صفحة ' + (index + 1) + ' · ' + page.natural_width + ' × ' + page.natural_height;
      card.append(caption);
      if (config.manage) {
        const actions = document.createElement('div');
        actions.className = 'mol-page-actions';
        const move = target => {
          pages.splice(target, 0, pages.splice(index, 1)[0]);
          orderDirty = true;
          renderPages();
        };
        const positionLabel = document.createElement('label');
        positionLabel.textContent = 'الموضع';
        const position = document.createElement('input');
        position.type = 'number'; position.min = '1'; position.max = String(pages.length); position.step = '1';
        position.value = String(index + 1); position.disabled = queue.running;
        position.setAttribute('aria-label', 'موضع الصفحة ' + (index + 1));
        position.style.inlineSize = '70px';
        position.addEventListener('change', () => {
          const target = Number(position.value);
          if (!Number.isInteger(target) || target < 1 || target > pages.length) {
            position.value = String(index + 1);
            notice('اختر موضعًا من 1 إلى ' + pages.length + '.', true);
            return;
          }
          move(target - 1);
        });
        positionLabel.append(position);
        actions.append(positionLabel);
        const before = button('السابق', () => move(index - 1));
        const after = button('التالي', () => move(index + 1));
        before.disabled = index === 0 || queue.running;
        after.disabled = index === pages.length - 1 || queue.running;
        const remove = button('حذف', async () => {
          if (!confirm('حذف هذه الصفحة وعناصر ترجمتها؟ تبقى الصورة الأصلية في مكتبة الوسائط.')) return;
          remove.disabled = true;
          try {
            await api('pages/' + page.id, 'DELETE');
            await refreshPages();
            notice('حُذفت الصفحة.');
          } catch (error) { notice(error.message, true); remove.disabled = false; }
        });
        remove.disabled = queue.running;
        actions.append(before, after, remove);
        card.append(actions);
      }
      $('mol-pages').append(card);
    });
    if ($('mol-save-order')) $('mol-save-order').disabled = !orderDirty || queue.running || !pages.length;
  }

  async function refreshPages() {
    const sequence = ++loadingSequence;
    const requestedId = chapterId;
    if (!requestedId) { pages = []; orderDirty = false; renderPages(); return; }
    try {
      const response = await api('chapters/' + requestedId + '/pages');
      if (sequence !== loadingSequence || requestedId !== chapterId) return;
      pages = response.data;
      orderDirty = false;
      renderPages();
    } catch (error) {
      if (sequence === loadingSequence) notice(error.message, true);
    }
  }

  function selectChapter(id) {
    chapterId = id;
    loadForm(chapters.find(chapter => chapter.id === id));
    orderDirty = false;
    renderChapters();
    refreshPages();
    renderQueue();
  }

  function canLeave() {
    return !(formDirty || orderDirty) || confirm('لديك تغييرات غير محفوظة. هل تريد تركها؟');
  }

  chapterSelect.addEventListener('change', () => {
    if (queue.running || !canLeave()) { chapterSelect.value = chapterId ? String(chapterId) : ''; return; }
    if (queue.jobs.some(job => job.status !== 'done') && !confirm('تغيير الفصل يزيل قائمة الملفات غير المرفوعة. هل تريد المتابعة؟')) {
      chapterSelect.value = chapterId ? String(chapterId) : '';
      return;
    }
    queue.jobs = [];
    for (const url of previews.values()) URL.revokeObjectURL(url);
    previews.clear();
    selectChapter(Number(chapterSelect.value));
  });
  $('mol-work').addEventListener('change', () => {
    if (queue.running || !canLeave()) { $('mol-work').value = String(config.workId); return; }
    const url = new URL(location.href);
    url.searchParams.set('work_id', $('mol-work').value);
    formDirty = false; orderDirty = false;
    location.assign(url);
  });
  $('mol-new-chapter')?.addEventListener('click', () => {
    if (!queue.running && canLeave()) {
      if (queue.jobs.some(job => job.status !== 'done') && !confirm('إزالة قائمة الرفع الحالية لبدء فصل جديد؟')) return;
      queue.jobs = [];
      for (const url of previews.values()) URL.revokeObjectURL(url);
      previews.clear();
      selectChapter(0);
      form.elements.chapter_label.focus();
    }
  });
  form.addEventListener('input', () => { formDirty = true; });
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (!config.manage || queue.running || !form.reportValidity()) return;
    const values = new FormData(form);
    const body = {
      chapter_label: values.get('chapter_label'), title: values.get('title') || null,
      sort_order: Number(values.get('sort_order')), translation_status: values.get('translation_status'),
      source_lang_override: values.get('source_lang_override') || null,
      reader_mode_override: values.get('reader_mode_override') || null,
      direction_override: values.get('direction_override') || null,
      is_published: form.elements.is_published.checked,
    };
    if (!chapterId) body.work_id = config.workId;
    const fieldset = form.querySelector('fieldset');
    fieldset.disabled = true;
    $('mol-save-chapter').disabled = true;
    try {
      const result = await api(chapterId ? 'chapters/' + chapterId : 'chapters', chapterId ? 'PATCH' : 'POST', body);
      chapters = [...chapters.filter(chapter => chapter.id !== result.data.id), result.data].sort((a, b) => a.sort_order - b.sort_order || a.id - b.id);
      selectChapter(result.data.id);
      notice('حُفظ الفصل.');
    } catch (error) { notice(error.message, true); }
    finally { fieldset.disabled = false; $('mol-save-chapter').disabled = false; }
  });
  $('mol-delete-chapter')?.addEventListener('click', async () => {
    if (!chapterId || queue.running || !confirm('حذف الفصل وكل صفحاته وعناصره والبيانات المرتبطة؟ تبقى الصور الأصلية في مكتبة الوسائط.')) return;
    try {
      await api('chapters/' + chapterId, 'DELETE');
      chapters = chapters.filter(chapter => chapter.id !== chapterId);
      selectChapter(0);
      notice('حُذف الفصل.');
    } catch (error) { notice(error.message, true); }
  });
  for (const control of document.querySelectorAll('[data-review]')) {
    control.addEventListener('click', async () => {
      if (!chapterId || queue.running || !canLeave()) return;
      try {
        const result = await api('chapters/' + chapterId + '/review', 'PATCH', { translation_status: control.dataset.review });
        chapters = chapters.map(chapter => chapter.id === result.data.id ? result.data : chapter);
        loadForm(result.data);
        notice('حُدّثت حالة مراجعة الترجمة.');
      } catch (error) { notice(error.message, true); }
    });
  }
  $('mol-refresh-pages').addEventListener('click', () => { if (!queue.running && (!orderDirty || confirm('تجاهل تغييرات الترتيب وتحديث الصفحات؟'))) refreshPages(); });
  $('mol-save-order')?.addEventListener('click', async () => {
    if (!chapterId || queue.running) return;
    $('mol-save-order').disabled = true;
    try {
      const result = await api('chapters/' + chapterId + '/pages/reorder', 'PATCH', { page_ids: pages.map(page => page.id) });
      pages = result.data; orderDirty = false; renderPages(); notice('حُفظ ترتيب الصفحات.');
    } catch (error) { notice(error.message + ' حدّث القائمة قبل إعادة الترتيب.', true); $('mol-save-order').disabled = false; }
  });

  function renderQueue() {
    if (!$('mol-upload-queue')) return;
    $('mol-upload-queue').replaceChildren();
    queue.jobs.forEach((job, index) => {
      const row = document.createElement('li');
      row.className = 'mol-upload-item';
      const image = document.createElement('img');
      if (!previews.has(job.key)) previews.set(job.key, URL.createObjectURL(job.file));
      image.src = previews.get(job.key); image.alt = ''; image.width = 48; image.height = 64;
      const label = document.createElement('div');
      const name = document.createElement('strong'); name.textContent = job.file.name;
      const state = document.createElement('span');
      state.textContent = job.status === 'done' ? 'اكتمل الرفع' : job.status === 'error' ? job.error : job.status === 'uploading' ? 'جارٍ الرفع والمعالجة · ' + Math.round(job.progress) + '%' : 'بانتظار الرفع';
      const progress = document.createElement('progress'); progress.max = 100; progress.value = job.progress; progress.setAttribute('aria-label', 'تقدم رفع ' + job.file.name);
      label.append(name, state, progress);
      row.append(image, label);
      if (!queue.running && job.status === 'queued') {
        const previous = button('أعلى', () => queue.move(index, index - 1)); previous.disabled = index === 0;
        const next = button('أسفل', () => queue.move(index, index + 1)); next.disabled = index === queue.jobs.length - 1;
        row.append(previous, next);
      }
      $('mol-upload-queue').append(row);
    });
    $('mol-start-upload').disabled = !chapterId || queue.running || !queue.jobs.some(job => job.status === 'queued');
    $('mol-retry-upload').hidden = !queue.jobs.some(job => job.status === 'error');
    $('mol-retry-upload').disabled = queue.running || !chapterId;
    if (queue.running) for (const control of document.querySelectorAll('#mol-pages button, #mol-pages input')) control.disabled = true;
    for (const id of ['mol-work', 'mol-chapter', 'mol-new-chapter', 'mol-images', 'mol-delete-chapter', 'mol-refresh-pages']) {
      const control = $(id);
      if (control) control.disabled = queue.running || (id === 'mol-delete-chapter' && !chapterId);
    }
  }

  function upload(job, progress, id) {
    return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();
      xhr.open('POST', config.api.replace(/\/$/, '') + '/chapters/' + id + '/pages');
      xhr.setRequestHeader('X-WP-Nonce', config.nonce);
      xhr.setRequestHeader('MOL-Idempotency-Key', job.key);
      xhr.timeout = 120000;
      xhr.upload.onprogress = event => { if (event.lengthComputable) progress(event.loaded / event.total * 100); };
      xhr.onerror = xhr.ontimeout = () => reject(new Error('انقطع الاتصال. أعد المحاولة للتحقق من نتيجة الرفع السابقة.'));
      xhr.onload = () => {
        let response;
        try { response = JSON.parse(xhr.responseText); }
        catch { reject(new Error('تعذر قراءة استجابة الخادم. أعد المحاولة.')); return; }
        if (xhr.status >= 200 && xhr.status < 300) resolve(response.data);
        else reject(new Error(response.message || 'تعذر رفع الصورة.'));
      };
      const body = new FormData(); body.append('image', job.file); xhr.send(body);
    });
  }

  async function startUploads(retry = false) {
    if (!chapterId || queue.running) return;
    const id = chapterId;
    notice('');
    await queue.run((job, progress) => upload(job, progress, id), retry);
    try {
      const latest = await api('chapters/' + id + '/pages');
      const uploaded = queue.jobs.filter(job => job.status === 'done').map(job => job.result.id);
      if (config.manage && uploaded.length) {
        const available = new Set(latest.data.map(page => page.id));
        const ours = new Set(uploaded);
        const ids = [...latest.data.filter(page => !ours.has(page.id)).map(page => page.id), ...uploaded.filter(pageId => available.has(pageId))];
        const result = await api('chapters/' + id + '/pages/reorder', 'PATCH', { page_ids: ids });
        if (id === chapterId) { pages = result.data; orderDirty = false; renderPages(); }
      } else if (id === chapterId) { pages = latest.data; renderPages(); }
      notice(queue.jobs.some(job => job.status === 'error') ? 'اكتمل رفع بعض الملفات. يمكنك إعادة محاولة الملفات المتعثرة.' : 'اكتمل رفع الصفحات وحفظ ترتيبها.');
    } catch (error) { notice('حُفظت الصور المرفوعة، وتعذر تحديث ترتيب القائمة: ' + error.message, true); }
  }
  $('mol-start-upload')?.addEventListener('click', () => startUploads());
  $('mol-retry-upload')?.addEventListener('click', () => startUploads(true));
  $('mol-images')?.addEventListener('change', event => {
    if (!queue.running) queue.add(event.target.files);
    event.target.value = '';
  });
  const drop = $('mol-drop-zone');
  drop?.addEventListener('dragover', event => { event.preventDefault(); drop.classList.add('mol-dragover'); });
  drop?.addEventListener('dragleave', () => drop.classList.remove('mol-dragover'));
  drop?.addEventListener('drop', event => {
    event.preventDefault(); drop.classList.remove('mol-dragover');
    if (!queue.running) queue.add(event.dataTransfer.files);
  });
  window.addEventListener('beforeunload', event => {
    if (queue.running || formDirty || orderDirty) { event.preventDefault(); event.returnValue = ''; }
  });
  renderChapters(); loadForm(null); renderPages(); renderQueue();
  // Never allow a native GET submission before the REST submit handler is ready.
  if (config.manage) {
    form.querySelector('fieldset').disabled = false;
    $('mol-save-chapter').disabled = false;
  }
  notice('');
}
