<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Http\Middleware;

use AgendaInteligente\Infrastructure\Http\JsonResponse;
use AgendaInteligente\Infrastructure\Http\MiddlewareInterface;
use AgendaInteligente\Infrastructure\Http\Request;
use AgendaInteligente\Infrastructure\Http\RequestHandlerInterface;
use AgendaInteligente\Infrastructure\Session\SessionManager;

final class SessionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private SessionManager $session
    ) {
    }

    public function process(
        Request $request,
        RequestHandlerInterface $next
    ): JsonResponse {
        $this->session->start();

        try {
            return $next->handle(
                $request
            );
        } finally {
            $this->session->close();
        }
    }
}
