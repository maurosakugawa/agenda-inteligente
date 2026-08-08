<?php

declare(strict_types=1);

use AgendaInteligente\Application\Auth\CsrfController;
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
function assertLoginRouteSame(
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

function assertLoginRouteTrue(
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
function loginRouteSessionConfig(): array
{
    return [
        'name' =>
            'AGENDA_INTELIGENTE_LOGIN_ROUTE_TEST',
        'secure' => false,
        'same_site' => 'Lax',
        'idle_timeout' => 1800,
        'absolute_timeout' => 28800,
    ];
}

function resetLoginRouteNativeSession(): void
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

function removeLoginRouteSessionDirectory(
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
 *     handlerCalls:ArrayObject
 * }
 */
function buildLoginRouteTestContext(
    string $sessionPath,
    int &$now
): array {
    $session =
        new SessionManager(
            loginRouteSessionConfig(),
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

                return str_repeat(
                    $randomCall === 1
                        ? "\x11"
                        : "\x22",
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

    $handlerCalls =
        new ArrayObject();

    $loginHandler =
        static function (
            Request $request
        ) use (
            $handlerCalls,
            $session,
            $authenticatedSession
        ): JsonResponse {
            $handlerCalls->append(
                true
            );

            assertLoginRouteTrue(
                $session->isStarted(),
                'O handler de login recebeu a requisição sem sessão ativa.'
            );

            $input =
                $request->json();

            $username =
                $input['username']
                ?? null;

            $password =
                $input['password']
                ?? null;

            if (
                !is_string($username)
                || !is_string($password)
            ) {
                return JsonResponse::error(
                    'invalid_credentials_input',
                    'Username e senha são obrigatórios.',
                    400
                );
            }

            $identity = [
                'id' => 42,
                'username' =>
                    $username,
            ];

            $newCsrfToken =
                $authenticatedSession->establish(
                    $identity
                );

            return JsonResponse::success(
                [
                    'message' =>
                        'Login realizado',
                    'user' => [
                        'id' =>
                            $identity['id'],
                        'username' =>
                            $identity['username'],
                    ],
                    'csrf_token' =>
                        $newCsrfToken,
                ],
                200
            );
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
            $loginHandler
        );

    return [
        'router' => $router,
        'session' => $session,
        'csrf' => $csrf,
        'handlerCalls' =>
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
function runLoginRouteTest(
    string $name,
    callable $test
): array {
    resetLoginRouteNativeSession();

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
        resetLoginRouteNativeSession();
    }
}

$tests = [];

$tests[
    'bloqueia login sem token csrf antes do handler'
] = static function (): void {
    $sessionPath =
        sys_get_temp_dir()
        . '/agenda-login-route-'
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
            buildLoginRouteTestContext(
                $sessionPath,
                $now
            );

        $response =
            $context['router']->handle(
                Request::create(
                    'POST',
                    '/auth/login',
                    [
                        'Content-Type' =>
                            'application/json',
                    ],
                    json_encode(
                        [
                            'username' =>
                                'login_route_test',
                            'password' =>
                                'senha-valida-de-login',
                        ],
                        JSON_THROW_ON_ERROR
                    )
                )
            );

        assertLoginRouteSame(
            403,
            $response->statusCode(),
            'Login sem CSRF não retornou 403.'
        );

        assertLoginRouteSame(
            'csrf_invalid',
            $response
                ->payload()['error']['code']
                ?? null,
            'Código de erro CSRF está incorreto.'
        );

        assertLoginRouteSame(
            0,
            $context['handlerCalls']->count(),
            'Handler de login foi executado sem CSRF.'
        );

        assertLoginRouteSame(
            PHP_SESSION_NONE,
            session_status(),
            'Sessão permaneceu aberta após bloqueio CSRF.'
        );
    } finally {
        resetLoginRouteNativeSession();

        removeLoginRouteSessionDirectory(
            $sessionPath
        );
    }
};

$tests[
    'autentica com csrf e estabelece nova sessão'
] = static function (): void {
    $sessionPath =
        sys_get_temp_dir()
        . '/agenda-login-route-'
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
            buildLoginRouteTestContext(
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

        assertLoginRouteSame(
            200,
            $csrfResponse->statusCode(),
            'Obtenção de CSRF não retornou 200.'
        );

        $oldToken =
            $csrfResponse
                ->payload()['csrf_token']
                ?? null;

        assertLoginRouteTrue(
            is_string(
                $oldToken
            ),
            'Rota CSRF não retornou token.'
        );

        assertLoginRouteSame(
            str_repeat(
                '11',
                32
            ),
            $oldToken,
            'Token CSRF anônimo está incorreto.'
        );

        $oldSessionId =
            session_id();

        assertLoginRouteTrue(
            $oldSessionId !== '',
            'Sessão anônima não possui identificador.'
        );

        assertLoginRouteSame(
            PHP_SESSION_NONE,
            session_status(),
            'Sessão anônima permaneceu aberta após obter CSRF.'
        );

        $now = 2_000_123;

        $response =
            $context['router']->handle(
                Request::create(
                    'POST',
                    '/auth/login',
                    [
                        'Content-Type' =>
                            'application/json',
                        'X-CSRF-Token' =>
                            $oldToken,
                    ],
                    json_encode(
                        [
                            'username' =>
                                'login_route_test',
                            'password' =>
                                'senha-valida-de-login',
                        ],
                        JSON_THROW_ON_ERROR
                    )
                )
            );

        assertLoginRouteSame(
            200,
            $response->statusCode(),
            'Login válido não retornou 200.'
        );

        assertLoginRouteSame(
            1,
            $context['handlerCalls']->count(),
            'Handler de login não foi executado exatamente uma vez.'
        );

        assertLoginRouteSame(
            [
                'id' => 42,
                'username' =>
                    'login_route_test',
            ],
            $response
                ->payload()['data']['user']
                ?? null,
            'Usuário retornado pelo login está incorreto.'
        );

        $newToken =
            $response
                ->payload()['data']['csrf_token']
                ?? null;

        assertLoginRouteSame(
            str_repeat(
                '22',
                32
            ),
            $newToken,
            'Novo token CSRF está incorreto.'
        );

        assertLoginRouteTrue(
            $oldToken !== $newToken,
            'O token CSRF não foi rotacionado.'
        );

        $newSessionId =
            session_id();

        assertLoginRouteTrue(
            $newSessionId !== '',
            'Sessão autenticada não possui identificador.'
        );

        assertLoginRouteTrue(
            $oldSessionId !== $newSessionId,
            'O identificador da sessão não foi regenerado.'
        );

        assertLoginRouteSame(
            PHP_SESSION_NONE,
            session_status(),
            'Sessão permaneceu aberta após login.'
        );

        $context['session']->start();

        assertLoginRouteSame(
            [
                'user_id' => 42,
                'username' =>
                    'login_route_test',
                'authenticated_at' =>
                    2_000_123,
            ],
            $context['session']->get(
                'auth'
            ),
            'Estado autenticado persistido está incorreto.'
        );

        $security =
            $context['session']->get(
                'security'
            );

        assertLoginRouteSame(
            2_000_123,
            $security['created_at']
                ?? null,
            'created_at não foi renovado durante login.'
        );

        assertLoginRouteSame(
            2_000_123,
            $security['last_activity_at']
                ?? null,
            'last_activity_at não foi renovado durante login.'
        );

        assertLoginRouteSame(
            2_028_923,
            $security['absolute_expires_at']
                ?? null,
            'absolute_expires_at não foi renovado durante login.'
        );

        assertLoginRouteSame(
            $newToken,
            $context['csrf']->currentToken(),
            'Token retornado não corresponde ao token persistido.'
        );

        assertLoginRouteSame(
            false,
            $context['csrf']->validate(
                $oldToken
            ),
            'Token CSRF anterior permaneceu válido.'
        );

        assertLoginRouteSame(
            true,
            $context['csrf']->validate(
                $newToken
            ),
            'Novo token CSRF não é válido.'
        );

        $context['session']->destroy();
    } finally {
        resetLoginRouteNativeSession();

        removeLoginRouteSessionDirectory(
            $sessionPath
        );
    }
};

$tests[
    'json inválido com csrf válido retorna 400 e fecha sessão'
] = static function (): void {
    $sessionPath =
        sys_get_temp_dir()
        . '/agenda-login-route-'
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
            buildLoginRouteTestContext(
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

        assertLoginRouteTrue(
            is_string(
                $token
            ),
            'Não foi possível obter token CSRF.'
        );

        $response =
            $context['router']->handle(
                Request::create(
                    'POST',
                    '/auth/login',
                    [
                        'Content-Type' =>
                            'application/json',
                        'X-CSRF-Token' =>
                            $token,
                    ],
                    '{"username":'
                )
            );

        assertLoginRouteSame(
            400,
            $response->statusCode(),
            'JSON inválido não retornou 400.'
        );

        assertLoginRouteSame(
            'invalid_json_body',
            $response
                ->payload()['error']['code']
                ?? null,
            'JSON inválido retornou código incorreto.'
        );

        assertLoginRouteSame(
            1,
            $context['handlerCalls']->count(),
            'Handler deveria receber a requisição após CSRF válido.'
        );

        assertLoginRouteSame(
            PHP_SESSION_NONE,
            session_status(),
            'Sessão permaneceu aberta após JSON inválido.'
        );
    } finally {
        resetLoginRouteNativeSession();

        removeLoginRouteSessionDirectory(
            $sessionPath
        );
    }
};

$results = [];
$passed = 0;

$total =
    count(
        $tests
    );

foreach ($tests as $name => $test) {
    $result =
        runLoginRouteTest(
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
    "\nLogin route: "
    . "{$passed}/{$total} teste(s) aprovado(s).\n"
);

exit(
    $passed === $total
        ? 0
        : 1
);
