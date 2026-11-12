<script setup lang="ts">
import CodeBlock from '../components/CodeBlock.vue'
import OsTabs from '../components/OsTabs.vue'

const install = [
  { label: 'Linux / macOS', code: 'curl -fsSL https://raw.githubusercontent.com/ozakdigital/gabarit/main/install.sh | sh' },
  { label: 'Windows (PowerShell)', code: 'irm https://raw.githubusercontent.com/ozakdigital/gabarit/main/install.ps1 | iex' },
]

const uninstall = [
  { label: 'Linux / macOS', code: 'rm -rf ~/.gabarit ~/.local/bin/gabarit\ndocker image rm gabarit-cli' },
  { label: 'Windows (PowerShell)', code: 'Remove-Item -Recurse -Force $HOME\\.gabarit\ndocker image rm gabarit-cli\n# puis retirez .gabarit\\bin de la variable PATH de votre utilisateur' },
]
</script>

<template>
  <h2>Prérequis</h2>
  <table>
    <thead><tr><th>Outil</th><th>Pourquoi</th></tr></thead>
    <tbody>
      <tr><td><strong>Docker</strong> (Docker Desktop sous Windows et macOS, Docker Engine sous Linux)</td><td>la CLI tourne dans un conteneur, comme les projets générés</td></tr>
      <tr><td><strong>git</strong></td><td>télécharger et mettre à jour Gabarit</td></tr>
    </tbody>
  </table>
  <p>Aucun PHP, Composer ni Node n'est nécessaire : l'image <code>gabarit-cli</code> contient PHP 8.3, Composer, Node, git et Gabarit.</p>

  <h2>Installer</h2>
  <OsTabs :tabs="install" />
  <p>L'installeur :</p>
  <ol>
    <li>vérifie que Docker et git sont présents et que Docker répond ;</li>
    <li>télécharge Gabarit dans <code>~/.gabarit</code> (<code>%USERPROFILE%\.gabarit</code> sous Windows), ou le met à jour ;</li>
    <li>construit l'image Docker <code>gabarit-cli</code> (quelques minutes la première fois) ;</li>
    <li>installe la commande <code>gabarit</code> : dans <code>~/.local/bin</code> (Linux, macOS) ou dans le PATH de l'utilisateur (Windows).</li>
  </ol>

  <h2>Vérifier</h2>
  <p>Dans un <strong>nouveau terminal</strong> :</p>
  <CodeBlock code="gabarit --version
gabarit doctor" />
  <div class="callout">
    Sous Linux et macOS, si <code>~/.local/bin</code> n'est pas dans votre PATH, l'installeur affiche la ligne
    à ajouter à <code>~/.bashrc</code> ou <code>~/.zshrc</code>. La commande fonctionne aussi dans Git Bash sous Windows.
  </div>

  <h2>Mettre à jour</h2>
  <CodeBlock code="gabarit self-update" />
  <p>Récupère la dernière version de Gabarit et reconstruit l'image.</p>

  <h2>Désinstaller</h2>
  <OsTabs :tabs="uninstall" />
</template>
