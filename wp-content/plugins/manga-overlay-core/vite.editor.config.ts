import { defineConfig } from 'vite';
export default defineConfig({
  base: './', define: { 'process.env.NODE_ENV': JSON.stringify('production') },
  build: {
    outDir: 'assets/dist/editor', emptyOutDir: true,
    lib: { entry: 'editor-src/editor/main.tsx', formats: ['es'], fileName: () => 'editor.js', cssFileName: 'editor' },
    sourcemap: true,
  },
});
