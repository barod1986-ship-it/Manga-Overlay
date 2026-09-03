(() => {
  'use strict';

  const config = window.molContentAdmin;
  if (!config) return;

  const notice = document.querySelector('.mol-admin-notice');
  const notify = (message, error = false) => {
    if (!notice) return;
    notice.hidden = false;
    notice.classList.toggle('notice-error', error);
    notice.classList.toggle('notice-success', !error);
    notice.querySelector('p').textContent = message;
  };

  const api = async (path, options = {}) => {
    const response = await fetch(`${config.restRoot}${path}`, {
      credentials: 'same-origin',
      ...options,
      headers: {
        'X-WP-Nonce': config.nonce,
        ...(options.headers || {})
      }
    });
    const body = response.status === 204 ? null : await response.json().catch(() => null);
    if (!response.ok) {
      const error = new Error(body?.message || `Request failed (${response.status})`);
      error.retryAfter = response.headers.get('Retry-After');
      throw error;
    }
    return body;
  };

  const json = (method, body) => ({
    method,
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(body)
  });

  const createForm = document.querySelector('.mol-chapter-create');
  createForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const data = new FormData(createForm);
    const payload = {
      work_id: Number(data.get('work_id')),
      chapter_label: data.get('chapter_label'),
      sort_order: Number(data.get('sort_order')),
      title: data.get('title') || null,
      translation_status: data.get('translation_status'),
      reader_mode_override: data.get('reader_mode_override') || null,
      direction_override: data.get('direction_override') || null,
      is_published: data.get('is_published') === 'on'
    };
    try {
      await api('chapters', json('POST', payload));
      notify('Chapter created.');
      window.location.reload();
    } catch (error) {
      notify(error.message, true);
    }
  });

  document.querySelectorAll('.mol-save-chapter').forEach((button) => {
    button.addEventListener('click', async () => {
      const row = button.closest('[data-chapter-id]');
      const value = (name) => row.querySelector(`[name="${name}"]`);
      const payload = {
        chapter_label: value('chapter_label').value,
        sort_order: Number(value('sort_order').value),
        title: value('title').value || null,
        translation_status: value('translation_status').value,
        is_published: value('is_published').checked
      };
      try {
        await api(`chapters/${row.dataset.chapterId}`, json('PATCH', payload));
        notify('Chapter saved.');
      } catch (error) {
        notify(error.message, true);
      }
    });
  });

  document.querySelectorAll('.mol-delete-chapter').forEach((button) => {
    button.addEventListener('click', async () => {
      if (!window.confirm('Delete this chapter and all of its page data?')) return;
      const row = button.closest('[data-chapter-id]');
      try {
        await api(`chapters/${row.dataset.chapterId}`, {method: 'DELETE'});
        row.remove();
        notify('Chapter deleted.');
      } catch (error) {
        notify(error.message, true);
      }
    });
  });

  const uploadRoot = document.querySelector('.mol-upload-admin');
  if (!uploadRoot) return;
  const chapterId = Number(uploadRoot.dataset.chapterId);
  const queue = uploadRoot.querySelector('.mol-page-queue');
  const dropZone = uploadRoot.querySelector('.mol-drop-zone');
  const fileInput = uploadRoot.querySelector('.mol-file-input');

  uploadRoot.querySelector('.mol-upload-chapter-select')?.addEventListener('change', (event) => {
    const url = new URL(window.location.href);
    url.searchParams.set('chapter_id', event.target.value);
    window.location.assign(url);
  });

  const move = (item, direction) => {
    const sibling = direction < 0 ? item.previousElementSibling : item.nextElementSibling;
    if (!sibling) return;
    if (direction < 0) queue.insertBefore(item, sibling);
    else queue.insertBefore(sibling, item);
  };

  const bindItem = (item) => {
    item.querySelector('.mol-move-up')?.addEventListener('click', () => move(item, -1));
    item.querySelector('.mol-move-down')?.addEventListener('click', () => move(item, 1));
    item.querySelector('.mol-delete-page')?.addEventListener('click', async () => {
      if (!window.confirm('Delete this page and all overlay data attached to it?')) return;
      try {
        await api(`pages/${item.dataset.pageId}`, {method: 'DELETE'});
        item.remove();
        notify('Page deleted.');
      } catch (error) {
        notify(error.message, true);
      }
    });
  };
  queue?.querySelectorAll('li').forEach(bindItem);

  const addFiles = (files) => {
    [...files]
      .filter((file) => ['image/jpeg', 'image/png', 'image/webp'].includes(file.type))
      .sort((a, b) => a.name.localeCompare(b.name, undefined, {numeric: true, sensitivity: 'base'}))
      .forEach((file) => {
        const item = document.createElement('li');
        item.dataset.state = 'pending';
        item._molFile = file;
        item._molKey = window.crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`;

        const handle = document.createElement('span');
        handle.className = 'mol-drag-handle';
        handle.textContent = '⋮⋮';
        const image = document.createElement('img');
        image.src = URL.createObjectURL(file);
        image.alt = '';
        image.addEventListener('load', () => URL.revokeObjectURL(image.src), {once: true});
        const name = document.createElement('span');
        name.className = 'mol-file-name';
        name.textContent = file.name;
        const state = document.createElement('span');
        state.className = 'mol-upload-state';
        state.textContent = 'Pending';
        const actions = document.createElement('span');
        actions.className = 'mol-order-actions';
        actions.innerHTML = '<button type="button" class="button mol-move-up" aria-label="Move up">↑</button><button type="button" class="button mol-move-down" aria-label="Move down">↓</button>';
        item.append(handle, image, name, state, actions);
        queue.append(item);
        bindItem(item);
      });
  };

  dropZone?.addEventListener('click', () => fileInput.click());
  dropZone?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' || event.key === ' ') fileInput.click();
  });
  for (const eventName of ['dragenter', 'dragover']) {
    dropZone?.addEventListener(eventName, (event) => {
      event.preventDefault();
      dropZone.classList.add('is-dragging');
    });
  }
  for (const eventName of ['dragleave', 'drop']) {
    dropZone?.addEventListener(eventName, (event) => {
      event.preventDefault();
      dropZone.classList.remove('is-dragging');
    });
  }
  dropZone?.addEventListener('drop', (event) => addFiles(event.dataTransfer.files));
  fileInput?.addEventListener('change', () => {
    addFiles(fileInput.files);
    fileInput.value = '';
  });

  const uploadItem = async (item) => {
    const state = item.querySelector('.mol-upload-state');
    item.dataset.state = 'uploading';
    state.textContent = 'Uploading…';
    const body = new FormData();
    body.append('image', item._molFile, item._molFile.name);
    try {
      const response = await api(`chapters/${chapterId}/pages`, {
        method: 'POST',
        headers: {'MOL-Idempotency-Key': item._molKey},
        body
      });
      item.dataset.pageId = response.data.id;
      item.dataset.state = 'uploaded';
      state.textContent = 'Uploaded';
    } catch (error) {
      item.dataset.state = 'error';
      state.textContent = error.retryAfter ? `Retry in ${error.retryAfter}s` : error.message;
    }
  };

  const saveOrder = async () => {
    const items = [...queue.querySelectorAll('li')];
    if (items.some((item) => !item.dataset.pageId)) {
      throw new Error('Upload every queued image before saving the order.');
    }
    await api(`chapters/${chapterId}/pages/reorder`, json('PATCH', {
      page_ids: items.map((item) => Number(item.dataset.pageId))
    }));
  };

  uploadRoot.querySelector('.mol-start-upload')?.addEventListener('click', async (event) => {
    const button = event.currentTarget;
    const pending = [...queue.querySelectorAll('li')].filter((item) => ['pending', 'error'].includes(item.dataset.state));
    if (!pending.length) return notify('The upload queue is empty.', true);
    button.disabled = true;
    let cursor = 0;
    const worker = async () => {
      while (cursor < pending.length) {
        const item = pending[cursor++];
        await uploadItem(item);
      }
    };
    await Promise.all(Array.from({length: Math.min(Number(config.maxConcurrency) || 2, pending.length)}, worker));
    button.disabled = false;
    const failed = pending.some((item) => item.dataset.state === 'error');
    if (!failed && config.canManage) {
      try {
        await saveOrder();
        notify('Pages uploaded and ordered.');
      } catch (error) {
        notify(error.message, true);
      }
    } else {
      notify(failed ? 'Some uploads need to be retried.' : 'Pages uploaded.');
    }
  });

  uploadRoot.querySelector('.mol-save-order')?.addEventListener('click', async () => {
    try {
      await saveOrder();
      notify('Page order saved.');
    } catch (error) {
      notify(error.message, true);
    }
  });
})();
