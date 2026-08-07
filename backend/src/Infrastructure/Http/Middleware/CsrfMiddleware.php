<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Http\Middleware;

use AgendaInteligente\Infrastructure\Http\JsonResponse;
use AgendaInteligente\Infrastructure\Http\MiddlewareInterface;
use AgendaInteligente\Infrastructure\Http\Request;
use AgendaInteligente\Infrastructure\Http\RequestHandlerInterface;
use AgendaInteligente\Infrastructure\Security\CsrfTokenManager;

final class CsrfMiddleware implements MiddlewareInterface
{
    private const PROTECTED_METHODS = [
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
    ];

    public function __construct(
        private CsrfTokenManager $csrf
    ) {
    }

    public function process(
        Request $request,
        RequestHandlerInterface $next
    ): JsonResponse {
        if (
            !in_array(
                $request->method(),
                self::PROTECTED_METHODS,
                true
            )
        ) {
            return $next->handle(
                $request
            );
        }

        $providedToken =
            $request->header(
                'X-CSRF-Token'
            );

        if (
            !$this->csrf->validate(
                $providedToken
            )
        ) {
            return JsonResponse::error(
                'csrf_invalid',
                'Token CSRF ausente ou inválido.',
                403
            );
        }

        return $next->handle(
            $request
        );
    }
}
