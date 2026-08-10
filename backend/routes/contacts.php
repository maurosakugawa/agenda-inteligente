<?php

declare(strict_types=1);

use AgendaInteligente\Infrastructure\Http\Middleware\AuthenticationMiddleware;
use AgendaInteligente\Infrastructure\Http\Middleware\CsrfMiddleware;
use AgendaInteligente\Infrastructure\Http\Middleware\SessionMiddleware;
use AgendaInteligente\Infrastructure\Http\Router;

/**
 * @param array{
 *     list:callable,
 *     create:callable,
 *     update:callable,
 *     delete:callable
 * } $handlers
 */
return static function (
    Router $router,
    SessionMiddleware $sessionMiddleware,
    AuthenticationMiddleware $authenticationMiddleware,
    CsrfMiddleware $csrfMiddleware,
    array $handlers
): Router {
    foreach (
        [
            'list',
            'create',
            'update',
            'delete',
        ] as $name
    ) {
        if (
            !array_key_exists(
                $name,
                $handlers
            )
            || !is_callable(
                $handlers[$name]
            )
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'Handler de contatos "%s" é obrigatório.',
                    $name
                )
            );
        }
    }

    $authenticatedMiddlewares = [
        $sessionMiddleware,
        $authenticationMiddleware,
    ];

    $protectedMiddlewares = [
        $sessionMiddleware,
        $authenticationMiddleware,
        $csrfMiddleware,
    ];

    $router->get(
        '/api/contacts',
        $handlers['list'],
        $authenticatedMiddlewares
    );

    $router->add(
        'POST',
        '/api/contacts',
        $handlers['create'],
        $protectedMiddlewares
    );

    $router->add(
        'PUT',
        '/api/contacts/{id}',
        $handlers['update'],
        $protectedMiddlewares
    );

    $router->add(
        'DELETE',
        '/api/contacts/{id}',
        $handlers['delete'],
        $protectedMiddlewares
    );

    return $router;
};
