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
use AgendaInteligente\Infrastructure\Session\SessionManager;

require_once dirname(__DIR__) . '/autoload.php';

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function assertRegisterRouteSame(
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

function assertRegisterRouteTrue(
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
function registerRouteSessionConfig(): array
{
    return [
        'name' =>
            'AGENDA_INTELIGENTE_REGISTER_ROUTE_TEST',
        'secure' => false,
        'same_site' => 'Lax',
        'idle_timeout' => 1800,
        'absolute_timeout' => 28800,
    ];
}

function resetRegisterRouteNativeSession(): void
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

function removeRegisterRouteSessionDirectory(
    string $sessionPath
): void {
    if (!is_dir($sessionPath)) {
        return;
    }

    $sessionFiles =
        glob(
            $sessionPath . '/*'
        );

    if (is_array($sessionFiles)) {
        foreach ($sessionFiles as $file) {
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
 *     handlerCalls:ArrayObject
 * }
 */
function buildRegisterRouteTestContext(
    string $sessionPath
): array {
    $session =
        new SessionManager(
            registerRouteSessionConfig(),
            $sessionPath,
            static fn (): int =>
                1_000_000
        );

    $csrf =
        new CsrfTokenManager(
            $session,
            static fn (
                int $length
            ): string => str_repeat(
                "\x55",
                $length
            )
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

    $healthController =
        new HealthController(
            static function (): void {
            }
        );

    $handlerCalls =
        new ArrayObject();

    $registerHandler =
        static function (
            Request $request
        ) use (
            $handlerCalls,
            $session
        ): JsonResponse {
            $handlerCalls->append(
                true
            );

            assertRegisterRouteTrue(
                $session->isStarted(),
                'O handler de registro recebeu a requisição sem sessão ativa.'
            );

            $input =
                $request->json();

            return JsonResponse::success(
                [
                    'received' => $input,
                ],
                201
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
    $routeFactory =
        require dirname(__DIR__)
            . '/routes/http.php';

    $router =
        $routeFactory(
            $healthController,
            $csrfController,
            $sessionMiddleware,
            $csrfMiddleware,
            $registerHandler
        );

    return [
        'router' => $router,
        'session' => $session,
        'handlerCalls' => $handlerCalls,
    ];
}

/**
 * @param callable(): void $test
 */
function runRegisterRouteTest(
    string $name,
    callable $test
): bool {
    resetRegisterRouteNativeSession();

    try {
        $test();

        fwrite(
            STDOUT,
            "[OK] {$name}\n"
        );

        return true;
    } catch (Throwable $exception) {
        fwrite(
            STDERR,
            "[FALHA] {$name}: "
            . $exception->getMessage()
            . "\n"
        );

        return false;
    } finally {
        resetRegisterRouteNativeSession();
    }
}

$tests = [];

$tests[
    'bloqueia registro sem token csrf antes do handler'
] = static function (): void {
    $sessionPath =
        sys_get_temp_dir()
        . '/agenda-register-route-'
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
        $context =
            buildRegisterRouteTestContext(
                $sessionPath
            );

        $response =
            $context['router']->handle(
                Request::create(
                    'POST',
                    '/auth/register',
                    [
                        'Content-Type' =>
                            'application/json',
                    ],
                    json_encode(
                        [
                            'username' =>
                                'register_route_test',
                            'password' =>
                                'senha-valida-de-registro',
                        ],
                        JSON_THROW_ON_ERROR
                    )
                )
            );

        assertRegisterRouteSame(
            403,
            $response->statusCode(),
            'Registro sem CSRF não retornou 403.'
        );

        assertRegisterRouteSame(
            'csrf_invalid',
            $response
                ->payload()['error']['code']
                ?? null,
            'Código de erro CSRF está incorreto.'
        );

        assertRegisterRouteSame(
            0,
            $context['handlerCalls']->count(),
            'Handler de registro foi executado sem CSRF.'
        );

        assertRegisterRouteSame(
            PHP_SESSION_NONE,
            session_status(),
            'Sessão permaneceu aberta após bloqueio CSRF.'
        );
    } finally {
        removeRegisterRouteSessionDirectory(
            $sessionPath
        );
    }
};

$tests[
    'permite registro com token csrf da mesma sessão'
] = static function (): void {
    $sessionPath =
        sys_get_temp_dir()
        . '/agenda-register-route-'
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
        $context =
            buildRegisterRouteTestContext(
                $sessionPath
            );

        $csrfResponse =
            $context['router']->handle(
                Request::create(
                    'GET',
                    '/auth/csrf'
                )
            );

        assertRegisterRouteSame(
            200,
            $csrfResponse->statusCode(),
            'Obtenção de CSRF não retornou 200.'
        );

        $token =
            $csrfResponse
                ->payload()['csrf_token']
                ?? null;

        assertRegisterRouteTrue(
            is_string($token),
            'Rota CSRF não retornou token.'
        );

        $response =
            $context['router']->handle(
                Request::create(
                    'POST',
                    '/auth/register',
                    [
                        'Content-Type' =>
                            'application/json',
                        'X-CSRF-Token' =>
                            $token,
                    ],
                    json_encode(
                        [
                            'username' =>
                                'register_route_test',
                            'password' =>
                                'senha-valida-de-registro',
                        ],
                        JSON_THROW_ON_ERROR
                    )
                )
            );

        assertRegisterRouteSame(
            201,
            $response->statusCode(),
            'Registro com CSRF válido não chegou ao handler.'
        );

        assertRegisterRouteSame(
            1,
            $context['handlerCalls']->count(),
            'Handler de registro não foi executado exatamente uma vez.'
        );

        assertRegisterRouteSame(
            'register_route_test',
            $response
                ->payload()['data']['received']['username']
                ?? null,
            'Payload não chegou corretamente ao handler.'
        );

        assertRegisterRouteSame(
            PHP_SESSION_NONE,
            session_status(),
            'Sessão permaneceu aberta depois do registro.'
        );
    } finally {
        removeRegisterRouteSessionDirectory(
            $sessionPath
        );
    }
};

$tests[
    'json inválido com csrf válido retorna 400 e fecha sessão'
] = static function (): void {
    $sessionPath =
        sys_get_temp_dir()
        . '/agenda-register-route-'
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
        $context =
            buildRegisterRouteTestContext(
                $sessionPath
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

        assertRegisterRouteTrue(
            is_string($token),
            'Não foi possível obter token CSRF.'
        );

        $response =
            $context['router']->handle(
                Request::create(
                    'POST',
                    '/auth/register',
                    [
                        'Content-Type' =>
                            'application/json',
                        'X-CSRF-Token' =>
                            $token,
                    ],
                    '{"username":'
                )
            );

        assertRegisterRouteSame(
            400,
            $response->statusCode(),
            'JSON inválido não retornou 400.'
        );

        assertRegisterRouteSame(
            'invalid_json_body',
            $response
                ->payload()['error']['code']
                ?? null,
            'JSON inválido retornou código incorreto.'
        );

        assertRegisterRouteSame(
            PHP_SESSION_NONE,
            session_status(),
            'Sessão permaneceu aberta após JSON inválido.'
        );
    } finally {
        removeRegisterRouteSessionDirectory(
            $sessionPath
        );
    }
};

$passed = 0;
$total = count(
    $tests
);

foreach ($tests as $name => $test) {
    if (
        runRegisterRouteTest(
            $name,
            $test
        )
    ) {
        ++$passed;
    }
}

fwrite(
    STDOUT,
    "\nRegister route: "
    . "{$passed}/{$total} teste(s) aprovado(s).\n"
);

exit(
    $passed === $total
        ? 0
        : 1
);
