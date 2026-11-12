import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vitest/config'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue()],
  server: {
    host: '0.0.0.0',
    port: 5173,
    // En développement, l'API Symfony tourne à côté (docker compose du dossier apps/web).
    proxy: {
      '/api': {
        target: process.env.API_URL ?? 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
  // Tests de composants (npm test) ; le scénario de bout en bout est dans e2e/ (Playwright).
  test: {
    environment: 'jsdom',
    include: ['tests/unit/**/*.spec.ts'],
    setupFiles: ['tests/setup.ts'],
  },
})
