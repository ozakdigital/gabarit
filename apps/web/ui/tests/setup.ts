// Chargé avant chaque fichier de test (vite.config.ts › test.setupFiles).
import { beforeEach } from 'vitest'
import { resetHarness } from './helpers'

beforeEach(() => {
  resetHarness()
})
