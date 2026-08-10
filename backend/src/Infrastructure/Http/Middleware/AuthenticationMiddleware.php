<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Http\Middleware;

use AgendaInteligente\Application\Auth\CurrentUserProvider;
use AgendaInteligente\Infrastructure\Http\JsonResponse;
use AgendaInteligente\Infrastructure\Http\MiddlewareInterface;
use AgendaInteligente\Infrastructure\Http\Request;
use AgendaInteligente\Infrastructure\Http\RequestHandlerInterface;

final class AuthenticationMiddleware
    implements MiddlewareInterface
{
    public function __construct(
        private CurrentUserProvider $currentUser
    ) {
    }

    public function process(
        Request $request,
        RequestHandlerInterface $next
    ): JsonResponse {
        $user =
            $this->currentUser->resolve();

        if ($user === null) {
            return new JsonResponse(
                [
                    'error' =>
                        'Autenticação necessária',
                ],
                401
            );
        }

        return $next->handle(
            $request->withAttribute(
                'authenticated_user_id',
                $user['id']
            )
        );
    }
}
