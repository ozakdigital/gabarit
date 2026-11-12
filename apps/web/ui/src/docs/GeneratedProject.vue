<script setup lang="ts">
import CodeBlock from '../components/CodeBlock.vue'

const tree = `boutique/
├── app/ bootstrap/ config/ …      ← le squelette officiel du framework
├── frontend/                      ← l'application SPA (si choisie)
├── docker/
│   ├── nginx/default.conf
│   └── php/app.ini, fpm.conf, entrypoint.sh
├── Dockerfile                     ← PHP-FPM + extensions utiles
├── compose.yaml                   ← app, nginx, database, node, redis…
├── Makefile                       ← make up / down / logs / sh / test
├── README.md                      ← documentation du projet, à sa marque
├── .github/workflows/ci.yml       ← CI adaptée au framework et au front
└── .env                           ← secrets (non versionné)`

const health = '{"status": "ok", "app": "Boutique Démo", "database": "ok"}'
</script>

<template>
  <h2>Structure</h2>
  <p>Exemple pour Laravel + MariaDB + Vue + Redis :</p>
  <CodeBlock :code="tree" />

  <h2>Commandes du projet</h2>
  <table>
    <tbody>
      <tr><td><code>make up</code></td><td>construit l'image, démarre les conteneurs, installe les dépendances et lance les migrations si besoin</td></tr>
      <tr><td><code>make down</code></td><td>arrête les conteneurs</td></tr>
      <tr><td><code>make logs</code></td><td>affiche les journaux</td></tr>
      <tr><td><code>make sh</code></td><td>shell dans le conteneur PHP</td></tr>
      <tr><td><code>make test</code></td><td>vérifie que l'application démarre et répond</td></tr>
      <tr><td><code>make help</code></td><td>liste toutes les commandes</td></tr>
    </tbody>
  </table>

  <h2>Les secrets</h2>
  <table>
    <thead><tr><th>Framework</th><th>Fichier des secrets (jamais versionné)</th></tr></thead>
    <tbody>
      <tr><td>Symfony</td><td><code>.env.local</code></td></tr>
      <tr><td>Laravel, CodeIgniter, Slim</td><td><code>.env</code></td></tr>
    </tbody>
  </table>
  <p>
    Ce fichier contient notamment <code>DB_PASSWORD</code>, généré aléatoirement. Il est ignoré par git :
    sur un nouveau poste, recréez-le (le README du projet liste les variables attendues).
  </p>

  <h2>Le test de santé</h2>
  <p>Chaque projet expose <code>GET /api/health</code>, qui interroge aussi la base :</p>
  <CodeBlock :code="health" />
  <p>Il renvoie <code>503</code> et <code>"database": "error"</code> si la base est injoignable.</p>
</template>
