import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react({ jsxRuntime: 'classic' })],
  build: {
    outDir: 'assets/dist',
    emptyOutDir: true,
    sourcemap: false,
    lib: {
      entry: 'editor-src/main.tsx',
      name: 'MOLEditor',
      formats: ['iife'],
      fileName: () => 'editor.js',
      cssFileName: 'editor',
    },
    rollupOptions: {
      external: ['react', 'react-dom/client'],
      output: {
        globals: {
          react: 'wp.element',
          'react-dom/client': 'wp.element',
        },
      },
    },
  },
  test: {
    environment: 'node',
    include: ['editor-src/**/*.test.ts'],
  },
});
