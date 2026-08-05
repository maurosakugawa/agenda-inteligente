<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$requestPath = parse_url(
    (string) ($_SERVER['REQUEST_URI'] ?? '/'),
    PHP_URL_PATH
);
$requestPath = is_string($requestPath) ? rtrim($requestPath, '/') : '/';
$requestPath = $requestPath === '' ? '/' : $requestPath;

if ($requestMethod === 'GET' && in_array($requestPath, ['/health', '/api/health'], true)) {
    http_response_code(200);

    echo json_encode(
        [
            'success' => true,
            'data' => [
                'service' => 'agenda-inteligente-api',
                'status' => 'ok',
                'timestamp' => gmdate(DATE_ATOM),
            ],
        ],
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

http_response_code(404);

echo json_encode(
    [
        'success' => false,
        'error' => [
            'code' => 'route_not_found',
            'message' => 'Rota não encontrada.',
        ],
    ],
    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
