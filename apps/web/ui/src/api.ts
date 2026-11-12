// Client de l'API Gabarit. Les types reflètent Matrix::toArray() côté PHP.

export interface FrameworkMatrix {
  label: string
  versions: Record<string, { php: string[] }>
  databases: string[]
  frontends: string[]
  customNamespace: boolean
}

export interface Matrix {
  frameworks: Record<string, FrameworkMatrix>
  databases: Record<string, { label: string; server: boolean }>
  frontends: Record<string, { label: string; spa: boolean }>
  services: Record<string, { label: string }>
}

export interface Brand {
  displayName: string
  phpNamespace: string
  primaryColor: string
  secondaryColor: string
}

export interface StarterRequest {
  name: string
  framework: string
  frameworkVersion: string
  phpVersion: string
  database: string
  frontend: string
  services: string[]
  brand: Brand
}

/** Erreur renvoyée par l'API : message + violations champ par champ. */
export class ApiError extends Error {
  readonly violations: Record<string, string>

  constructor(message: string, violations: Record<string, string> = {}) {
    super(message)
    this.violations = violations
  }
}

export async function fetchMatrix(): Promise<Matrix> {
  const response = await fetch('/api/matrix')
  if (!response.ok) {
    throw new ApiError('Impossible de charger les options disponibles.')
  }
  return (await response.json()) as Matrix
}

/** Demande le starter zip et renvoie le fichier à enregistrer. */
export async function downloadStarter(request: StarterRequest, logo: File | null): Promise<{ blob: Blob; filename: string }> {
  const body = new FormData()
  body.append('spec', JSON.stringify(request))
  if (logo) {
    body.append('logo', logo)
  }

  const response = await fetch('/api/starter', { method: 'POST', body })
  if (!response.ok) {
    let message = 'La génération a échoué.'
    let violations: Record<string, string> = {}
    if (response.status === 429) {
      message = 'Trop de demandes : réessayez dans quelques minutes.'
    }
    try {
      const data = (await response.json()) as { message?: string; violations?: Record<string, string> }
      message = data.message ?? message
      violations = data.violations ?? {}
    } catch {
      // réponse non JSON : on garde le message générique
    }
    throw new ApiError(message, violations)
  }

  const disposition = response.headers.get('Content-Disposition') ?? ''
  const filename = /filename="?([^";]+)"?/.exec(disposition)?.[1] ?? `${request.name}.zip`
  return { blob: await response.blob(), filename }
}
