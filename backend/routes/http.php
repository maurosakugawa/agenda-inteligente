<?php

declare(strict_types=1);

use AgendaInteligente\Application\Health\HealthController;
use AgendaInteligente\Infrastructure\Http\JsonResponse;
use AgendaInteligente\Infrastructure\Http\Request;
use AgendaInteligente\Infrastructure\Http\Router;

return static function (
    HealthController $healthController
): Router {
    $router = new Router();

    $healthHandler = static function (
        Request $request
    ) use (
        $healthController
    ): JsonResponse {
        return $healthController->handle();
    };

    $router->get(
        '/health',
        $healthHandler
    );
    $router->get(
        '/api/health',
        $healthHandler
    );

    return $router;
};
