<?php

declare(strict_types=1);

use AgendaInteligente\Application\Health\HealthController;
use AgendaInteligente\Application\HttpKernel;
use AgendaInteligente\Infrastructure\Config\ConfigurationException;
use AgendaInteligente\Infrastructure\Database\Connection;
use AgendaInteligente\Infrastructure\Http\JsonResponse;
use AgendaInteligente\Infrastructure\Http\Request;
use AgendaInteligente\Infrastructure\Http\Router;
use AgendaInteligente\Infrastructure\Logging\ExceptionLogger;

require_once dirname(__DIR__) . '/autoload.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
header_remove('X-Powered-By');

try {
    /**
     * @var array{
     *     config:array<string, mixed>,
     *     database:array{
     *         host:string,
     *         port:int,
     *         database:string,
     *         username:string,
     *         password:string,
     *         charset:string
     *     }
     * } $application
     */
    $application = require dirname(__DIR__) . '/bootstrap.php';

    $databaseConfig = $application['database'];

    $healthController = new HealthController(
        static function () use (
            $databaseConfig
        ): void {
            $pdo = Connection::make(
                $databaseConfig
            );

            $statement = $pdo->query(
                'SELECT 1'
            );

            if ($statement === false) {
                throw new RuntimeException(
                    'A consulta de saúde do banco falhou.'
                );
            }
        }
    );

    /** @var callable(HealthController): Router $routeFactory */
    $routeFactory = require dirname(__DIR__)
        . '/routes/http.php';

    $kernel = new HttpKernel(
        $routeFactory(
            $healthController
        )
    );

    $response = $kernel->handle(
        Request::fromGlobals()
    );
} catch (ConfigurationException $exception) {
    ExceptionLogger::log(
        $exception,
        'bootstrap.configuration'
    );

    $response = JsonResponse::error(
        'service_unavailable',
        'Serviço temporariamente indisponível.',
        503
    );
} catch (Throwable $exception) {
    ExceptionLogger::log(
        $exception,
        'http.unhandled'
    );

    $response = JsonResponse::error(
        'internal_error',
        'Ocorreu um erro interno.',
        500
    );
}

$response->send();
