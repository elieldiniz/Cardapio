import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/feed.css', 'resources/js/feed.js'],
            refresh: true,
            fonts: [
                bunny('DM Sans', {
                    weights: [400, 500, 600, 700],
                }),
                bunny('DM Serif Display', {
                    weights: [400],
                }),
                bunny('Imperial Script', {
                    weights: [400],
                }),
                bunny('Carter One', {
                    weights: [400],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
