import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { EditorApp } from './EditorApp';
import './styles.css';

const root = document.querySelector<HTMLElement>('#root');

if (root === null) {
  throw new Error('Editor root was not found');
}

createRoot(root).render(
  <StrictMode>
    <EditorApp />
  </StrictMode>,
);
