import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig(({ mode }) => ({
  build: {
    lib: {
      entry: resolve(__dirname, 'src/index.ts'),
      name: 'NotedOverlay',
      formats: ['iife'],
      fileName: () => mode === 'development'
        ? 'noted-overlay.js'
        : 'noted-overlay.min.js',
    },
    outDir: resolve(__dirname, '../assets/js'),
    emptyOutDir: false,
    minify: mode === 'development' ? false : 'esbuild',
    sourcemap: mode === 'development',
    rollupOptions: {
      output: {
        inlineDynamicImports: true,
      },
    },
  },
}));
