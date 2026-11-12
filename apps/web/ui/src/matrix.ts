// Matrice chargée une seule fois et partagée par toutes les pages.
import { ref } from 'vue'
import { fetchMatrix, type Matrix } from './api'

export const matrix = ref<Matrix | null>(null)
export const matrixError = ref('')

let loading: Promise<void> | null = null

export function loadMatrix(): Promise<void> {
  loading ??= fetchMatrix()
    .then((m) => {
      matrix.value = m
      matrixError.value = ''
    })
    .catch((e: unknown) => {
      matrixError.value = e instanceof Error ? e.message : String(e)
      loading = null
    })
  return loading
}
