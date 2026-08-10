<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'banxico' => [
        'token' => env('BANXICO_TOKEN'),
    ],

    'fesa' => [
        'modo'             => env('FESA_MODO', 'PR'),
        'usuario'          => env('FESA_USUARIO'),
        'contrasena'       => env('FESA_CONTRASENA'),
        'tipo_comprobante' => env('FESA_TIPO_COMPROBANTE', 'Factura'),
        'nombre_sucursal'  => env('FESA_NOMBRE_SUCURSAL', 'MATRIZ'),
    ],

    /*
     * Usuario a cuyo nombre se registran las NC del programa forecast: siempre las genera
     * el mismo requester, aunque quien dispare la generación sea un forecast admin. Los
     * pasos del workflow con rol REQUESTER se asignan al creador de la solicitud.
     */
    'forecast' => [
        'credit_note_requester_id' => env('FORECAST_CREDIT_NOTE_REQUESTER_ID', 36),
    ],

    'deploy_token' => env('DEPLOY_TOKEN'),

];