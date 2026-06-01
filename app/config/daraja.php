<?php

return [
    /*
    |--------------------------------------------------------------------------
    | M-Pesa Daraja 3.0 Environment Settings
    |--------------------------------------------------------------------------
    | Sandbox uses Safaricom's developer credentials, while 'production'
    | targets real-world production gateways.
    */
    'env' => env('MPESA_ENV', 'sandbox'),

    'consumer_key' => env('MPESA_CONSUMER_KEY'),
    'consumer_secret' => env('MPESA_CONSUMER_SECRET'),

    // Safaricom standard sandbox Paybill is 174379
    'shortcode' => env('MPESA_SHORTCODE', '174379'),

    // Sandbox Passkey is publicly documented by Safaricom:
    'passkey' => env('MPESA_PASSKEY', 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919'),

    'callback_url' => env('MPESA_CALLBACK_BASE_URL', 'https://your-ngrok-domain.ngrok-free.app').'/api/mpesa/callback',
];
