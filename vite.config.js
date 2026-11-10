import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

const apiTarget = process.env.VITE_API_TARGET || 'http://127.0.0.1:8000'

export default defineConfig({
  plugins: [
    vue(),
    tailwindcss(),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    port: 5173,
    proxy: {
      '/php_action': {
        target: apiTarget,
        changeOrigin: true,
      },
      '/assests': {
        target: apiTarget,
        changeOrigin: true,
      },
    },
  },
  preview: {
    port: 4173,
    proxy: {
      '/php_action': {
        target: apiTarget,
        changeOrigin: true,
      },
      '/assests': {
        target: apiTarget,
        changeOrigin: true,
      },
    },
  },
})
