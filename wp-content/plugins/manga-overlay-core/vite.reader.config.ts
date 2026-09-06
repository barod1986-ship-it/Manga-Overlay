import { defineConfig } from 'vite';
export default defineConfig({
  base: './', define: { 'process.env.NODE_ENV': JSON.stringify('production') },
  build: {
    outDir: 'assets/dist/reader', emptyOutDir: true,
    lib: { entry: 'editor-src/reader/main.tsx', formats: ['es'], fileName: () => 'reader.js', cssFileName: 'reader' },
    sourcemap: true,
  },
});
