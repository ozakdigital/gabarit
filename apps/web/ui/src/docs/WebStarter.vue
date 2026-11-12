<script setup lang="ts">
import OsTabs from '../components/OsTabs.vue'
import CodeBlock from '../components/CodeBlock.vue'
import { link } from '../router'

const up = [
  { label: 'Linux / macOS / Git Bash', code: 'cd mon-app\n./bin/up' },
  { label: 'Windows (cmd, PowerShell)', code: 'cd mon-app\nbin\\up.cmd' },
]

const tree = `mon-app/
├── gabarit.json      ← vos choix, validés
├── bin/up            ← Linux, macOS, Git Bash
├── bin/up.cmd        ← Windows
├── README.md
└── logo.svg          ← si vous en avez fourni un`
</script>

<template>
  <p class="lead">
    Le site fonctionne comme start.spring.io : vous choisissez, vous téléchargez, vous lancez.
    Rien d'autre que Docker n'est nécessaire.
  </p>

  <h2>1. Choisir et télécharger</h2>
  <p>
    Dans le <a :href="link('/new')">générateur</a>, choisissez framework, PHP, base, front, services et marque,
    puis cliquez sur <strong>Générer</strong>. Le navigateur télécharge un <strong>starter</strong> zip de quelques Ko :
  </p>
  <CodeBlock :code="tree" title="Contenu du starter" />

  <div class="callout">
    <strong>Partager une configuration.</strong> L'adresse du générateur décrit vos choix : le bouton
    « Copier le lien de cette configuration » la copie, pour l'envoyer à un collègue ou la garder en favori.
  </div>

  <h2>2. Décompresser et lancer</h2>
  <OsTabs :tabs="up" />

  <h2>Ce qui se passe au premier lancement</h2>
  <ol>
    <li><code>bin/up</code> télécharge l'image Gabarit (<code>ghcr.io/ozakdigital/gabarit</code>) ;</li>
    <li>l'image exécute <code>gabarit apply</code> : installation du framework et du front avec leurs installeurs officiels, configuration, marque ;</li>
    <li>les <strong>secrets sont générés sur votre machine</strong>, puis un dépôt git est initialisé ;</li>
    <li><code>bin/up</code> démarre les conteneurs.</li>
  </ol>
  <p>Les lancements suivants démarrent simplement le projet.</p>

  <div class="callout">
    Le serveur ne lance aucun installeur et ne stocke rien : le zip est construit puis envoyé directement.
    Vous avez déjà la CLI ? Dans le dossier du starter, <code>gabarit apply</code> fait la même chose.
  </div>
</template>
