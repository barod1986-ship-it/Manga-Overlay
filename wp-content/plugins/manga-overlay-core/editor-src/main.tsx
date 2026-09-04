import React, { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { EditorApp } from './EditorApp';
import type { EditorBootstrap } from './types';
import './styles.css';

function readBootstrap(): EditorBootstrap {
  const node = document.getElementById('mol-editor-bootstrap');
  if (!(node instanceof HTMLScriptElement) || node.textContent === null) {
    throw new Error('Manga Overlay editor bootstrap is missing.');
  }

  return JSON.parse(node.textContent) as EditorBootstrap;
}

function mount(): void {
  const root = document.getElementById('mol-editor-root');
  if (root === null) {
    return;
  }

  try {
    createRoot(root).render(
      <StrictMode>
        <EditorApp data={readBootstrap()} />
      </StrictMode>,
    );
  } catch (error) {
    root.textContent = 'تعذر تشغيل محرر الترجمة. أعد تحميل الصفحة وحاول مرة أخرى.';
    root.setAttribute('role', 'alert');
    console.error(error);
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mount, { once: true });
} else {
  mount();
}
