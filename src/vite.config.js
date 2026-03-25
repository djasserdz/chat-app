import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

const port = 5173;

export default defineConfig(({ mode }) => {
    // Read Vite-related env vars from `src/.env`.
    // This is important because `process.env.*` may not include them inside
    // the docker container, but Vite will.
    const env = loadEnv(mode, process.cwd(), '');

    // Hostname/IP that your browser uses to reach this dev server.
    // Must NOT be `0.0.0.0` (Firefox blocks it).
    // Fallback order:
    // 1) VITE_HMR_HOST (recommended; set to the hostname you browse to)
    // 2) APP_URL hostname (if APP_URL uses a stable domain, this keeps it working)
    // 3) localhost
    let viteHmrHost = (env.VITE_HMR_HOST || '').trim() || '';
    if (!viteHmrHost) {
        try {
            viteHmrHost = new URL(env.APP_URL || '').hostname;
        } catch {
            viteHmrHost = '';
        }
    }
    if (!viteHmrHost) viteHmrHost = 'localhost';
    const clientPort = parseInt(env.VITE_HMR_CLIENT_PORT || '80', 10);
    const viteOrigin = viteHmrHost ? `http://${viteHmrHost}:${clientPort}` : null;

    return {
        plugins: [
            laravel({
                input: ['resources/js/app.js'],
                refresh: true,
            }),
            vue(),
        ],
        server: {
            host: '0.0.0.0',
            port,
            strictPort: true,
            cors: true,
            hmr: {
                ...(viteHmrHost ? { host: viteHmrHost } : {}),
                protocol: 'ws',
                // Only tell the browser which port to use for HMR. Vite itself still listens on `server.port` (=5173).
                clientPort,
                // Use a dedicated websocket path so Nginx can proxy it cleanly.
                path: '/@vite/hmr',
            },
            ...(viteOrigin ? { origin: viteOrigin } : {}),
        },
    };
});