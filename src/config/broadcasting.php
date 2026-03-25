<?php

return [
    'default' => env('BROADCAST_DRIVER', 'pusher'),

    'connections' => [

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'host' => env('PUSHER_HOST', 'soketi'),
                'port' => env('PUSHER_PORT', 6001),
                'scheme' => 'http',   // ✅ NOT 'ws' — Laravel uses HTTP API to push to Soketi
                'encrypted' => false,
                'useTLS' => false,
            ],
            'client_options' => [],
        ],

        'log' => ['driver' => 'log'],
        'null' => ['driver' => 'null'],
    ],
];
