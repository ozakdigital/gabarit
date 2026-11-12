// Outils partagés des tests de composants : API simulée, presse-papiers, téléchargements.
// Installés une seule fois par fichier de test (singleton sur globalThis).
import { vi } from 'vitest'
import { matrixFixture } from './fixtures/matrix'

export type StarterHandler = (body: FormData) => Response

function zipResponse(filename = 'mon-app.zip'): Response {
  return new Response('zip', {
    status: 200,
    headers: { 'Content-Type': 'application/zip', 'Content-Disposition': `attachment; filename="${filename}"` },
  })
}

interface Harness {
  fetchMock: ReturnType<typeof vi.fn<(input: RequestInfo | URL, init?: RequestInit) => Promise<Response>>>
  clipboardWrite: ReturnType<typeof vi.fn<(text: string) => Promise<void>>>
  downloads: string[]
  starter: { handler: StarterHandler }
}

function install(): Harness {
  const starter: { handler: StarterHandler } = { handler: () => zipResponse() }
  const downloads: string[] = []

  const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit): Promise<Response> => {
    const url = String(input)
    if (url === '/api/matrix') {
      return new Response(JSON.stringify(matrixFixture), { status: 200, headers: { 'Content-Type': 'application/json' } })
    }
    if (url === '/api/starter' && init?.method === 'POST') {
      return starter.handler(init.body as FormData)
    }
    return new Response('Not found', { status: 404 })
  })
  const clipboardWrite = vi.fn(async (_text: string): Promise<void> => undefined)

  vi.stubGlobal('fetch', fetchMock)
  Object.defineProperty(navigator, 'clipboard', { value: { writeText: clipboardWrite }, configurable: true })
  Object.defineProperty(URL, 'createObjectURL', { value: vi.fn(() => 'blob:gabarit'), configurable: true })
  Object.defineProperty(URL, 'revokeObjectURL', { value: vi.fn(), configurable: true })
  window.scrollTo = vi.fn() as unknown as typeof window.scrollTo
  HTMLAnchorElement.prototype.click = function click(this: HTMLAnchorElement): void {
    downloads.push(this.download)
  }

  return { fetchMock, clipboardWrite, downloads, starter }
}

const store = globalThis as typeof globalThis & { __gabaritTests?: Harness }
const harness = (store.__gabaritTests ??= install())

export const fetchMock = harness.fetchMock
export const clipboardWrite = harness.clipboardWrite
export const downloads = harness.downloads

/** Remplace la réponse de POST /api/starter pour le test en cours. */
export function onStarter(handler: StarterHandler): void {
  harness.starter.handler = handler
}

/** Remet l'environnement à zéro entre deux tests. */
export function resetHarness(): void {
  fetchMock.mockClear()
  clipboardWrite.mockClear()
  downloads.length = 0
  harness.starter.handler = () => zipResponse()
}

/** Va à une adresse interne (#/…) comme le ferait un clic sur un lien. */
export function goTo(hash: string): void {
  window.location.hash = hash
  window.dispatchEvent(new HashChangeEvent('hashchange'))
}
