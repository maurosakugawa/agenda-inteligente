<?php

declare(strict_types=1);

use AgendaInteligente\Application\Auth\CsrfController;
use AgendaInteligente\Application\Health\HealthController;
use AgendaInteligente\Infrastructure\Http\JsonResponse;
use AgendaInteligente\Infrastructure\Http\Middleware\CsrfMiddleware;
use AgendaInteligente\Infrastructure\Http\Middleware\SessionMiddleware;
use AgendaInteligente\Infrastructure\Http\Request;
use AgendaInteligente\Infrastructure\Http\Router;

return static function (
    HealthController $healthController,
    CsrfController $csrfController,
    SessionMiddleware $sessionMiddleware,
    CsrfMiddleware $csrfMiddleware,
    callable $registerHandler,
    callable $loginHandler,
    callable $logoutHandler
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

    $router->get(
        '/auth/csrf',
        static function (
            Request $request
        ) use (
            $csrfController
        ): JsonResponse {
            return $csrfController->handle();
        },
        [
            $sessionMiddleware,
        ]
    );

    $router->add(
        'POST',
        '/auth/register',
        $registerHandler,
        [
            $sessionMiddleware,
            $csrfMiddleware,
        ]
    );

    $router->add(
        'POST',
        '/auth/login',
        $loginHandler,
        [
            $sessionMiddleware,
            $csrfMiddleware,
        ]
    );

    $router->add(
        'POST',
        '/auth/logout',
        $logoutHandler,
        [
            $sessionMiddleware,
            $csrfMiddleware,
        ]
    );

    return $router;
};
