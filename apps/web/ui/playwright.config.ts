import { defineConfig, devices } from '@playwright/test'

// Scénario de bout en bout sur le vrai site (interface + API), lancé par `make test-e2e` ou la CI.
// Le site doit déjà tourner : `make web` (http://localhost:8090) ; E2E_URL pour une autre adresse.
export default defineConfig({
  testDir: 'e2e',
  timeout: 30_000,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? 'github' : 'list',
  use: {
    baseURL: process.env.E2E_URL ?? 'http://localhost:8090',
    trace: 'retain-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
})
