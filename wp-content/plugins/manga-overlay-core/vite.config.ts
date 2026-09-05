import { defineConfig } from 'vite';

export default defineConfig({
  base: './',
  build: { outDir: 'assets/dist/poc', emptyOutDir: true, sourcemap: true },
  server: { port: 5173, strictPort: true },
});
