<?php

declare(strict_types=1);

use AgendaInteligente\Application\Auth\CredentialValidator;
use AgendaInteligente\Application\Auth\CredentialVerifier;
use AgendaInteligente\Application\Auth\CsrfController;
use AgendaInteligente\Application\Auth\CurrentUserProvider;
use AgendaInteligente\Application\Auth\CurrentUserResolver;
use AgendaInteligente\Application\Auth\LoginController;
use AgendaInteligente\Application\Auth\LoginRateLimiter;
use AgendaInteligente\Application\Auth\LogoutController;
use AgendaInteligente\Application\Auth\MeController;
use AgendaInteligente\Application\Auth\RegisterController;
use AgendaInteligente\Application\Auth\UserRegistrar;
use AgendaInteligente\Application\Contacts\ContactController;
use AgendaInteligente\Application\Contacts\ContactCreator;
use AgendaInteligente\Application\Contacts\ContactDeleter;
use AgendaInteligente\Application\Contacts\ContactLister;
use AgendaInteligente\Application\Contacts\ContactUpdater;
use AgendaInteligente\Application\Contacts\ContactValidator;
use AgendaInteligente\Application\Health\HealthController;
use AgendaInteligente\Application\HttpKernel;
use AgendaInteligente\Infrastructure\Config\ConfigurationException;
use AgendaInteligente\Infrastructure\Database\Connection;
use AgendaInteligente\Infrastructure\Http\JsonResponse;
use AgendaInteligente\Infrastructure\Http\Middleware\AuthenticationMiddleware;
use AgendaInteligente\Infrastructure\Http\Middleware\CsrfMiddleware;
use AgendaInteligente\Infrastructure\Http\Middleware\SessionMiddleware;
use AgendaInteligente\Infrastructure\Http\Request;
use AgendaInteligente\Infrastructure\Http\Router;
use AgendaInteligente\Infrastructure\Logging\ExceptionLogger;
use AgendaInteligente\Infrastructure\Persistence\ContactRepository;
use AgendaInteligente\Infrastructure\Persistence\MySqlRateLimitRepository;
use AgendaInteligente\Infrastructure\Persistence\UserRepository;
use AgendaInteligente\Infrastructure\Security\CsrfTokenManager;
use AgendaInteligente\Infrastructure\Security\PasswordHasher;
use AgendaInteligente\Infrastructure\Session\AuthenticatedSession;
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

    /**
     * @var array{
     *     key_secret:string,
     *     ip:array{
     *         max_attempts:int,
     *         window_seconds:int,
     *         block_seconds:int
     *     },
     *     username_ip:array{
     *         max_failures:int,
     *         window_seconds:int,
     *         block_seconds:int
     *     }
     * } $loginRateLimitConfig
     */
    $loginRateLimitConfig =
        $config['login_rate_limit'];

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

    $authenticatedSession = new AuthenticatedSession(
        $sessionManager,
        $csrfTokenManager
    );

    /**
     * Conexão compartilhada apenas pelas rotas de Contatos.
     *
     * A PDO só é criada quando autenticação ou controller
     * de Contatos realmente precisar do banco.
     */
    $contactDatabase =
        static function () use (
            $databaseConfig
        ): PDO {
            static $pdo = null;

            if (!$pdo instanceof PDO) {
                $pdo = Connection::make(
                    $databaseConfig
                );
            }

            return $pdo;
        };

    $contactCurrentUser =
        new class(
            $contactDatabase,
            $authenticatedSession
        ) implements CurrentUserProvider {
            private ?CurrentUserResolver $resolver =
                null;

            public function __construct(
                private \Closure $database,
                private AuthenticatedSession $session
            ) {
            }

            public function resolve(): ?array
            {
                if (
                    !$this->resolver
                    instanceof CurrentUserResolver
                ) {
                    $repository =
                        new UserRepository(
                            ($this->database)()
                        );

                    $this->resolver =
                        new CurrentUserResolver(
                            $this->session,
                            $repository
                        );
                }

                return $this->resolver->resolve();
            }
        };

    $authenticationMiddleware =
        new AuthenticationMiddleware(
            $contactCurrentUser
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
     * @var callable(Request): JsonResponse $loginHandler
     */
    $loginHandler = static function (
        Request $request
    ) use (
        $databaseConfig,
        $loginRateLimitConfig,
        $authenticatedSession
    ): JsonResponse {
        static $loginController = null;

        if (
            !$loginController instanceof LoginController
        ) {
            $pdo = Connection::make(
                $databaseConfig
            );

            $repository = new UserRepository(
                $pdo
            );

            $credentials =
                new CredentialValidator();

            $verifier = new CredentialVerifier(
                $repository,
                new PasswordHasher(),
                $credentials
            );

            $rateLimiter =
                new LoginRateLimiter(
                    new MySqlRateLimitRepository(
                        $pdo
                    ),
                    $loginRateLimitConfig
                );

            $loginController = new LoginController(
                $verifier,
                $credentials,
                $rateLimiter,
                $authenticatedSession
            );
        }

        return $loginController->handle(
            $request
        );
    };

    $logoutController =
        new LogoutController(
            $authenticatedSession
        );

    /**
     * @var callable(Request): JsonResponse $logoutHandler
     */
    $logoutHandler = static function (
        Request $request
    ) use (
        $logoutController
    ): JsonResponse {
        return $logoutController->handle();
    };

    /**
     * @var callable(Request): JsonResponse $meHandler
     */
    $meHandler = static function (
        Request $request
    ) use (
        $databaseConfig,
        $authenticatedSession
    ): JsonResponse {
        static $meController = null;

        if (
            !$meController instanceof MeController
        ) {
            $pdo = Connection::make(
                $databaseConfig
            );

            $repository =
                new UserRepository(
                    $pdo
                );

            $currentUser =
                new CurrentUserResolver(
                    $authenticatedSession,
                    $repository
                );

            $meController =
                new MeController(
                    $currentUser
                );
        }

        return $meController->handle();
    };

    /**
     * @var \Closure(): ContactController $contactControllerFactory
     */
    $contactControllerFactory =
        static function () use (
            $contactDatabase
        ): ContactController {
            static $contactController = null;

            if (
                !$contactController
                instanceof ContactController
            ) {
                $repository =
                    new ContactRepository(
                        $contactDatabase()
                    );

                $validator =
                    new ContactValidator();

                $contactController =
                    new ContactController(
                        new ContactLister(
                            $repository
                        ),
                        new ContactCreator(
                            $repository,
                            $validator
                        ),
                        new ContactUpdater(
                            $repository,
                            $validator
                        ),
                        new ContactDeleter(
                            $repository
                        )
                    );
            }

            return $contactController;
        };

    $contactHandlers = [
        'list' =>
            static function (
                Request $request
            ) use (
                $contactControllerFactory
            ): JsonResponse {
                return $contactControllerFactory()
                    ->list(
                        $request
                    );
            },

        'create' =>
            static function (
                Request $request
            ) use (
                $contactControllerFactory
            ): JsonResponse {
                return $contactControllerFactory()
                    ->create(
                        $request
                    );
            },

        'update' =>
            static function (
                Request $request
            ) use (
                $contactControllerFactory
            ): JsonResponse {
                return $contactControllerFactory()
                    ->update(
                        $request
                    );
            },

        'delete' =>
            static function (
                Request $request
            ) use (
                $contactControllerFactory
            ): JsonResponse {
                return $contactControllerFactory()
                    ->delete(
                        $request
                    );
            },
    ];

    /**
     * @var callable(
     *     HealthController,
     *     CsrfController,
     *     SessionMiddleware,
     *     CsrfMiddleware,
     *     callable(Request): JsonResponse,
     *     callable(Request): JsonResponse,
     *     callable(Request): JsonResponse,
     *     callable(Request): JsonResponse
     * ): Router $routeFactory
     */
    $routeFactory = require dirname(__DIR__)
        . '/routes/http.php';

    $router =
        $routeFactory(
            $healthController,
            $csrfController,
            $sessionMiddleware,
            $csrfMiddleware,
            $registerHandler,
            $loginHandler,
            $logoutHandler,
            $meHandler
        );

    /**
     * @var callable(
     *     Router,
     *     SessionMiddleware,
     *     AuthenticationMiddleware,
     *     CsrfMiddleware,
     *     array<string, callable>
     * ): Router $contactRouteRegistrar
     */
    $contactRouteRegistrar =
        require dirname(__DIR__)
            . '/routes/contacts.php';

    $router =
        $contactRouteRegistrar(
            $router,
            $sessionMiddleware,
            $authenticationMiddleware,
            $csrfMiddleware,
            $contactHandlers
        );

    $kernel =
        new HttpKernel(
            $router
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
