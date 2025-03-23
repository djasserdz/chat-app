import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.js'],
            refresh: true,
        }),
        vue(),
    ],
    server: {
        host: '0.0.0.0', // Allows access from any device in the network
        port: 5173, // Ensure this matches the port you're using
        strictPort: true, // Ensures Vite does not switch ports
        hmr: {
            host: '192.168.100.9', // Replace with your actual IP
        },
    },
});
