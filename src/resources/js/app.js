import '../css/app.css';
import './bootstrap.js';
import './echo.js';

import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy/dist/index.esm.js';

const appName = process.env.MIX_APP_NAME || 'Laravel';

// Resolve Inertia pages without using `require.context`.
// Webpack sometimes leaves `require.context` untouched depending on module type.
// Inertia passes component names like `Welcome` or `Auth/Login`.
import Welcome from './Pages/Welcome.vue';
import Dashboard from './Pages/Dashboard.vue';
import Conversations from './Pages/Conversations.vue';
import ChatMessages from './Pages/ChatMessages.vue';

import AuthLogin from './Pages/Auth/Login.vue';
import AuthRegister from './Pages/Auth/Register.vue';
import AuthForgotPassword from './Pages/Auth/ForgotPassword.vue';
import AuthResetPassword from './Pages/Auth/ResetPassword.vue';
import AuthConfirmPassword from './Pages/Auth/ConfirmPassword.vue';
import AuthVerifyEmail from './Pages/Auth/VerifyEmail.vue';

import ProfileEdit from './Pages/Profile/Edit.vue';

const pages = {
    Welcome,
    Dashboard,
    Conversations,
    ChatMessages,
    'Auth/Login': AuthLogin,
    'Auth/Register': AuthRegister,
    'Auth/ForgotPassword': AuthForgotPassword,
    'Auth/ResetPassword': AuthResetPassword,
    'Auth/ConfirmPassword': AuthConfirmPassword,
    'Auth/VerifyEmail': AuthVerifyEmail,
    'Profile/Edit': ProfileEdit,
};

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => {
        const component = pages[name];
        if (!component) {
            throw new Error(`Unknown Inertia page component: ${name}`);
        }
        return component;
    },
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
