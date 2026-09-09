import { defineConfig } from 'vite';

// Serve browser modules as .js, which managed WordPress hosts already recognize.
// Bundle relative .mjs imports too; changing only the entry URL is insufficient.
export default defineConfig({
  base: './',
  build: {
    outDir: 'assets/dist/admin', emptyOutDir: true,
    lib: {
      entry: { content: 'assets/admin/content.mjs', reports: 'assets/admin/reports.mjs' },
      formats: ['es'], fileName: (_format, entryName) => `${entryName}.js`,
    },
    sourcemap: true,
  },
});
