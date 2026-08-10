<?php

declare(strict_types=1);

namespace AgendaInteligente\Application\Auth;

use AgendaInteligente\Infrastructure\Http\JsonResponse;
use AgendaInteligente\Infrastructure\Security\CsrfTokenManager;

final class CsrfController
{
    public function __construct(
        private CsrfTokenManager $csrf
    ) {
    }

    public function handle(): JsonResponse
    {
        return new JsonResponse(
            [
                'csrf_token' => $this->csrf->token(),
            ],
            200,
            [
                'Cache-Control' => 'no-store',
            ]
        );
    }
}
