<?php

declare(strict_types=1);

use AgendaInteligente\Application\Auth\CsrfController;
use AgendaInteligente\Application\Auth\LogoutController;
use AgendaInteligente\Application\Health\HealthController;
use AgendaInteligente\Infrastructure\Http\JsonResponse;
use AgendaInteligente\Infrastructure\Http\Middleware\CsrfMiddleware;
use AgendaInteligente\Infrastructure\Http\Middleware\SessionMiddleware;
use AgendaInteligente\Infrastructure\Http\Request;
use AgendaInteligente\Infrastructure\Http\Router;
use AgendaInteligente\Infrastructure\Security\CsrfTokenManager;
use AgendaInteligente\Infrastructure\Session\AuthenticatedSession;
use AgendaInteligente\Infrastructure\Session\SessionManager;

require_once dirname(__DIR__) . '/autoload.php';

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function assertLogoutRouteSame(
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

function assertLogoutRouteTrue(
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
function logoutRouteSessionConfig(): array
{
    return [
        'name' =>
            'AGENDA_INTELIGENTE_LOGOUT_ROUTE_TEST',
        'secure' => false,
        'same_site' => 'Lax',
        'idle_timeout' => 1800,
        'absolute_timeout' => 28800,
    ];
}

function resetLogoutRouteNativeSession(): void
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

function removeLogoutRouteSessionDirectory(
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
 *     router:Router,
 *     session:SessionManager,
 *     csrf:CsrfTokenManager,
 *     authenticated_session:AuthenticatedSession,
 *     handler_calls:ArrayObject
 * }
 */
function buildLogoutRouteTestContext(
    string $sessionPath,
    int &$now
): array {
    $session =
        new SessionManager(
            logoutRouteSessionConfig(),
            $sessionPath,
            static function () use (
                &$now
            ): int {
                return $now;
            }
        );

    $randomCall = 0;

    $csrf =
        new CsrfTokenManager(
            $session,
            static function (
                int $length
            ) use (
                &$randomCall
            ): string {
                ++$randomCall;

                $byte =
                    match ($randomCall) {
                        1 => "\x11",
                        2 => "\x22",
                        default => "\x33",
                    };

                return str_repeat(
                    $byte,
                    $length
                );
            }
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

    $logoutController =
        new LogoutController(
            $authenticatedSession
        );

    $handlerCalls =
        new ArrayObject();

    $logoutHandler =
        static function (
            Request $request
        ) use (
            $handlerCalls,
            $session,
            $logoutController
        ): JsonResponse {
            $handlerCalls->append(
                true
            );

            assertLogoutRouteTrue(
                $session->isStarted(),
                'O handler de logout recebeu a requisição sem sessão ativa.'
            );

            return $logoutController->handle();
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
            $logoutHandler
        );

    return [
        'router' => $router,
        'session' => $session,
        'csrf' => $csrf,
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
function runLogoutRouteTest(
    string $name,
    callable $test
): array {
    resetLogoutRouteNativeSession();

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
        resetLogoutRouteNativeSession();
    }
}

$tests = [];

$tests[
    'bloqueia logout sem token csrf antes do handler'
] = static function (): void {
    $sessionPath =
        sys_get_temp_dir()
        . '/agenda-logout-route-'
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
            buildLogoutRouteTestContext(
                $sessionPath,
                $now
            );

        $response =
            $context['router']->handle(
                Request::create(
                    'POST',
                    '/auth/logout'
                )
            );

        assertLogoutRouteSame(
            403,
            $response->statusCode(),
            'Logout sem CSRF não retornou 403.'
        );

        assertLogoutRouteSame(
            'csrf_invalid',
            $response
                ->payload()['error']['code']
                ?? null,
            'Código de erro CSRF está incorreto.'
        );

        assertLogoutRouteSame(
            0,
            $context[
                'handler_calls'
            ]->count(),
            'Handler de logout foi executado sem CSRF.'
        );

        assertLogoutRouteSame(
            PHP_SESSION_NONE,
            session_status(),
            'Sessão permaneceu aberta após bloqueio CSRF.'
        );
    } finally {
        resetLogoutRouteNativeSession();

        removeLogoutRouteSessionDirectory(
            $sessionPath
        );
    }
};

$tests[
    'encerra sessão autenticada com csrf válido'
] = static function (): void {
    $sessionPath =
        sys_get_temp_dir()
        . '/agenda-logout-route-'
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
            buildLogoutRouteTestContext(
                $sessionPath,
                $now
            );

        $csrfResponse =
            $context['router']->handle(
                Request::create(
                    'GET',
                    '/auth/csrf'
                )
            );

        $anonymousToken =
            $csrfResponse
                ->payload()['csrf_token']
                ?? null;

        assertLogoutRouteTrue(
            is_string(
                $anonymousToken
            ),
            'Rota CSRF não retornou token.'
        );

        $context['session']->start();

        $now = 2_000_123;

        $authenticatedToken =
            $context[
                'authenticated_session'
            ]->establish(
                [
                    'id' => 42,
                    'username' =>
                        'logout_route_test',
                ]
            );

        assertLogoutRouteTrue(
            $anonymousToken
            !== $authenticatedToken,
            'Login simulado não rotacionou o CSRF.'
        );

        $context['session']->close();

        $response =
            $context['router']->handle(
                Request::create(
                    'POST',
                    '/auth/logout',
                    [
                        'X-CSRF-Token' =>
                            $authenticatedToken,
                    ]
                )
            );

        assertLogoutRouteSame(
            200,
            $response->statusCode(),
            'Logout autenticado não retornou 200.'
        );

        assertLogoutRouteSame(
            'Logout realizado',
            $response
                ->payload()['data']['message']
                ?? null,
            'Mensagem de logout está incorreta.'
        );

        assertLogoutRouteSame(
            1,
            $context[
                'handler_calls'
            ]->count(),
            'Handler de logout não executou exatamente uma vez.'
        );

        assertLogoutRouteSame(
            PHP_SESSION_NONE,
            session_status(),
            'Sessão permaneceu ativa após logout.'
        );

        $context['session']->start();

        assertLogoutRouteSame(
            null,
            $context['session']->get(
                'auth'
            ),
            'Identidade autenticada permaneceu após logout.'
        );

        assertLogoutRouteSame(
            false,
            $context['csrf']->validate(
                $authenticatedToken
            ),
            'CSRF autenticado anterior permaneceu válido.'
        );

        $context['session']->destroy();
    } finally {
        resetLogoutRouteNativeSession();

        removeLogoutRouteSessionDirectory(
            $sessionPath
        );
    }
};

$tests[
    'permite logout anônimo com csrf válido'
] = static function (): void {
    $sessionPath =
        sys_get_temp_dir()
        . '/agenda-logout-route-'
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
            buildLogoutRouteTestContext(
                $sessionPath,
                $now
            );

        $csrfResponse =
            $context['router']->handle(
                Request::create(
                    'GET',
                    '/auth/csrf'
                )
            );

        $token =
            $csrfResponse
                ->payload()['csrf_token']
                ?? null;

        assertLogoutRouteTrue(
            is_string(
                $token
            ),
            'Sessão anônima não recebeu CSRF.'
        );

        $response =
            $context['router']->handle(
                Request::create(
                    'POST',
                    '/auth/logout',
                    [
                        'X-CSRF-Token' =>
                            $token,
                    ]
                )
            );

        assertLogoutRouteSame(
            200,
            $response->statusCode(),
            'Logout anônimo com CSRF válido não retornou 200.'
        );

        assertLogoutRouteSame(
            'Logout realizado',
            $response
                ->payload()['data']['message']
                ?? null,
            'Resposta do logout anônimo está incorreta.'
        );

        assertLogoutRouteSame(
            1,
            $context[
                'handler_calls'
            ]->count(),
            'Handler não executou no logout anônimo válido.'
        );

        assertLogoutRouteSame(
            PHP_SESSION_NONE,
            session_status(),
            'Sessão anônima permaneceu ativa após logout.'
        );

        $context['session']->start();

        assertLogoutRouteSame(
            false,
            $context['csrf']->validate(
                $token
            ),
            'CSRF anônimo anterior permaneceu válido após logout.'
        );

        $context['session']->destroy();
    } finally {
        resetLogoutRouteNativeSession();

        removeLogoutRouteSessionDirectory(
            $sessionPath
        );
    }
};

$results = [];
$passed = 0;
$total = count(
    $tests
);

foreach ($tests as $name => $test) {
    $result =
        runLogoutRouteTest(
            $name,
            $test
        );

    $results[] =
        $result['output'];

    if ($result['passed']) {
        ++$passed;
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
    "\nLogout route: "
    . "{$passed}/{$total} teste(s) aprovado(s).\n"
);

exit(
    $passed === $total
        ? 0
        : 1
);
