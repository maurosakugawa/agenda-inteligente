<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Http;

interface MiddlewareInterface
{
    public function process(
        Request $request,
        RequestHandlerInterface $next
    ): JsonResponse;
}
