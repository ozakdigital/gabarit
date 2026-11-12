<script setup lang="ts">
const steps = [
  ['Validation', "Vérifie chaque choix contre la matrice. En cas d'erreur, rien n'est créé."],
  ['Framework', 'composer create-project du squelette officiel, puis les paquets nécessaires.'],
  ['Front', "Twig ou Blade : page d'accueil à la marque. SPA : installeur officiel (create-vite, ng new) dans frontend/."],
  ['Base de données', 'Configuration de connexion propre au framework et route GET /api/health.'],
  ['Docker', 'Dockerfile (PHP-FPM + extensions), compose.yaml, configuration nginx et PHP.'],
  ['Services', 'Ajoute Redis et Mailpit au compose et à la configuration du framework.'],
  ['Marque', 'Nom Composer, namespace PHP, thème CSS, titres, logo.'],
  ['Fichiers communs', 'Makefile, README, .gitignore, .editorconfig, CI GitHub Actions.'],
  ['Secrets', 'Mots de passe et clés aléatoires dans un fichier non versionné.'],
  ['Git', 'git init et premier commit (désactivable avec --no-git).'],
]
</script>

<template>
  <p class="lead">
    Gabarit n'embarque aucun code de framework. Il appelle l'installeur officiel à la version de la matrice,
    puis ajoute ses propres couches, toujours dans le même ordre.
  </p>

  <ol class="steps">
    <li v-for="([title, text], i) in steps" :key="title">
      <span class="step-num">{{ i + 1 }}</span>
      <div><strong>{{ title }}</strong><p>{{ text }}</p></div>
    </li>
  </ol>

  <h2>Garanties</h2>
  <ul>
    <li><strong>Pas de shell construit à la main</strong> : chaque appel externe reçoit un tableau d'arguments.</li>
    <li><strong>Déterministe</strong> : mêmes choix, mêmes fichiers (hors secrets).</li>
    <li><strong>Idempotent</strong> : relancer une étape ne duplique rien.</li>
    <li><strong>Aucun secret en dur</strong> : les mots de passe sont générés pour chaque projet.</li>
    <li><strong>Testé</strong> : chaque combinaison de la matrice est générée et validée en CI.</li>
  </ul>
</template>
