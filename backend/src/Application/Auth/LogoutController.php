<?php

declare(strict_types=1);

namespace AgendaInteligente\Application\Auth;

use AgendaInteligente\Infrastructure\Http\JsonResponse;

final class LogoutController
{
    public function __construct(
        private AuthenticationSession $session
    ) {
    }

    public function handle(): JsonResponse
    {
        $this->session->terminate();

        return JsonResponse::success(
            [
                'message' =>
                    'Logout realizado',
            ],
            200
        );
    }
}
