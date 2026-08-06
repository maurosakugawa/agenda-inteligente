<?php

declare(strict_types=1);

namespace AgendaInteligente\Application\Health;

use AgendaInteligente\Infrastructure\Http\JsonResponse;
use AgendaInteligente\Infrastructure\Logging\ExceptionLogger;
use Closure;
use Throwable;

final class HealthController
{
    /**
     * @param Closure(): void $databaseProbe
     */
    public function __construct(
        private Closure $databaseProbe
    ) {
    }

    public function handle(): JsonResponse
    {
        $timestamp = gmdate(
            DATE_ATOM
        );

        try {
            ($this->databaseProbe)();

            return JsonResponse::success(
                [
                    'service' => 'agenda-inteligente-api',
                    'status' => 'ok',
                    'checks' => [
                        'application' => [
                            'status' => 'ok',
                        ],
                        'database' => [
                            'status' => 'ok',
                        ],
                    ],
                    'timestamp' => $timestamp,
                ]
            );
        } catch (Throwable $exception) {
            ExceptionLogger::log(
                $exception,
                'health.database'
            );

            return JsonResponse::error(
                'service_unavailable',
                'O serviço não está totalmente disponível.',
                503,
                [
                    'service' => 'agenda-inteligente-api',
                    'status' => 'degraded',
                    'checks' => [
                        'application' => [
                            'status' => 'ok',
                        ],
                        'database' => [
                            'status' => 'unavailable',
                        ],
                    ],
                    'timestamp' => $timestamp,
                ]
            );
        }
    }
}
