<?php

declare(strict_types=1);

namespace AgendaInteligente\Application;

use AgendaInteligente\Application\Health\HealthController;
use AgendaInteligente\Infrastructure\Http\JsonResponse;
use AgendaInteligente\Infrastructure\Http\Request;

final class HttpKernel
{
    public function __construct(
        private HealthController $healthController
    ) {
    }

    public function handle(
        Request $request
    ): JsonResponse {
        if (
            in_array(
                $request->path(),
                [
                    '/health',
                    '/api/health',
                ],
                true
            )
        ) {
            if ($request->method() !== 'GET') {
                return JsonResponse::error(
                    'method_not_allowed',
                    'Método não permitido.',
                    405,
                    [
                        'allowed_methods' => [
                            'GET',
                        ],
                    ],
                    [
                        'Allow' => 'GET',
                    ]
                );
            }

            return $this->healthController->handle();
        }

        return JsonResponse::error(
            'route_not_found',
            'Rota não encontrada.',
            404
        );
    }
}
