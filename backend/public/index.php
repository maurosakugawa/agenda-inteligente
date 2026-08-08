<?php

declare(strict_types=1);

use AgendaInteligente\Application\Auth\CredentialValidator;
use AgendaInteligente\Application\Auth\CsrfController;
use AgendaInteligente\Application\Auth\RegisterController;
use AgendaInteligente\Application\Auth\UserRegistrar;
use AgendaInteligente\Application\Health\HealthController;
use AgendaInteligente\Application\HttpKernel;
use AgendaInteligente\Infrastructure\Config\ConfigurationException;
use AgendaInteligente\Infrastructure\Database\Connection;
use AgendaInteligente\Infrastructure\Http\JsonResponse;
use AgendaInteligente\Infrastructure\Http\Middleware\CsrfMiddleware;
use AgendaInteligente\Infrastructure\Http\Middleware\SessionMiddleware;
use AgendaInteligente\Infrastructure\Http\Request;
use AgendaInteligente\Infrastructure\Http\Router;
use AgendaInteligente\Infrastructure\Logging\ExceptionLogger;
use AgendaInteligente\Infrastructure\Persistence\UserRepository;
use AgendaInteligente\Infrastructure\Security\CsrfTokenManager;
use AgendaInteligente\Infrastructure\Security\PasswordHasher;
use AgendaInteligente\Infrastructure\Session\SessionManager;

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

    $config = $application['config'];

    /**
     * @var array{
     *     name:string,
     *     secure:bool,
     *     same_site:string,
     *     idle_timeout:int,
     *     absolute_timeout:int
     * } $sessionConfig
     */
    $sessionConfig = $config['session'];

    $sessionManager = new SessionManager(
        $sessionConfig
    );

    $csrfTokenManager = new CsrfTokenManager(
        $sessionManager
    );

    $csrfController = new CsrfController(
        $csrfTokenManager
    );

    $sessionMiddleware = new SessionMiddleware(
        $sessionManager
    );

    $csrfMiddleware = new CsrfMiddleware(
        $csrfTokenManager
    );

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

    /**
     * @var callable(Request): JsonResponse $registerHandler
     */
    $registerHandler = static function (
        Request $request
    ) use (
        $databaseConfig
    ): JsonResponse {
        static $registerController = null;

        if (
            !$registerController instanceof RegisterController
        ) {
            $pdo = Connection::make(
                $databaseConfig
            );

            $repository = new UserRepository(
                $pdo
            );

            $registrar = new UserRegistrar(
                $repository,
                new PasswordHasher(),
                new CredentialValidator()
            );

            $registerController = new RegisterController(
                $registrar
            );
        }

        return $registerController->handle(
            $request
        );
    };

    /**
     * @var callable(
     *     HealthController,
     *     CsrfController,
     *     SessionMiddleware,
     *     CsrfMiddleware,
     *     callable(Request): JsonResponse
     * ): Router $routeFactory
     */
    $routeFactory = require dirname(__DIR__)
        . '/routes/http.php';

    $kernel = new HttpKernel(
        $routeFactory(
            $healthController,
            $csrfController,
            $sessionMiddleware,
            $csrfMiddleware,
            $registerHandler
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
