<?php

declare(strict_types=1);

use AgendaInteligente\Application\Auth\CsrfController;
use AgendaInteligente\Application\Auth\CurrentUserResolver;
use AgendaInteligente\Application\Auth\MeController;
use AgendaInteligente\Application\Health\HealthController;
use AgendaInteligente\Infrastructure\Database\Connection;
use AgendaInteligente\Infrastructure\Http\JsonResponse;
use AgendaInteligente\Infrastructure\Http\Middleware\CsrfMiddleware;
use AgendaInteligente\Infrastructure\Http\Middleware\SessionMiddleware;
use AgendaInteligente\Infrastructure\Http\Request;
use AgendaInteligente\Infrastructure\Http\Router;
use AgendaInteligente\Infrastructure\Persistence\UserRepository;
use AgendaInteligente\Infrastructure\Security\CsrfTokenManager;
use AgendaInteligente\Infrastructure\Session\AuthenticatedSession;
use AgendaInteligente\Infrastructure\Session\SessionManager;

require_once dirname(__DIR__) . '/autoload.php';

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function assertMeRouteSame(
    mixed $expected,
    mixed $actual,
    string $message
): void {
    if ($expected !== $actual) {
        throw new RuntimeException(
            sprintf(
                '%s Esperado: %s. Recebido: %s.',
                $message,
                var_export(
                    $expected,
                    true
                ),
                var_export(
                    $actual,
                    true
                )
            )
        );
    }
}

function assertMeRouteTrue(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException(
            $message
        );
    }
}

/**
 * @return array{
 *     name:string,
 *     secure:bool,
 *     same_site:string,
 *     idle_timeout:int,
 *     absolute_timeout:int
 * }
 */
function meRouteSessionConfig(): array
{
    return [
        'name' =>
            'AGENDA_INTELIGENTE_ME_ROUTE_TEST',
        'secure' => false,
        'same_site' => 'Lax',
        'idle_timeout' => 1800,
        'absolute_timeout' => 28800,
    ];
}

function resetMeRouteNativeSession(): void
{
    if (
        session_status()
        === PHP_SESSION_ACTIVE
    ) {
        $_SESSION = [];

        session_destroy();
    }

    if (
        session_status()
        === PHP_SESSION_NONE
    ) {
        session_id('');
    }

    $_SESSION = [];
}

function removeMeRouteSessionDirectory(
    string $sessionPath
): void {
    if (!is_dir($sessionPath)) {
        return;
    }

    $files =
        glob(
            $sessionPath . '/*'
        );

    if (is_array($files)) {
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink(
                    $file
                );
            }
        }
    }

    if (is_dir($sessionPath)) {
        rmdir(
            $sessionPath
        );
    }
}

/**
 * @return array{
 *     id:int,
 *     username:string
 * }
 */
function createMeRouteFixture(
    PDO $pdo,
    string $username,
    int $active = 1
): array {
    $statement =
        $pdo->prepare(
            "
            INSERT INTO users (
                username,
                password_hash,
                active,
                created_at,
                updated_at,
                deleted_at
            )
            VALUES (
                :username,
                :password_hash,
                :active,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP(),
                NULL
            )
            "
        );

    $statement->execute([
        ':username' => $username,
        ':password_hash' =>
            'hash-nao-utilizado-no-teste-me-route',
        ':active' => $active,
    ]);

    return [
        'id' =>
            (int) $pdo->lastInsertId(),
        'username' =>
            $username,
    ];
}

/**
 * @return array{
 *     router:Router,
 *     session:SessionManager,
 *     authenticated_session:AuthenticatedSession,
 *     handler_calls:ArrayObject
 * }
 */
function buildMeRouteTestContext(
    PDO $pdo,
    string $sessionPath,
    int &$now
): array {
    $session =
        new SessionManager(
            meRouteSessionConfig(),
            $sessionPath,
            static function () use (
                &$now
            ): int {
                return $now;
            }
        );

    $csrf =
        new CsrfTokenManager(
            $session
        );

    $csrfController =
        new CsrfController(
            $csrf
        );

    $sessionMiddleware =
        new SessionMiddleware(
            $session
        );

    $csrfMiddleware =
        new CsrfMiddleware(
            $csrf
        );

    $authenticatedSession =
        new AuthenticatedSession(
            $session,
            $csrf,
            static function () use (
                &$now
            ): int {
                return $now;
            }
        );

    $repository =
        new UserRepository(
            $pdo
        );

    $resolver =
        new CurrentUserResolver(
            $authenticatedSession,
            $repository
        );

    $meController =
        new MeController(
            $resolver
        );

    $handlerCalls =
        new ArrayObject();

    $meHandler =
        static function (
            Request $request
        ) use (
            $handlerCalls,
            $session,
            $meController
        ): JsonResponse {
            $handlerCalls->append(
                true
            );

            assertMeRouteTrue(
                $session->isStarted(),
                'O handler de /auth/me recebeu a requisição sem sessão ativa.'
            );

            return $meController->handle();
        };

    $registerHandler =
        static function (
            Request $request
        ): JsonResponse {
            return JsonResponse::success(
                [
                    'registered' => true,
                ],
                201
            );
        };

    $loginHandler =
        static function (
            Request $request
        ): JsonResponse {
            return JsonResponse::success(
                [
                    'logged_in' => true,
                ],
                200
            );
        };

    $logoutHandler =
        static function (
            Request $request
        ): JsonResponse {
            return JsonResponse::success(
                [
                    'logged_out' => true,
                ],
                200
            );
        };

    $healthController =
        new HealthController(
            static function (): void {
            }
        );

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
    $routeFactory =
        require dirname(__DIR__)
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

    return [
        'router' => $router,
        'session' => $session,
        'authenticated_session' =>
            $authenticatedSession,
        'handler_calls' =>
            $handlerCalls,
    ];
}

/**
 * @param callable(): void $test
 *
 * @return array{
 *     passed:bool,
 *     output:string
 * }
 */
function runMeRouteTest(
    string $name,
    callable $test
): array {
    resetMeRouteNativeSession();

    try {
        $test();

        return [
            'passed' => true,
            'output' =>
                "[OK] {$name}",
        ];
    } catch (Throwable $exception) {
        return [
            'passed' => false,
            'output' =>
                "[FALHA] {$name}: "
                . $exception->getMessage(),
        ];
    } finally {
        resetMeRouteNativeSession();
    }
}

/**
 * @var array{
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
$application =
    require dirname(__DIR__)
        . '/bootstrap.php';

$databaseConfig =
    $application['database'];

if (
    $databaseConfig['database']
    !== 'agenda_inteligente_test'
) {
    throw new RuntimeException(
        'Este teste só pode ser executado no banco agenda_inteligente_test.'
    );
}

$pdo =
    Connection::make(
        $databaseConfig
    );

$currentDatabase =
    $pdo
        ->query(
            'SELECT DATABASE()'
        )
        ->fetchColumn();

if (
    $currentDatabase
    !== 'agenda_inteligente_test'
) {
    throw new RuntimeException(
        'Teste recusado: conexão não está em agenda_inteligente_test.'
    );
}

$tests = [];

$tests[
    'retorna 401 para sessão anônima'
] = static function () use (
    $pdo
): void {
    $sessionPath =
        sys_get_temp_dir()
        . '/agenda-me-route-'
        . bin2hex(
            random_bytes(8)
        );

    if (
        !mkdir(
            $sessionPath,
            0700,
            true
        )
    ) {
        throw new RuntimeException(
            'Não foi possível criar diretório temporário de sessão.'
        );
    }

    try {
        $now = 1_000_000;

        $context =
            buildMeRouteTestContext(
                $pdo,
                $sessionPath,
                $now
            );

        $response =
            $context['router']->handle(
                Request::create(
                    'GET',
                    '/auth/me'
                )
            );

        assertMeRouteSame(
            401,
            $response->statusCode(),
            'Sessão anônima não retornou HTTP 401.'
        );

        assertMeRouteSame(
            [
                'error' =>
                    'Autenticação necessária',
            ],
            $response->payload(),
            'Resposta anônima de /auth/me está incorreta.'
        );

        assertMeRouteSame(
            1,
            $context[
                'handler_calls'
            ]->count(),
            'Handler de /auth/me não executou exatamente uma vez.'
        );

        assertMeRouteSame(
            PHP_SESSION_NONE,
            session_status(),
            'Sessão permaneceu aberta após /auth/me.'
        );
    } finally {
        resetMeRouteNativeSession();

        removeMeRouteSessionDirectory(
            $sessionPath
        );
    }
};

$tests[
    'retorna usuário autenticado ativo sem wrapper'
] = static function () use (
    $pdo
): void {
    $sessionPath =
        sys_get_temp_dir()
        . '/agenda-me-route-'
        . bin2hex(
            random_bytes(8)
        );

    if (
        !mkdir(
            $sessionPath,
            0700,
            true
        )
    ) {
        throw new RuntimeException(
            'Não foi possível criar diretório temporário de sessão.'
        );
    }

    try {
        $now = 2_000_000;

        $context =
            buildMeRouteTestContext(
                $pdo,
                $sessionPath,
                $now
            );

        $fixture =
            createMeRouteFixture(
                $pdo,
                'me_route_active_test'
            );

        $context['session']->start();

        $context[
            'authenticated_session'
        ]->establish(
            [
                'id' =>
                    $fixture['id'],
                'username' =>
                    'username_antigo_da_sessao',
            ]
        );

        $context['session']->close();

        $response =
            $context['router']->handle(
                Request::create(
                    'GET',
                    '/auth/me'
                )
            );

        assertMeRouteSame(
            200,
            $response->statusCode(),
            'Usuário autenticado não retornou HTTP 200.'
        );

        assertMeRouteSame(
            [
                'id' =>
                    $fixture['id'],
                'username' =>
                    $fixture['username'],
            ],
            $response->payload(),
            'Resposta autenticada de /auth/me não preservou o contrato direto.'
        );

        assertMeRouteSame(
            1,
            $context[
                'handler_calls'
            ]->count(),
            'Handler de /auth/me não executou exatamente uma vez.'
        );

        assertMeRouteSame(
            PHP_SESSION_NONE,
            session_status(),
            'Sessão permaneceu aberta após /auth/me.'
        );

        $context['session']->start();

        $auth =
            $context['session']->get(
                'auth'
            );

        assertMeRouteTrue(
            is_array(
                $auth
            ),
            'A sessão autenticada não permaneceu persistida.'
        );

        assertMeRouteSame(
            $fixture['id'],
            $auth['user_id']
                ?? null,
            'A identidade persistida foi alterada indevidamente.'
        );

        $context['session']->destroy();
    } finally {
        resetMeRouteNativeSession();

        removeMeRouteSessionDirectory(
            $sessionPath
        );
    }
};

$tests[
    'invalida sessão quando usuário está indisponível'
] = static function () use (
    $pdo
): void {
    $sessionPath =
        sys_get_temp_dir()
        . '/agenda-me-route-'
        . bin2hex(
            random_bytes(8)
        );

    if (
        !mkdir(
            $sessionPath,
            0700,
            true
        )
    ) {
        throw new RuntimeException(
            'Não foi possível criar diretório temporário de sessão.'
        );
    }

    try {
        $now = 3_000_000;

        $context =
            buildMeRouteTestContext(
                $pdo,
                $sessionPath,
                $now
            );

        $fixture =
            createMeRouteFixture(
                $pdo,
                'me_route_inactive_test',
                0
            );

        $context['session']->start();

        $context[
            'authenticated_session'
        ]->establish(
            [
                'id' =>
                    $fixture['id'],
                'username' =>
                    $fixture['username'],
            ]
        );

        $context['session']->close();

        $response =
            $context['router']->handle(
                Request::create(
                    'GET',
                    '/auth/me'
                )
            );

        assertMeRouteSame(
            401,
            $response->statusCode(),
            'Usuário indisponível não retornou HTTP 401.'
        );

        assertMeRouteSame(
            [
                'error' =>
                    'Autenticação necessária',
            ],
            $response->payload(),
            'Usuário indisponível expôs motivo diferente de autenticação.'
        );

        assertMeRouteSame(
            PHP_SESSION_NONE,
            session_status(),
            'Sessão destruída deixou o mecanismo nativo ativo.'
        );

        $context['session']->start();

        assertMeRouteSame(
            null,
            $context['session']->get(
                'auth'
            ),
            'Identidade indisponível permaneceu persistida.'
        );

        $context['session']->destroy();
    } finally {
        resetMeRouteNativeSession();

        removeMeRouteSessionDirectory(
            $sessionPath
        );
    }
};

$results = [];
$passed = 0;
$total = count(
    $tests
);

$pdo->beginTransaction();

try {
    foreach (
        $tests as $name => $test
    ) {
        $result =
            runMeRouteTest(
                $name,
                $test
            );

        $results[] =
            $result['output'];

        if ($result['passed']) {
            ++$passed;
        }
    }
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

foreach ($results as $result) {
    fwrite(
        STDOUT,
        $result . "\n"
    );
}

fwrite(
    STDOUT,
    "\nMe route: "
    . "{$passed}/{$total} teste(s) aprovado(s).\n"
);

exit(
    $passed === $total
        ? 0
        : 1
);
