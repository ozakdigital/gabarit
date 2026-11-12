<template>
  <p class="lead">Chaque projet porte votre marque dès sa création : c'est le côté « marque blanche » de Gabarit.</p>

  <table>
    <thead><tr><th>Élément</th><th>Où il est appliqué</th></tr></thead>
    <tbody>
      <tr><td><strong>Nom d'affichage</strong></td><td>titre des pages, README, réponse de <code>/api/health</code>, titre du front</td></tr>
      <tr><td><strong>Namespace PHP</strong></td><td><code>composer.json</code> (autoload PSR-4) et toutes les classes de l'application</td></tr>
      <tr><td><strong>Couleurs</strong></td><td>thème CSS de la page d'accueil et du front SPA (<code>brand.css</code>)</td></tr>
      <tr><td><strong>Logo</strong></td><td>page d'accueil et front (PNG ou SVG, 1 Mo max)</td></tr>
      <tr><td><strong>Nom du projet</strong></td><td>dossier, nom Composer, nom du paquet npm, nom des conteneurs</td></tr>
    </tbody>
  </table>

  <h2>Règles</h2>
  <ul>
    <li>Le namespace s'écrit en PascalCase : <code>Acme</code> ou <code>Acme\Shop</code>.</li>
    <li>CodeIgniter impose le namespace <code>App</code> : il n'est pas modifiable pour ce framework.</li>
    <li>Le logo SVG ne doit contenir aucun script : il est refusé sinon.</li>
    <li>Les couleurs s'écrivent <code>#rrggbb</code>.</li>
  </ul>
</template>
