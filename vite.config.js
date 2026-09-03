import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 
                    'resources/js/app.js',
                    'resources/js/admin.js',
                    'resources/js/dias_especiales.js',
                    'resources/js/grupos.js',
                    'resources/js/layout.js',
                    'resources/js/maintenance.js',
                    'resources/js/personal.js',
                    'resources/js/session.js',
                    'resources/js/sup_user.js',
                    'resources/js/tiposSolicitud.js',
                    'resources/js/users.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: 'localhost',
        port: 5173,
        strictPort: true,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});