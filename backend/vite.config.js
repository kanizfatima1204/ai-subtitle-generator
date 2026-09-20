import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import path from 'path';

export default defineConfig({
    server: {
        host: 'localhost',
        port: 5173,
        strictPort: true,
    },

    preview: {
        host: 'localhost',
        port: 4173,
        strictPort: true,
    },

    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),

        vue(),
        tailwindcss(),
    ],

    resolve: {
        alias: {
            /*
             * @  → resources/js
             * Allows: import X from '@/services/api'
             *         import X from '@/utils/subtitleQuality'
             *         import X from '@/Components/Foo.vue'
             */
            '@': path.resolve(__dirname, 'resources/js'),

            /*
             * @/lib/api compatibility shim.
             * SubtitleWorkspace.vue uses `@/lib/api` but the real
             * file lives at `resources/js/services/api.js`.
             * This alias resolves it without touching the component.
             */
            '@/lib/api': path.resolve(
                __dirname,
                'resources/js/services/api.js'
            ),
        },
    },
});
