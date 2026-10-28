import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
export default defineConfig({
 base: process.env.PAGES_BASE_PATH || '/zomb-surv/',
 plugins: [react(), tailwindcss()],
 publicDir: false,
 build: { outDir: 'dist', emptyOutDir: true },
});
