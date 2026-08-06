<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Http;

interface RequestHandlerInterface
{
    public function handle(
        Request $request
    ): JsonResponse;
}
