<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { ApiError, downloadStarter, fetchMatrix, type Matrix } from './api'

const NAME_PATTERN = /^[a-z0-9][a-z0-9-]{0,38}[a-z0-9]$/

const matrix = ref<Matrix | null>(null)
const loadError = ref('')
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
  primaryColor: '#4f46e5',
  secondaryColor: '#0f172a',
})

// Toutes les options viennent de la matrice : aucune règle de compatibilité n'est codée ici.
const framework = computed(() => (matrix.value && form.framework ? matrix.value.frameworks[form.framework] : undefined))
const versions = computed(() => Object.keys(framework.value?.versions ?? {}))
const phpVersions = computed(() => framework.value?.versions[form.frameworkVersion]?.php ?? [])
const databases = computed(() => framework.value?.databases ?? [])
const serverFrontends = computed(() => (framework.value?.frontends ?? []).filter((f) => !matrix.value?.frontends[f]?.spa))
const spaFrontends = computed(() => (framework.value?.frontends ?? []).filter((f) => matrix.value?.frontends[f]?.spa))
const nameValid = computed(() => NAME_PATTERN.test(form.name) && !form.name.includes('--'))

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
    form.database = pick(form.database, databases.value)
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

onMounted(async () => {
  try {
    matrix.value = await fetchMatrix()
    form.framework = Object.keys(matrix.value.frameworks)[0] ?? ''
  } catch (e) {
    loadError.value = e instanceof Error ? e.message : String(e)
  }
})

function onLogo(event: Event): void {
  logo.value = (event.target as HTMLInputElement).files?.[0] ?? null
}

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
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    link.click()
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
  <div class="page" @keydown="onKey">
    <header class="header">
      <div class="logo">Gabarit</div>
      <p class="tagline">Squelettes d'applications PHP en marque blanche, prêts à démarrer avec Docker.</p>
    </header>

    <p v-if="loadError" class="alert">{{ loadError }}</p>

    <main v-else-if="matrix" class="grid">
      <section class="column">
        <fieldset>
          <legend>Framework</legend>
          <label v-for="(fw, key) in matrix.frameworks" :key="key" class="choice">
            <input v-model="form.framework" type="radio" :value="key" />
            {{ fw.label }}
          </label>
        </fieldset>

        <fieldset v-if="versions.length > 1">
          <legend>Version</legend>
          <label v-for="v in versions" :key="v" class="choice">
            <input v-model="form.frameworkVersion" type="radio" :value="v" />
            {{ v }}
          </label>
        </fieldset>

        <fieldset>
          <legend>PHP</legend>
          <label v-for="php in phpVersions" :key="php" class="choice">
            <input v-model="form.phpVersion" type="radio" :value="php" />
            {{ php }}
          </label>
        </fieldset>

        <fieldset>
          <legend>Base de données</legend>
          <label v-for="db in databases" :key="db" class="choice">
            <input v-model="form.database" type="radio" :value="db" />
            {{ matrix.databases[db]?.label }}
          </label>
        </fieldset>

        <fieldset>
          <legend>Front</legend>
          <div class="group-title">Rendu serveur</div>
          <label v-for="f in serverFrontends" :key="f" class="choice">
            <input v-model="form.frontend" type="radio" :value="f" />
            {{ matrix.frontends[f]?.label }}
          </label>
          <div class="group-title">Application séparée (frontend/)</div>
          <label v-for="f in spaFrontends" :key="f" class="choice">
            <input v-model="form.frontend" type="radio" :value="f" />
            {{ matrix.frontends[f]?.label }}
          </label>
        </fieldset>

        <fieldset>
          <legend>Services</legend>
          <label v-for="(service, key) in matrix.services" :key="key" class="choice">
            <input v-model="form.services" type="checkbox" :value="key" />
            {{ service.label }}
          </label>
        </fieldset>
      </section>

      <section class="column">
        <fieldset>
          <legend>Projet</legend>
          <label class="field">
            <span>Nom (dossier, slug)</span>
            <input v-model.trim="form.name" :class="{ invalid: !nameValid }" maxlength="40" />
            <small v-if="!nameValid" class="hint error">2 à 40 caractères parmi a-z, 0-9 et -</small>
            <small v-if="violations.name" class="hint error">{{ violations.name }}</small>
          </label>
        </fieldset>

        <fieldset>
          <legend>Marque</legend>
          <label class="field">
            <span>Nom d'affichage</span>
            <input v-model="form.displayName" maxlength="60" @input="displayNameTouched = true" />
            <small v-if="violations['brand.displayName']" class="hint error">{{ violations['brand.displayName'] }}</small>
          </label>
          <label class="field">
            <span>Namespace PHP</span>
            <input v-model.trim="form.phpNamespace" :disabled="!framework?.customNamespace" />
            <small v-if="!framework?.customNamespace" class="hint">{{ framework?.label }} impose le namespace « App ».</small>
            <small v-if="violations['brand.phpNamespace']" class="hint error">{{ violations['brand.phpNamespace'] }}</small>
          </label>
          <div class="colors">
            <label class="field">
              <span>Couleur principale</span>
              <input v-model="form.primaryColor" type="color" />
            </label>
            <label class="field">
              <span>Couleur secondaire</span>
              <input v-model="form.secondaryColor" type="color" />
            </label>
          </div>
          <label class="field">
            <span>Logo (PNG ou SVG, 1 Mo max, facultatif)</span>
            <input type="file" accept="image/png,image/svg+xml" @change="onLogo" />
            <small v-if="violations.logo" class="hint error">{{ violations.logo }}</small>
          </label>
        </fieldset>

        <div class="preview" :style="{ '--primary': form.primaryColor, '--secondary': form.secondaryColor }">
          <strong>{{ form.displayName || 'App' }}</strong>
          <span>{{ framework?.label }} {{ form.frameworkVersion }} · PHP {{ form.phpVersion }} · {{ matrix.databases[form.database]?.label }} · {{ matrix.frontends[form.frontend]?.label }}</span>
        </div>
      </section>
    </main>

    <p v-else class="loading">Chargement des options…</p>

    <footer v-if="matrix" class="footer">
      <p v-if="error" class="alert">{{ error }}</p>
      <button class="generate" :disabled="!nameValid || generating" @click="generate">
        {{ generating ? 'Génération…' : 'Générer' }} <kbd>Ctrl + ⏎</kbd>
      </button>
      <p class="next">
        Ensuite : décompressez, puis lancez <code>./bin/up</code> (Linux, macOS) ou <code>bin\up.cmd</code> (Windows).
        Docker télécharge et installe tout.
      </p>
    </footer>
  </div>
</template>
