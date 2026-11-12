import type { Matrix } from '../../src/api'

// Extrait de la matrice réelle, au format de GET /api/matrix.
export const matrixFixture: Matrix = {
  frameworks: {
    symfony: {
      label: 'Symfony',
      versions: { '7.4': { php: ['8.2', '8.3', '8.4'] } },
      databases: ['postgresql', 'mysql', 'mariadb', 'sqlite'],
      frontends: ['twig', 'none', 'vue', 'react'],
      customNamespace: true,
    },
    laravel: {
      label: 'Laravel',
      versions: { '13': { php: ['8.3', '8.4'] } },
      databases: ['postgresql', 'mysql', 'mariadb', 'sqlite'],
      frontends: ['blade', 'none', 'vue', 'react'],
      customNamespace: true,
    },
    codeigniter: {
      label: 'CodeIgniter',
      versions: { '4.7': { php: ['8.2', '8.3', '8.4'] } },
      databases: ['postgresql', 'mysql', 'sqlite'],
      frontends: ['none', 'vue'],
      customNamespace: false,
    },
  },
  databases: {
    postgresql: { label: 'PostgreSQL 17', server: true },
    mysql: { label: 'MySQL 8.4', server: true },
    mariadb: { label: 'MariaDB 11.8', server: true },
    sqlite: { label: 'SQLite (fichier local)', server: false },
  },
  frontends: {
    twig: { label: 'Twig', spa: false },
    blade: { label: 'Blade', spa: false },
    none: { label: 'Aucun (API seule)', spa: false },
    vue: { label: 'Vue 3 + Vite + TypeScript', spa: true },
    react: { label: 'React 19 + Vite + TypeScript', spa: true },
  },
  services: {
    redis: { label: 'Redis' },
    mailpit: { label: 'Mailpit' },
  },
}
