const mix = require('laravel-mix');
require('laravel-mix-vue3');

const path = require('path');
const tailwindcss = require('tailwindcss');
const autoprefixer = require('autoprefixer');

// Compile Vue/Inertia app bundle + Tailwind CSS.
// Note: we build a single JS entry (`resources/js/app.js`) that includes
// all Inertia pages via webpack's `require.context` (implemented later).
mix.webpackConfig({
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
        extensions: ['.js', '.vue', '.json'],
    },
});

mix
    .js('resources/js/app.js', 'public/js')
    .vue({ version: 3 })
    .postCss('resources/css/app.css', 'public/css', [
        tailwindcss,
        autoprefixer,
    ]);

