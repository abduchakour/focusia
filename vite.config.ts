import vue from '@vitejs/plugin-vue';
import autoprefixer from 'autoprefixer';
import laravel from 'laravel-vite-plugin';
import path from 'path';
import tailwindcss from 'tailwindcss';
import { defineConfig } from 'vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        // Offline mode (phase 4): service worker written to public/sw.js so its scope is the whole site.
        // The web manifest is a static file (public/manifest.webmanifest).
        VitePWA({
            strategies: 'injectManifest',
            srcDir: 'resources/js',
            filename: 'sw.ts',
            outDir: 'public',
            base: '/',
            buildBase: '/',
            scope: '/',
            injectRegister: false,
            manifest: false,
            injectManifest: {
                globDirectory: 'public',
                globPatterns: ['build/assets/**/*.{js,css,svg,png,woff2}', 'icons/*.{png,svg}'],
                maximumFileSizeToCacheInBytes: 3 * 1024 * 1024,
            },
            devOptions: { enabled: false },
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
        },
    },
    css: {
        postcss: {
            plugins: [tailwindcss, autoprefixer],
        },
    },
});
