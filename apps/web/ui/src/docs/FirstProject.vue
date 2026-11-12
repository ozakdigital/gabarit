<script setup lang="ts">
import CodeBlock from '../components/CodeBlock.vue'
import { link } from '../router'

const interview = `Framework            › Symfony / Laravel / CodeIgniter / Slim
Version de PHP       › 8.3 / 8.4 …          (seulement les versions compatibles)
Base de données      › PostgreSQL / MySQL / MariaDB / SQLite
Front                › Twig / Aucun / Vue 3 / React …
Services annexes     › Redis, Mailpit       (cases à cocher)
Nom d'affichage      › Mon App
Namespace PHP        › App`

const scripted = `gabarit new boutique \\
    --framework=laravel --fw-version=13 --php=8.3 \\
    --db=mariadb --frontend=blade --services=redis,mailpit \\
    --brand-name="Boutique Démo" --namespace='Acme\\Shop' \\
    --primary-color="#e11d48" --no-interaction`
</script>

<template>
  <p class="lead">Placez-vous dans le dossier où vous rangez vos projets : le projet est créé dans un sous-dossier.</p>

  <h2>Mode interactif</h2>
  <CodeBlock code="gabarit new mon-app" />
  <p>Les menus sont construits depuis la matrice : seules les options compatibles avec vos choix précédents sont proposées.</p>
  <CodeBlock :code="interview" title="Questions posées" />

  <h2>En une seule commande</h2>
  <p>Pour un script ou la CI, passez toutes les réponses en options :</p>
  <CodeBlock :code="scripted" />
  <p>Toutes les options sont détaillées dans <a :href="link('/docs/commandes')">Commandes CLI</a>.</p>

  <h2>Démarrer le projet</h2>
  <CodeBlock code="cd boutique
make up" />
  <table>
    <thead><tr><th>Adresse</th><th>Contenu</th></tr></thead>
    <tbody>
      <tr><td><code>http://localhost:8080</code></td><td>l'application PHP</td></tr>
      <tr><td><code>http://localhost:8080/api/health</code></td><td>le test de santé (inclut la base)</td></tr>
      <tr><td><code>http://localhost:5173</code></td><td>le front SPA, avec rechargement à chaud</td></tr>
      <tr><td><code>http://localhost:8025</code></td><td>Mailpit (e-mails capturés)</td></tr>
    </tbody>
  </table>
  <p>Changez les ports avec <code>HTTP_PORT</code>, <code>FRONT_PORT</code> et <code>MAILPIT_PORT</code>, par exemple <code>HTTP_PORT=9000 make up</code>.</p>
</template>
