<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import { ApiError, downloadStarter } from '../api'
import { loadMatrix, matrix, matrixError as loadError } from '../matrix'
import { link, replaceQuery, route } from '../router'

const NAME_PATTERN = /^[a-z0-9][a-z0-9-]{0,38}[a-z0-9]$/

const generating = ref(false)
const error = ref('')
const violations = ref<Record<string, string>>({})
const logo = ref<File | null>(null)

const form = reactive({
  name: 'mon-app',
  framework: '',
  frameworkVersion: '',
  phpVersion: '',
  database: '',
  frontend: '',
  services: [] as string[],
  displayName: 'Mon App',
  phpNamespace: 'App',
  primaryColor: '#7c3aed',
  secondaryColor: '#0f172a',
})

// Toutes les options viennent de la matrice : aucune règle de compatibilité n'est codée ici.
const framework = computed(() => (matrix.value && form.framework ? matrix.value.frameworks[form.framework] : undefined))
const versions = computed(() => Object.keys(framework.value?.versions ?? {}))
const phpVersions = computed(() => framework.value?.versions[form.frameworkVersion]?.php ?? [])
const nameValid = computed(() => NAME_PATTERN.test(form.name) && !form.name.includes('--'))

// Toutes les valeurs connues de la matrice ; celles absentes du framework choisi sont grisées.
const allPhp = computed(() => {
  const set = new Set<string>()
  for (const fw of Object.values(matrix.value?.frameworks ?? {})) for (const v of Object.values(fw.versions)) v.php.forEach((p) => set.add(p))
  return [...set].sort()
})
const allDatabases = computed(() => Object.keys(matrix.value?.databases ?? {}))
const allServerFrontends = computed(() => Object.keys(matrix.value?.frontends ?? {}).filter((f) => !matrix.value?.frontends[f]?.spa))
const allSpaFrontends = computed(() => Object.keys(matrix.value?.frontends ?? {}).filter((f) => matrix.value?.frontends[f]?.spa))
const hasDatabase = (db: string): boolean => framework.value?.databases.includes(db) ?? false
const hasFrontend = (f: string): boolean => framework.value?.frontends.includes(f) ?? false

function pick<T>(current: T, available: T[], preferred?: T): T {
  if (available.includes(current)) return current
  if (preferred !== undefined && available.includes(preferred)) return preferred
  return available[0] as T
}

// Garde des choix cohérents quand une option en amont change.
watch(
  () => form.framework,
  () => {
    form.frameworkVersion = pick(form.frameworkVersion, versions.value, versions.value[versions.value.length - 1])
    form.database = pick(form.database, framework.value?.databases ?? [])
    form.frontend = pick(form.frontend, framework.value?.frontends ?? [])
    if (!framework.value?.customNamespace) form.phpNamespace = 'App'
  },
)
watch(
  () => form.frameworkVersion,
  () => {
    form.phpVersion = pick(form.phpVersion, phpVersions.value, '8.3')
  },
)

// Nom d'affichage déduit du nom tant que l'utilisateur ne l'a pas modifié.
const displayNameTouched = ref(false)
watch(
  () => form.name,
  (name) => {
    if (!displayNameTouched.value) {
      form.displayName = name.split('-').filter(Boolean).map((w) => w[0]!.toUpperCase() + w.slice(1)).join(' ') || 'App'
    }
  },
)

const HEX = /^#[0-9a-f]{6}$/i

/**
 * Relit une configuration partagée (#/new?framework=…). Chaque valeur est vérifiée contre la matrice
 * et appliquée dans l'ordre des dépendances ; une valeur inconnue est ignorée.
 */
async function applySharedQuery(query: URLSearchParams): Promise<void> {
  const m = matrix.value
  if (!m) return
  const fw = query.get('framework')
  if (fw && fw in m.frameworks) form.framework = fw
  await nextTick()
  const version = query.get('version')
  if (version && versions.value.includes(version)) form.frameworkVersion = version
  await nextTick()
  const php = query.get('php')
  if (php && phpVersions.value.includes(php)) form.phpVersion = php
  const db = query.get('db')
  if (db && hasDatabase(db)) form.database = db
  const front = query.get('frontend')
  if (front && hasFrontend(front)) form.frontend = front
  if (query.has('services')) form.services = (query.get('services') ?? '').split(',').filter((s) => s in m.services)
  const name = query.get('name')
  if (name !== null) form.name = name.slice(0, 40)
  const display = query.get('brand')
  if (display !== null) {
    form.displayName = display.slice(0, 60)
    displayNameTouched.value = true
  }
  const ns = query.get('namespace')
  if (ns !== null && framework.value?.customNamespace) form.phpNamespace = ns.slice(0, 80)
  const primary = query.get('primary')
  if (primary && HEX.test(primary)) form.primaryColor = primary
  const secondary = query.get('secondary')
  if (secondary && HEX.test(secondary)) form.secondaryColor = secondary
}

const shareParams = computed(() => {
  const p = new URLSearchParams()
  p.set('framework', form.framework)
  p.set('version', form.frameworkVersion)
  p.set('php', form.phpVersion)
  p.set('db', form.database)
  p.set('frontend', form.frontend)
  p.set('services', form.services.join(','))
  p.set('name', form.name)
  p.set('brand', form.displayName)
  if (form.phpNamespace !== 'App') p.set('namespace', form.phpNamespace)
  p.set('primary', form.primaryColor)
  p.set('secondary', form.secondaryColor)
  return p
})

const ready = ref(false)
onMounted(async () => {
  await loadMatrix()
  if (!matrix.value) return
  if (!form.framework) form.framework = Object.keys(matrix.value.frameworks)[0] ?? ''
  await nextTick()
  await applySharedQuery(route.value.query)
  await nextTick()
  ready.value = true
})

// L'adresse suit les choix : elle se partage ou se met en favori telle quelle.
watch(shareParams, (params) => {
  if (ready.value) replaceQuery(params)
})

const linkCopied = ref(false)
async function copyLink(): Promise<void> {
  replaceQuery(shareParams.value)
  try {
    await navigator.clipboard.writeText(window.location.href)
    linkCopied.value = true
    setTimeout(() => (linkCopied.value = false), 1500)
  } catch {
    // presse-papiers indisponible : l'adresse de la page reste copiable à la main
  }
}

function onLogo(event: Event): void {
  logo.value = (event.target as HTMLInputElement).files?.[0] ?? null
}

const summary = computed(() =>
  [
    `${framework.value?.label ?? ''} ${form.frameworkVersion}`,
    `PHP ${form.phpVersion}`,
    matrix.value?.databases[form.database]?.label,
    matrix.value?.frontends[form.frontend]?.label,
  ]
    .filter(Boolean)
    .join(' · '),
)

// La même génération en ligne de commande.
const cliCommand = computed(() => {
  const parts = [
    `gabarit new ${form.name}`,
    `--framework=${form.framework}`,
    `--fw-version=${form.frameworkVersion}`,
    `--php=${form.phpVersion}`,
    `--db=${form.database}`,
    `--frontend=${form.frontend}`,
    `--services=${form.services.length ? form.services.join(',') : 'none'}`,
    `--brand-name="${form.displayName}"`,
  ]
  if (form.phpNamespace !== 'App') parts.push(`--namespace='${form.phpNamespace}'`)
  parts.push(`--primary-color="${form.primaryColor}"`, `--secondary-color="${form.secondaryColor}"`)
  return parts.join(' ')
})

async function generate(): Promise<void> {
  if (!nameValid.value || generating.value) return
  generating.value = true
  error.value = ''
  violations.value = {}
  try {
    const { blob, filename } = await downloadStarter(
      {
        name: form.name,
        framework: form.framework,
        frameworkVersion: form.frameworkVersion,
        phpVersion: form.phpVersion,
        database: form.database,
        frontend: form.frontend,
        services: form.services,
        brand: {
          displayName: form.displayName,
          phpNamespace: form.phpNamespace,
          primaryColor: form.primaryColor,
          secondaryColor: form.secondaryColor,
        },
      },
      logo.value,
    )
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = filename
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    error.value = e instanceof Error ? e.message : String(e)
    if (e instanceof ApiError) violations.value = e.violations
  } finally {
    generating.value = false
  }
}

function onKey(event: KeyboardEvent): void {
  if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') void generate()
}
</script>

<template>
  <main class="generator" @keydown="onKey">
    <header class="generator-head">
      <span class="tag">◇ Configurer le projet</span>
      <h1>Assemblez votre starter</h1>
      <p>
        Les options incompatibles se grisent d'elles-mêmes : tout vient de la matrice.
        <a :href="link('/docs/starter')">Comment ça marche ?</a>
      </p>
    </header>

    <p v-if="loadError" class="alert">{{ loadError }}</p>
    <p v-else-if="!matrix" class="loading">Chargement des options…</p>

    <div v-else class="generator-grid">
      <div class="options">
        <section class="glass panel" aria-labelledby="fw-title">
          <div class="panel-head"><h2 id="fw-title" class="tag">01 · Framework</h2></div>
          <div class="tiles big">
            <button v-for="(fw, key) in matrix.frameworks" :key="key" type="button" class="opt" :class="{ on: form.framework === key }" :aria-pressed="form.framework === key" @click="form.framework = String(key)">
              <strong>{{ fw.label }}</strong>
              <span class="mono">{{ Object.keys(fw.versions).join(' · ') }}</span>
            </button>
          </div>
          <div v-if="versions.length > 1" class="tiles">
            <button v-for="v in versions" :key="v" type="button" class="opt mono" :class="{ on: form.frameworkVersion === v }" :aria-pressed="form.frameworkVersion === v" @click="form.frameworkVersion = v">{{ v }}</button>
          </div>
        </section>

        <div class="pair">
          <section class="glass panel" aria-labelledby="php-title">
            <h2 id="php-title" class="tag">02 · PHP</h2>
            <div class="tiles">
              <button
                v-for="php in allPhp"
                :key="php"
                type="button"
                class="opt mono"
                :class="{ on: form.phpVersion === php }"
                :aria-pressed="form.phpVersion === php"
                :disabled="!phpVersions.includes(php)"
                @click="form.phpVersion = php"
              >
                {{ php }}
              </button>
            </div>
            <span v-if="phpVersions.length < allPhp.length" class="sub">Grisé : non pris en charge par {{ framework?.label }} {{ form.frameworkVersion }}</span>
          </section>

          <section class="glass panel" aria-labelledby="db-title">
            <h2 id="db-title" class="tag">03 · Base de données</h2>
            <div class="tiles">
              <button
                v-for="db in allDatabases"
                :key="db"
                type="button"
                class="opt"
                :class="{ on: form.database === db }"
                :aria-pressed="form.database === db"
                :disabled="!hasDatabase(db)"
                @click="form.database = db"
              >
                {{ matrix.databases[db]?.label }}
              </button>
            </div>
          </section>
        </div>

        <section class="glass panel" aria-labelledby="front-title">
          <h2 id="front-title" class="tag">04 · Front</h2>
          <span class="sub">Rendu serveur</span>
          <div class="tiles">
            <button
              v-for="f in allServerFrontends"
              :key="f"
              type="button"
              class="opt"
              :class="{ on: form.frontend === f }"
              :aria-pressed="form.frontend === f"
              :disabled="!hasFrontend(f)"
              @click="form.frontend = f"
            >
              {{ matrix.frontends[f]?.label }}
            </button>
          </div>
          <span class="sub">Application séparée · frontend/</span>
          <div class="tiles">
            <button
              v-for="f in allSpaFrontends"
              :key="f"
              type="button"
              class="opt"
              :class="{ on: form.frontend === f }"
              :aria-pressed="form.frontend === f"
              :disabled="!hasFrontend(f)"
              @click="form.frontend = f"
            >
              {{ matrix.frontends[f]?.label }}
            </button>
          </div>
        </section>

        <section class="glass panel" aria-labelledby="svc-title">
          <h2 id="svc-title" class="tag">05 · Services</h2>
          <div class="tiles">
            <label v-for="(service, key) in matrix.services" :key="key" class="opt" :class="{ on: form.services.includes(String(key)) }">
              <input v-model="form.services" type="checkbox" :value="key" />
              {{ service.label }}
            </label>
          </div>
        </section>

        <section class="glass panel" aria-labelledby="brand-title">
          <h2 id="brand-title" class="tag">06 · Projet &amp; marque</h2>
          <div class="fields">
            <label class="field">
              Nom du projet
              <input v-model.trim="form.name" class="fld mono" :class="{ invalid: !nameValid }" maxlength="40" />
              <small v-if="!nameValid" class="hint error">2 à 40 caractères parmi a-z, 0-9 et -</small>
              <small v-if="violations.name" class="hint error">{{ violations.name }}</small>
            </label>
            <label class="field">
              Nom d'affichage
              <input v-model="form.displayName" class="fld" maxlength="60" @input="displayNameTouched = true" />
              <small v-if="violations['brand.displayName']" class="hint error">{{ violations['brand.displayName'] }}</small>
            </label>
            <label class="field">
              Namespace PHP
              <input v-model.trim="form.phpNamespace" class="fld mono" :disabled="!framework?.customNamespace" />
              <small v-if="!framework?.customNamespace" class="hint">{{ framework?.label }} impose le namespace « App ».</small>
              <small v-if="violations['brand.phpNamespace']" class="hint error">{{ violations['brand.phpNamespace'] }}</small>
            </label>
            <label class="field">
              Logo (PNG ou SVG, 1 Mo max)
              <input class="fld" type="file" accept="image/png,image/svg+xml" @change="onLogo" />
              <small v-if="violations.logo" class="hint error">{{ violations.logo }}</small>
            </label>
          </div>
          <div class="colors">
            <label class="color-field"><input v-model="form.primaryColor" type="color" />Couleur principale <span class="mono">{{ form.primaryColor }}</span></label>
            <label class="color-field"><input v-model="form.secondaryColor" type="color" />Couleur secondaire <span class="mono">{{ form.secondaryColor }}</span></label>
          </div>
        </section>
      </div>

      <aside class="summary" aria-label="Récapitulatif">
        <div class="glass summary-main">
          <span class="tag">Aperçu</span>
          <div class="preview" :style="{ '--primary': form.primaryColor, '--secondary': form.secondaryColor }">
            <strong>{{ form.displayName || 'App' }}</strong>
            <span>{{ summary }}</span>
          </div>

          <pre class="zip-tree"><b>{{ form.name || 'mon-app' }}.zip</b>
├── gabarit.json
├── bin/up
├── bin/up.cmd
{{ logo ? '├──' : '└──' }} README.md<template v-if="logo">
└── {{ logo.name }}</template></pre>

          <p v-if="error" class="alert">{{ error }}</p>
          <button type="button" class="generate" :disabled="!nameValid || generating" @click="generate">
            {{ generating ? 'Génération…' : 'Générer le starter' }} <kbd>Ctrl ⏎</kbd>
          </button>

          <ol class="next-steps">
            <li><span class="mono">1</span>Décompressez le zip</li>
            <li><span class="mono">2</span><span>Lancez <code>./bin/up</code> ou <code>bin\up.cmd</code></span></li>
            <li><span class="mono">3</span><span>Ouvrez <code>localhost:8080</code></span></li>
          </ol>
        </div>

        <button type="button" class="glass share" @click="copyLink">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 14a4 4 0 0 0 5.66 0l3-3a4 4 0 0 0-5.66-5.66l-1 1" /><path d="M14 10a4 4 0 0 0-5.66 0l-3 3a4 4 0 0 0 5.66 5.66l1-1" /></svg>
          {{ linkCopied ? 'Lien copié' : 'Copier le lien de cette configuration' }}
        </button>

        <div class="glass cli-equivalent">
          <span class="sub">Équivalent en ligne de commande</span>
          <code><span class="t-prompt">❯</span> {{ cliCommand }}</code>
        </div>
      </aside>
    </div>
  </main>
</template>
