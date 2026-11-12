// Sommaire de la documentation : l'ordre ici est l'ordre du menu et des liens précédent / suivant.
import type { Component } from 'vue'
import GetStarted from './GetStarted.vue'
import Installation from './Installation.vue'
import FirstProject from './FirstProject.vue'
import CliCommands from './CliCommands.vue'
import WebStarter from './WebStarter.vue'
import GeneratedProject from './GeneratedProject.vue'
import Branding from './Branding.vue'
import MatrixTable from './MatrixTable.vue'
import HowItWorks from './HowItWorks.vue'
import Troubleshooting from './Troubleshooting.vue'

export interface DocPage {
  slug: string
  title: string
  group: string
  component: Component
}

export const docs: DocPage[] = [
  { slug: 'demarrage', title: 'Démarrage rapide', group: 'Prise en main', component: GetStarted },
  { slug: 'installation', title: 'Installation', group: 'Prise en main', component: Installation },
  { slug: 'premier-projet', title: 'Premier projet', group: 'Prise en main', component: FirstProject },
  { slug: 'commandes', title: 'Commandes CLI', group: 'Utilisation', component: CliCommands },
  { slug: 'starter', title: 'Starter web', group: 'Utilisation', component: WebStarter },
  { slug: 'projet-genere', title: 'Projet généré', group: 'Utilisation', component: GeneratedProject },
  { slug: 'marque', title: 'Marque blanche', group: 'Utilisation', component: Branding },
  { slug: 'combinaisons', title: 'Combinaisons', group: 'Référence', component: MatrixTable },
  { slug: 'fonctionnement', title: 'Fonctionnement', group: 'Référence', component: HowItWorks },
  { slug: 'depannage', title: 'Dépannage', group: 'Référence', component: Troubleshooting },
]
