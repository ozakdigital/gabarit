<template>
  <table>
    <thead><tr><th>Symptôme</th><th>Cause et solution</th></tr></thead>
    <tbody>
      <tr><td><code>gabarit : commande introuvable</code></td><td>Ouvrez un nouveau terminal. Sous Linux et macOS, ajoutez <code>~/.local/bin</code> à votre PATH.</td></tr>
      <tr><td>« Docker ne répond pas »</td><td>Démarrez Docker Desktop, puis relancez <code>gabarit doctor</code>.</td></tr>
      <tr><td><code>toomanyrequests</code> pendant un build</td><td>Limite de téléchargements anonymes de Docker Hub : <code>docker login</code>, ou patientez.</td></tr>
      <tr><td>Génération très lente sous Windows</td><td>Les volumes Docker sur NTFS sont lents (composer, npm). C'est normal la première fois.</td></tr>
      <tr><td>504 à la toute première requête</td><td>Le framework construit son cache. Rechargez après quelques secondes.</td></tr>
      <tr><td>Le front SPA ne répond pas tout de suite</td><td>Le conteneur <code>node</code> installe les dépendances au premier démarrage (<code>make logs</code>).</td></tr>
      <tr><td>Fichiers appartenant à <code>root</code> (Linux)</td><td><code>sudo chown -R $USER: mon-app</code></td></tr>
      <tr><td>« Le dossier cible existe déjà et n'est pas vide »</td><td>Gabarit n'écrase jamais un dossier : choisissez un autre nom ou <code>--dir</code>.</td></tr>
      <tr><td>Un port est déjà utilisé</td><td>Changez-le : <code>HTTP_PORT=9000 make up</code> (aussi <code>FRONT_PORT</code>, <code>MAILPIT_PORT</code>).</td></tr>
      <tr><td><code>bin/up</code> : permission refusée</td><td>Le zip a perdu le droit d'exécution : <code>chmod +x bin/up</code>, ou lancez <code>sh bin/up</code>.</td></tr>
    </tbody>
  </table>
  <p>
    Toujours bloqué ? Ouvrez un ticket sur
    <a href="https://github.com/ozakdigital/gabarit/issues" target="_blank" rel="noopener">GitHub</a>
    avec la sortie de <code>gabarit doctor</code>.
  </p>
</template>
