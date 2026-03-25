import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: process.env.MIX_REVERB_APP_KEY || 'local',
    // If MIX_REVERB_HOST isn't set, default to the current browser hostname.
    wsHost: process.env.MIX_REVERB_HOST || window.location.hostname,
    wsPort: parseInt(process.env.MIX_REVERB_PORT || '6001', 10),
    wssPort: parseInt(process.env.MIX_REVERB_PORT || '6001', 10),
    forceTLS: false,
    encrypted: false,
    disableStats: true,
    enabledTransports: ['ws'],
    cluster: 'mt1',
});

export const echoReady = new Promise((resolve) => {
    const conn = window.Echo.connector.pusher.connection;

    const finish = () => resolve(window.Echo);

    if (conn.state === 'connected') {
        finish();
        return;
    }

    conn.bind('connected', () => {
        console.log('✅ Connected to Soketi');
        finish();
    });

    conn.bind('failed', () => {
        console.error('⚠️ WebSocket connection failed (check host/port and Soketi)');
        finish();
    });

    conn.bind('unavailable', () => {
        console.warn('⚠️ WebSocket unavailable');
        finish();
    });

    conn.bind('disconnected', () => console.log('❌ Disconnected from Soketi'));
    conn.bind('error', (err) => console.error('⚠️ Soketi error', err));
});

export default window.Echo;