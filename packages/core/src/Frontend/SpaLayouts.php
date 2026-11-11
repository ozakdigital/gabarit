<?php

declare(strict_types=1);

namespace Gabarit\Core\Frontend;

use Gabarit\Core\Model\Frontend;

/**
 * Profils des fronts SPA, relevés sur les squelettes officiels (create-vite 9.3.4, Angular CLI 22.2.6).
 * Toute mise à jour de version dans la matrice impose de revérifier ces chemins
 * (fixtures : packages/core/tests/Fixtures/skeletons/frontend).
 */
final class SpaLayouts
{
    private const array VITE_DEV = ['sh', '-c', 'npm install && npm run dev -- --host 0.0.0.0 --port 5173'];

    public static function for(Frontend $frontend): ?SpaLayout
    {
        return match ($frontend) {
            Frontend::Vue => new SpaLayout(
                renders: ['frontend/vue/App.vue.twig' => 'src/App.vue', 'frontend/vite.config.ts.twig' => 'vite.config.ts'],
                remove: ['src/components/HelloWorld.vue'],
                styleFile: 'src/main.ts',
                styleImport: "import './brand.css'",
                styleAfter: "/^import '\\.\\/style\\.css'$/m",
                indexHtml: 'index.html',
                devCommand: self::VITE_DEV,
            ),
            Frontend::React => new SpaLayout(
                renders: ['frontend/react/App.tsx.twig' => 'src/App.tsx', 'frontend/vite.config.ts.twig' => 'vite.config.ts'],
                remove: ['src/App.css'],
                styleFile: 'src/main.tsx',
                styleImport: "import './brand.css'",
                styleAfter: "/^import '\\.\\/index\\.css'$/m",
                indexHtml: 'index.html',
                devCommand: self::VITE_DEV,
            ),
            Frontend::Svelte => new SpaLayout(
                renders: ['frontend/svelte/App.svelte.twig' => 'src/App.svelte', 'frontend/vite.config.ts.twig' => 'vite.config.ts'],
                remove: ['src/lib/Counter.svelte'],
                styleFile: 'src/main.ts',
                styleImport: "import './brand.css'",
                styleAfter: "/^import '\\.\\/app\\.css'$/m",
                indexHtml: 'index.html',
                devCommand: self::VITE_DEV,
            ),
            Frontend::Solid => new SpaLayout(
                renders: ['frontend/solid/App.tsx.twig' => 'src/App.tsx', 'frontend/vite.config.ts.twig' => 'vite.config.ts'],
                remove: ['src/App.css'],
                styleFile: 'src/index.tsx',
                styleImport: "import './brand.css'",
                styleAfter: "/^import '\\.\\/index\\.css'$/m",
                indexHtml: 'index.html',
                devCommand: self::VITE_DEV,
            ),
            Frontend::Preact => new SpaLayout(
                renders: ['frontend/preact/app.tsx.twig' => 'src/app.tsx', 'frontend/vite.config.ts.twig' => 'vite.config.ts'],
                remove: ['src/app.css'],
                styleFile: 'src/main.tsx',
                styleImport: "import './brand.css'",
                styleAfter: "/^import '\\.\\/index\\.css'$/m",
                indexHtml: 'index.html',
                devCommand: self::VITE_DEV,
            ),
            Frontend::Angular => new SpaLayout(
                renders: [
                    'frontend/angular/app.ts.twig' => 'src/app/app.ts',
                    'frontend/angular/app.html.twig' => 'src/app/app.html',
                    'frontend/angular/app.spec.ts.twig' => 'src/app/app.spec.ts',
                    'frontend/angular/proxy.conf.json.twig' => 'proxy.conf.json',
                ],
                remove: ['src/app/app.css'],
                styleFile: 'src/styles.css',
                styleImport: "@import './brand.css';",
                styleAfter: null,
                indexHtml: 'src/index.html',
                devCommand: ['sh', '-c', 'npm install && npx ng serve --host 0.0.0.0 --port 5173 --proxy-config proxy.conf.json'],
            ),
            default => null,
        };
    }

    /**
     * Plugin Vite de chaque framework (import + appel), tel qu'écrit par create-vite.
     *
     * @return array{import: string, call: string}|null
     */
    public static function vitePlugin(Frontend $frontend): ?array
    {
        return match ($frontend) {
            Frontend::Vue => ['import' => "import vue from '@vitejs/plugin-vue'", 'call' => 'vue()'],
            Frontend::React => ['import' => "import react from '@vitejs/plugin-react'", 'call' => 'react()'],
            Frontend::Svelte => ['import' => "import { svelte } from '@sveltejs/vite-plugin-svelte'", 'call' => 'svelte()'],
            Frontend::Solid => ['import' => "import solid from 'vite-plugin-solid'", 'call' => 'solid()'],
            Frontend::Preact => ['import' => "import preact from '@preact/preset-vite'", 'call' => 'preact()'],
            default => null,
        };
    }
}
