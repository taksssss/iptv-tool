import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src')
    }
  },
  server: {
    port: 3000,
    proxy: {
      '/epg/api': {
        target: 'http://localhost:5678',
        changeOrigin: true
      },
      '/epg/index.php': {
        target: 'http://localhost:5678',
        changeOrigin: true
      }
    }
  },
  build: {
    outDir: '../epg/dist',
    emptyOutDir: true,
    rollupOptions: {
      output: {
        manualChunks: {
          'element-plus': ['element-plus'],
          'vue-vendor': ['vue', 'vue-router', 'pinia']
        }
      }
    }
  }
})
