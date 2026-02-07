<?php

return [
    'max_user_attempts' => env('SECURITY_MAX_USER_ATTEMPTS', 5),
    'max_ip_attempts' => env('SECURITY_MAX_IP_ATTEMPTS', 10),
    'user_lock_minutes' => env('SECURITY_USER_LOCK_MINUTES', 60 * 24),
    'jwt_ttl_minutes' => env('JWT_TTL_MINUTES', 120),
    'jwt_issuer' => env('JWT_ISSUER', 'backend-notasCredito'),
    'jwt_secret' => env('JWT_SECRET', env('APP_KEY')),
];
