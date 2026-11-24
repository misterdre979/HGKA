<?php

return [
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
    'token_hours' => (int) env('TOKEN_LIFETIME_HOURS', 12),
    'backup_keep' => (int) env('BACKUP_KEEP', 14),

    'paystack' => [
        'secret' => env('PAYSTACK_SECRET_KEY'),
        'public' => env('PAYSTACK_PUBLIC_KEY'),
        'base_url' => 'https://api.paystack.co',
    ],

    'flutterwave' => [
        'secret' => env('FLUTTERWAVE_SECRET_KEY'),
        'public' => env('FLUTTERWAVE_PUBLIC_KEY'),
        'webhook_hash' => env('FLUTTERWAVE_WEBHOOK_HASH'),
        'base_url' => 'https://api.flutterwave.com/v3',
    ],

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'), // log | termii
        'api_key' => env('SMS_API_KEY'),
        'sender' => env('SMS_SENDER', 'HGKA'),
        'base_url' => env('SMS_BASE_URL', 'https://api.ng.termii.com/api'),
    ],
];
