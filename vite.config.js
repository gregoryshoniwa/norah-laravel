import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
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
        tailwindcss(),
    ],
    /**
     * Optimization configuration
     *
     * We exclude 'dompdf' from Vite's dependency optimization because:
     * 1. DomPDF is a PHP library used on the server-side
     * 2. Vite shouldn't try to bundle or process it as JavaScript
     * 3. This prevents errors during development and build
     */
    // optimizeDeps: {
    //     exclude: ['dompdf']
    // }
});
