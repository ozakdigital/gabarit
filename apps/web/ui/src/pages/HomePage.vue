<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { loadMatrix, matrix } from '../matrix'
import { link } from '../router'

onMounted(() => void loadMatrix())

// Les listes viennent de la matrice : rien n'est codé en dur.
const stack = computed(() => {
  const m = matrix.value
  if (!m) return []
  return [
    { title: 'Frameworks', items: Object.values(m.frameworks).map((f) => `${f.label} ${Object.keys(f.versions).join(', ')}`) },
    { title: 'Bases', items: Object.values(m.databases).map((d) => d.label) },
    { title: 'Fronts', items: Object.values(m.frontends).map((f) => f.label) },
    { title: 'Services', items: Object.values(m.services).map((s) => s.label) },
  ]
})

const pipeline = [
  ['Validation', 'contre la matrice'],
  ['Framework', 'composer create-project'],
  ['Front', 'create-vite · ng new'],
  ['Base de données', 'connexion + /api/health'],
  ['Docker', 'Dockerfile, compose, nginx'],
  ['Services', 'Redis, Mailpit'],
  ['Marque', 'nom, namespace, couleurs'],
  ['Fichiers communs', 'Makefile, README, CI'],
  ['Secrets', 'générés chez vous'],
  ['Git', 'premier commit'],
]

const promises = [
  { kicker: '// docker seul', title: "Rien d'autre à installer", text: 'Ni PHP, ni Composer, ni Node sur votre machine : la CLI et le projet tournent dans des conteneurs.' },
  { kicker: '// marque blanche', title: 'À vos couleurs', text: 'Nom, namespace PHP, couleurs et logo appliqués dans tout le projet dès le premier commit.' },
  { kicker: '// secrets locaux', title: 'Jamais sur un serveur', text: 'Mots de passe aléatoires générés sur votre machine, dans un fichier ignoré par git.' },
]
</script>

<template>
  <section class="hero">
    <div class="hero-glow" aria-hidden="true"></div>
    <div class="hero-inner">
      <span class="tag pill">◇ Générateur de projets PHP · marque blanche</span>
      <h1>Du choix au projet en ligne,<br /><span>en une commande.</span></h1>
      <p class="lead">
        Framework, base de données, front et marque : Gabarit assemble un projet complet et dockerisé
        avec les installeurs officiels. Seul Docker est nécessaire.
      </p>
      <div class="cta">
        <a class="btn primary" :href="link('/docs/demarrage')">Commencer →</a>
        <a class="btn ghost" :href="link('/new')">Générer en ligne</a>
      </div>

      <div class="glass terminal">
        <div class="terminal-bar" aria-hidden="true"><i></i><i></i><i></i><span>~/projets — gabarit</span></div>
        <pre><span class="t-prompt">❯</span> gabarit new boutique --framework=laravel --db=mariadb --frontend=vue
<span class="t-dim">  [1/10]</span> Validation des choix <span class="t-ok">✔</span>
<span class="t-dim">  [2/10]</span> Installation du framework · Laravel 13 <span class="t-ok">✔</span>
<span class="t-dim">  [3/10]</span> Installation du front · Vue 3 <span class="t-ok">✔</span>
<span class="t-dim">  [4/10]</span> Base de données · MariaDB <span class="t-ok">✔</span>
<span class="t-dim">   ...</span>
<span class="t-dim"> [10/10]</span> Dépôt git initialisé <span class="t-ok">✔</span>

<span class="t-prompt">❯</span> cd boutique &amp;&amp; make up
  <span class="t-link">→ http://localhost:8080</span>  <span class="t-dim">/api/health · database: ok</span></pre>
      </div>
    </div>
  </section>

  <main class="home">
    <section class="section">
      <div class="section-head">
        <span class="tag">01 · Deux modes</span>
        <h2>Terminal ou navigateur, même moteur.</h2>
      </div>
      <div class="modes">
        <a class="glass mode" :href="link('/docs/installation')">
          <div class="mode-top">
            <span class="badge">CLI</span>
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#a78bfa" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 17l6-5-6-5" /><path d="M12 19h8" /></svg>
          </div>
          <h3>En ligne de commande</h3>
          <p>Installez Gabarit une fois sur Windows, macOS ou Linux, puis créez chaque projet avec <code>gabarit new</code>.</p>
          <div class="mode-cmd">curl -fsSL …/install.sh | sh</div>
          <span class="more">Installer la CLI →</span>
        </a>
        <a class="glass mode" :href="link('/new')">
          <div class="mode-top">
            <span class="badge cyan">WEB</span>
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#22d3ee" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12" /><path d="M7 10l5 5 5-5" /><path d="M4 19h16" /></svg>
          </div>
          <h3>Depuis le site</h3>
          <p>Choisissez vos options, téléchargez un starter de quelques Ko, décompressez-le et lancez <code>bin/up</code>. Docker fait le reste.</p>
          <div class="mode-cmd">unzip mon-app.zip &amp;&amp; ./mon-app/bin/up</div>
          <span class="more cyan">Ouvrir le générateur →</span>
        </a>
      </div>
    </section>

    <section class="section">
      <div class="section-head">
        <span class="tag">02 · Le pipeline</span>
        <h2>Dix couches, toujours dans le même ordre.</h2>
        <p>Aucun squelette figé : l'installeur officiel d'abord, puis les couches de Gabarit par-dessus.</p>
      </div>
      <ol class="pipeline">
        <li v-for="([title, text], i) in pipeline" :key="title" class="glass">
          <span class="num">{{ String(i + 1).padStart(2, '0') }}</span>
          <strong>{{ title }}</strong>
          <span>{{ text }}</span>
        </li>
      </ol>
    </section>

    <section v-if="stack.length" class="section">
      <div class="section-head">
        <span class="tag">03 · Compatibilité</span>
        <h2>Chaque combinaison est vérifiée.</h2>
      </div>
      <div class="glass stack">
        <div v-for="row in stack" :key="row.title" class="stack-row">
          <h3>{{ row.title }}</h3>
          <div class="chips"><span v-for="item in row.items" :key="item" class="chip">{{ item }}</span></div>
        </div>
      </div>
      <p class="matrix-link">Toutes les combinaisons : <a :href="link('/docs/combinaisons')">voir la matrice</a>.</p>
    </section>

    <section class="section">
      <div class="promises">
        <div v-for="p in promises" :key="p.title" class="promise">
          <span class="mono">{{ p.kicker }}</span>
          <h3>{{ p.title }}</h3>
          <p>{{ p.text }}</p>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="final">
        <h2>Prêt à lancer votre prochain projet ?</h2>
        <p>Il vous faut seulement Docker.</p>
        <div class="cta">
          <a class="btn primary" :href="link('/docs/demarrage')">Lire le démarrage rapide</a>
          <a class="btn cyan" :href="link('/new')">Générer un projet</a>
        </div>
      </div>
    </section>
  </main>
</template>
