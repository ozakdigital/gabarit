<script setup lang="ts">
import CodeBlock from '../components/CodeBlock.vue'
import OsTabs from '../components/OsTabs.vue'
import { link } from '../router'

const install = [
  { label: 'Linux / macOS', code: 'curl -fsSL https://raw.githubusercontent.com/ozakdigital/gabarit/main/install.sh | sh' },
  { label: 'Windows (PowerShell)', code: 'irm https://raw.githubusercontent.com/ozakdigital/gabarit/main/install.ps1 | iex' },
]
</script>

<template>
  <p class="lead">
    Gabarit crée un projet PHP complet et dockerisé, à votre marque, en quelques minutes.
    Il vous faut seulement <strong>Docker</strong> (et git pour la CLI).
  </p>

  <h2>1. Installer la CLI</h2>
  <OsTabs :tabs="install" />
  <p>Ouvrez ensuite un nouveau terminal.</p>

  <h2>2. Créer un projet</h2>
  <CodeBlock code="gabarit new mon-app" />
  <p>Gabarit pose quelques questions (framework, PHP, base, front, services, marque), puis installe tout.</p>

  <h2>3. Le démarrer</h2>
  <CodeBlock code="cd mon-app
make up" />
  <p>L'application répond sur <code>http://localhost:8080</code>, et son test de santé sur <code>/api/health</code>.</p>

  <div class="callout">
    <strong>Sans installer la CLI ?</strong>
    Utilisez le <a :href="link('/new')">générateur en ligne</a> : téléchargez le starter, décompressez-le,
    puis lancez <code>bin/up</code>. <a :href="link('/docs/starter')">En savoir plus</a>.
  </div>
</template>
