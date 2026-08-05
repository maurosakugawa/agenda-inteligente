import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

const phpBackendUrl =
  process.env.PHP_BACKEND_URL
  ?? 'http://127.0.0.1:8000';

export default defineConfig({
  plugins: [
    react(),
  ],

  server: {
    port: 3000,
    strictPort: true,

    proxy: {
      '/api': {
        target: phpBackendUrl,
        changeOrigin: true,
      },

      '/auth': {
        target: phpBackendUrl,
        changeOrigin: true,
      },
    },
  },
});
