#!/bin/bash

# Start PHP-FPM in the background
php-fpm &

# Start Vite dev server
npm run dev -- --host 0.0.0.0