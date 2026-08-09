<?php

declare(strict_types=1);

return [
    'app' => [
        'environment' => 'development',
        'debug' => true,
        'timezone' => 'America/Sao_Paulo',
        'base_url' => 'http://localhost:8000',
    ],

    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'agenda_inteligente',
        'username' => 'agenda_user',
        'password' => 'troque-esta-senha',
        'charset' => 'utf8mb4',
    ],

    'session' => [
        'name' => 'AGENDA_INTELIGENTE_SESSID',
        'secure' => false,
        'same_site' => 'Lax',
        'idle_timeout' => 1800,
        'absolute_timeout' => 28800,
    ],

    'login_rate_limit' => [
        'key_secret' =>
            'troque-este-segredo-de-rate-limit-com-32-bytes-ou-mais',

        'ip' => [
            'max_attempts' => 20,
            'window_seconds' => 300,
            'block_seconds' => 900,
        ],

        'username_ip' => [
            'max_failures' => 5,
            'window_seconds' => 900,
            'block_seconds' => 900,
        ],
    ],

    'weather' => [
        'api_key' => '',
        'base_url' => 'https://api.openweathermap.org',
        'cache_seconds' => 1800,
    ],
];
