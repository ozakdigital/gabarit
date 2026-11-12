<template>
  <table>
    <thead><tr><th>Commande</th><th>Rôle</th></tr></thead>
    <tbody>
      <tr><td><code>gabarit new &lt;nom&gt;</code></td><td>génère un projet (interactif ou avec options)</td></tr>
      <tr><td><code>gabarit apply</code></td><td>transforme un starter téléchargé (<code>gabarit.json</code>) en projet complet</td></tr>
      <tr><td><code>gabarit matrix</code></td><td>affiche les combinaisons disponibles (<code>--json</code> pour une machine)</td></tr>
      <tr><td><code>gabarit doctor</code></td><td>vérifie les outils nécessaires</td></tr>
      <tr><td><code>gabarit self-update</code></td><td>met Gabarit à jour et reconstruit l'image</td></tr>
      <tr><td><code>gabarit help &lt;commande&gt;</code></td><td>aide détaillée d'une commande</td></tr>
    </tbody>
  </table>

  <h2>Options de <code>gabarit new</code></h2>
  <table>
    <thead><tr><th>Option</th><th>Valeurs</th><th>Défaut sans interaction</th></tr></thead>
    <tbody>
      <tr><td><code>&lt;nom&gt;</code></td><td>slug <code>[a-z0-9-]</code>, 2 à 40 caractères</td><td>obligatoire</td></tr>
      <tr><td><code>--framework</code></td><td><code>symfony</code>, <code>laravel</code>, <code>codeigniter</code>, <code>slim</code></td><td><code>symfony</code></td></tr>
      <tr><td><code>--fw-version</code></td><td>selon la matrice</td><td>la version de la matrice</td></tr>
      <tr><td><code>--php</code></td><td>selon le framework</td><td><code>8.3</code></td></tr>
      <tr><td><code>--db</code></td><td><code>postgresql</code>, <code>mysql</code>, <code>mariadb</code>, <code>sqlite</code></td><td><code>postgresql</code></td></tr>
      <tr><td><code>--frontend</code></td><td><code>twig</code>, <code>blade</code>, <code>none</code>, <code>vue</code>, <code>react</code>, <code>svelte</code>, <code>angular</code>, <code>solid</code>, <code>preact</code></td><td>le 1er front du framework</td></tr>
      <tr><td><code>--services</code></td><td><code>redis,mailpit</code> ou <code>none</code></td><td>aucun</td></tr>
      <tr><td><code>--brand-name</code></td><td>lettres, chiffres, espaces, <code>. , ' &amp; -</code></td><td>déduit du nom</td></tr>
      <tr><td><code>--namespace</code></td><td><code>Acme</code> ou <code>Acme\Shop</code></td><td><code>App</code></td></tr>
      <tr><td><code>--primary-color</code>, <code>--secondary-color</code></td><td><code>#rrggbb</code></td><td><code>#4f46e5</code>, <code>#0f172a</code></td></tr>
      <tr><td><code>--logo</code></td><td>PNG ou SVG sous le dossier courant (1 Mo max)</td><td>aucun</td></tr>
      <tr><td><code>--dir</code></td><td>dossier cible sous le dossier courant</td><td><code>./&lt;nom&gt;</code></td></tr>
      <tr><td><code>--no-git</code></td><td>ne pas créer de dépôt git</td><td>git activé</td></tr>
      <tr><td><code>--json</code></td><td>sortie machine</td><td>—</td></tr>
      <tr><td><code>-v</code></td><td>affiche la sortie de composer et npm</td><td>—</td></tr>
    </tbody>
  </table>

  <h2>Codes de sortie</h2>
  <table>
    <tbody>
      <tr><td><code>0</code></td><td>succès</td></tr>
      <tr><td><code>1</code></td><td>choix invalides : rien n'a été créé</td></tr>
      <tr><td><code>2</code></td><td>erreur pendant la génération (installeur en échec, dossier non vide…)</td></tr>
    </tbody>
  </table>

  <div class="callout">
    La CLI tourne dans un conteneur qui ne voit que le dossier courant : <code>--dir</code> et <code>--logo</code>
    doivent désigner un chemin <strong>sous</strong> ce dossier. Dans un script Windows sans terminal, définissez
    <code>GABARIT_NO_TTY=1</code>.
  </div>
</template>
