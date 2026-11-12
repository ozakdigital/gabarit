// Routage minimal par ancre (#/docs/installation, #/new?framework=laravel) : aucune dépendance,
// aucune règle côté serveur.
import { computed, ref } from 'vue'

const hash = ref(window.location.hash)

window.addEventListener('hashchange', () => {
  const samePage = hash.value.split('?')[0] === window.location.hash.split('?')[0]
  hash.value = window.location.hash
  if (!samePage) window.scrollTo(0, 0)
})

export interface Route {
  path: string
  section: string
  slug: string
  query: URLSearchParams
}

export function parseHash(value: string): Route {
  const raw = value.replace(/^#/, '') || '/'
  const [pathPart = '/', queryPart = ''] = raw.split('?', 2)
  const path = pathPart || '/'
  const [, section = '', slug = ''] = path.split('/')
  return { path, section, slug, query: new URLSearchParams(queryPart) }
}

export const route = computed(() => parseHash(hash.value))

export function link(path: string): string {
  return `#${path}`
}

/** Met à jour les paramètres de la page courante sans recharger ni ajouter d'entrée d'historique. */
export function replaceQuery(params: URLSearchParams): void {
  const path = route.value.path
  const query = params.toString()
  const next = `#${path}${query ? `?${query}` : ''}`
  if (next === window.location.hash) return
  window.history.replaceState(window.history.state, '', next)
  hash.value = next
}
