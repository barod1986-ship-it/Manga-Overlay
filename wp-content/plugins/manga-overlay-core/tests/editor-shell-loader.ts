import * as React from 'react';
import { createRoot } from 'react-dom/client';

declare global {
  interface Window {
    wp: {
      element: typeof React & { readonly createRoot: typeof createRoot };
    };
  }
}

window.wp = {
  element: Object.assign({}, React, { createRoot }),
};

const bundle = document.createElement('script');
bundle.src = '/assets/dist/editor.js';
bundle.addEventListener('error', () => {
  document.getElementById('mol-editor-root')?.setAttribute('data-load-error', 'true');
});
document.body.append(bundle);
