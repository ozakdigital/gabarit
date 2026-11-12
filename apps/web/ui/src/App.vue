<script setup lang="ts">
import { computed } from 'vue'
import { link, route } from './router'
import HomePage from './pages/HomePage.vue'
import DocsPage from './pages/DocsPage.vue'
import GeneratorPage from './pages/GeneratorPage.vue'

const GITHUB = 'https://github.com/ozakdigital/gabarit'
// Servi depuis public/ (pas importé par Vite).
const LOGO = '/favicon.svg'

const page = computed(() => {
  if (route.value.section === 'docs') return DocsPage
  if (route.value.section === 'new') return GeneratorPage
  return HomePage
})

// Petit suffixe façon chemin à côté du logo : « / docs », « / new ».
const path = computed(() => (route.value.section === 'docs' || route.value.section === 'new' ? `/ ${route.value.section}` : ''))
</script>

<template>
  <header class="nav">
    <nav class="nav-inner" aria-label="Navigation principale">
      <a class="brand" :href="link('/')">
        <img :src="LOGO" alt="" width="32" height="32" />
        Gabarit
        <span v-if="path" class="brand-path">{{ path }}</span>
      </a>
      <div class="nav-links">
        <a :href="link('/docs/demarrage')" :class="{ active: route.section === 'docs' }">Documentation</a>
        <a :href="link('/new')" :class="{ active: route.section === 'new' }">Générateur</a>
        <a :href="GITHUB" target="_blank" rel="noopener">GitHub</a>
        <a v-if="route.section !== 'new'" class="nav-cta" :href="link('/new')">Générer un projet</a>
      </div>
    </nav>
  </header>

  <component :is="page" />

  <footer class="site-footer">
    <div class="footer-inner">
      <span class="mono">© Gabarit · licence MIT</span>
      <span>
        <a :href="link('/docs/demarrage')">Documentation</a>
        <a :href="GITHUB" target="_blank" rel="noopener">GitHub</a>
      </span>
    </div>
  </footer>
</template>
