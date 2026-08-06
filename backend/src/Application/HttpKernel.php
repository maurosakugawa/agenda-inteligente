<?php

declare(strict_types=1);

namespace AgendaInteligente\Application;

use AgendaInteligente\Infrastructure\Http\JsonResponse;
use AgendaInteligente\Infrastructure\Http\Request;
use AgendaInteligente\Infrastructure\Http\RequestHandlerInterface;

final class HttpKernel implements RequestHandlerInterface
{
    public function __construct(
        private RequestHandlerInterface $requestHandler
    ) {
    }

    public function handle(
        Request $request
    ): JsonResponse {
        return $this->requestHandler->handle(
            $request
        );
    }
}
