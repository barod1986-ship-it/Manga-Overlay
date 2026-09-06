import { createRoot } from 'react-dom/client';
import '@fontsource-variable/cairo';
import '@fontsource-variable/noto-sans-arabic';
import '@fontsource-variable/noto-kufi-arabic';
import '@fontsource/tajawal/400.css';
import '@fontsource/tajawal/500.css';
import '@fontsource/tajawal/700.css';
import '@fontsource/tajawal/800.css';
import '@fontsource/tajawal/900.css';
import { App } from './App';
import type { Bootstrap } from './state';
import './styles.css';

const node = document.getElementById('mol-editor-root');
const data = document.getElementById('mol-editor-data');
if (node && data?.textContent) createRoot(node).render(<App boot={JSON.parse(data.textContent) as Bootstrap} />);
