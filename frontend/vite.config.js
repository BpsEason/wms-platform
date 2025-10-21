// File: frontend/vite.config.js
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'path';

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      // 設定 @ 符號指向 src 目錄
      '@': path.resolve(__dirname, './src'),
    },
  },
});
