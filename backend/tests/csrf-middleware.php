<?php

declare(strict_types=1);

use AgendaInteligente\Infrastructure\Http\JsonResponse;
use AgendaInteligente\Infrastructure\Http\Middleware\CsrfMiddleware;
use AgendaInteligente\Infrastructure\Http\Request;
use AgendaInteligente\Infrastructure\Http\RequestHandlerInterface;
use AgendaInteligente\Infrastructure\Security\CsrfTokenManager;
use AgendaInteligente\Infrastructure\Session\SessionManager;

require_once dirname(__DIR__) . '/autoload.php';

/**
 * @return array{
 *     name:string,
 *     secure:bool,
 *     same_site:string,
 *     idle_timeout:int,
 *     absolute_timeout:int
 * }
 */
function csrfMiddlewareSessionConfig(): array
{
    return [
        'name' => 'AGENDA_INTELIGENTE_CSRF_MIDDLEWARE_TEST',
        'secure' => false,
        'same_site' => 'Lax',
        'idle_timeout' => 1800,
        'absolute_timeout' => 28800,
    ];
}

function assertCsrfMiddlewareTrue(
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
 * @param mixed $expected
 * @param mixed $actual
 */
function assertCsrfMiddlewareSame(
    mixed $expected,
    mixed $actual,
    string $message
): void {
    if ($expected !== $actual) {
        throw new RuntimeException(
            sprintf(
                '%s Esperado: %s. Recebido: %s.',
                $message,
                var_export($expected, true),
                var_export($actual, true)
            )
        );
    }
}

function resetCsrfMiddlewareNativeSession(): void
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

/**
 * @return array{
 *     passed:bool,
 *     name:string,
 *     error:?string
 * }
 */
function runCsrfMiddlewareTest(
    string $name,
    callable $test
): array {
    resetCsrfMiddlewareNativeSession();

    try {
        $test();

        return [
            'passed' => true,
            'name' => $name,
            'error' => null,
        ];
    } catch (Throwable $exception) {
        return [
            'passed' => false,
            'name' => $name,
            'error' => $exception->getMessage(),
        ];
    } finally {
        resetCsrfMiddlewareNativeSession();
    }
}

$temporarySessionPath =
    sys_get_temp_dir()
    . '/agenda-csrf-middleware-tests-'
    . bin2hex(
        random_bytes(8)
    );

if (
    !mkdir(
        $temporarySessionPath,
        0700,
        true
    )
) {
    throw new RuntimeException(
        'Não foi possível criar o diretório temporário de sessões.'
    );
}

$tests = [];

$tests[
    'permite métodos não mutáveis sem validar csrf'
] = static function () use (
    $temporarySessionPath
): void {
    $session = new SessionManager(
        csrfMiddlewareSessionConfig(),
        $temporarySessionPath,
        static fn (): int => 1_000_000
    );

    $session->start();

    $csrf =
        new CsrfTokenManager(
            $session
        );

    $middleware =
        new CsrfMiddleware(
            $csrf
        );

    $calls = 0;

    $handler =
        new class (
            $calls
        ) implements RequestHandlerInterface {
            public function __construct(
                private int &$calls
            ) {
            }

            public function handle(
                Request $request
            ): JsonResponse {
                $this->calls++;

                return JsonResponse::success(
                    [
                        'handled' => true,
                    ]
                );
            }
        };

    foreach (
        [
            'GET',
            'HEAD',
            'OPTIONS',
        ] as $method
    ) {
        $response =
            $middleware->process(
                Request::create(
                    $method,
                    '/teste'
                ),
                $handler
            );

        assertCsrfMiddlewareSame(
            200,
            $response->statusCode(),
            sprintf(
                '%s foi bloqueado indevidamente.',
                $method
            )
        );
    }

    assertCsrfMiddlewareSame(
        3,
        $calls,
        'Nem todos os métodos não mutáveis chegaram ao handler.'
    );

    assertCsrfMiddlewareSame(
        null,
        $csrf->currentToken(),
        'O middleware gerou token CSRF durante método não mutável.'
    );

    $session->destroy();
};

$tests[
    'bloqueia métodos mutáveis sem token'
] = static function () use (
    $temporarySessionPath
): void {
    $session = new SessionManager(
        csrfMiddlewareSessionConfig(),
        $temporarySessionPath,
        static fn (): int => 2_000_000
    );

    $session->start();

    $csrf =
        new CsrfTokenManager(
            $session,
            static fn (
                int $length
            ): string => str_repeat(
                "\x11",
                $length
            )
        );

    $csrf->token();

    $middleware =
        new CsrfMiddleware(
            $csrf
        );

    $calls = 0;

    $handler =
        new class (
            $calls
        ) implements RequestHandlerInterface {
            public function __construct(
                private int &$calls
            ) {
            }

            public function handle(
                Request $request
            ): JsonResponse {
                $this->calls++;

                return JsonResponse::success();
            }
        };

    foreach (
        [
            'POST',
            'PUT',
            'PATCH',
            'DELETE',
        ] as $method
    ) {
        $response =
            $middleware->process(
                Request::create(
                    $method,
                    '/teste'
                ),
                $handler
            );

        assertCsrfMiddlewareSame(
            403,
            $response->statusCode(),
            sprintf(
                '%s sem token CSRF não foi bloqueado.',
                $method
            )
        );

        assertCsrfMiddlewareSame(
            'csrf_invalid',
            $response->payload()['error']['code']
                ?? null,
            'O código do erro CSRF está incorreto.'
        );

        assertCsrfMiddlewareSame(
            'Token CSRF ausente ou inválido.',
            $response->payload()['error']['message']
                ?? null,
            'A mensagem do erro CSRF está incorreta.'
        );
    }

    assertCsrfMiddlewareSame(
        0,
        $calls,
        'Uma requisição sem CSRF chegou ao handler.'
    );

    $session->destroy();
};

$tests[
    'bloqueia token csrf inválido'
] = static function () use (
    $temporarySessionPath
): void {
    $session = new SessionManager(
        csrfMiddlewareSessionConfig(),
        $temporarySessionPath,
        static fn (): int => 3_000_000
    );

    $session->start();

    $csrf =
        new CsrfTokenManager(
            $session,
            static fn (
                int $length
            ): string => str_repeat(
                "\x22",
                $length
            )
        );

    $csrf->token();

    $middleware =
        new CsrfMiddleware(
            $csrf
        );

    $handler =
        new class implements RequestHandlerInterface {
            public function handle(
                Request $request
            ): JsonResponse {
                throw new RuntimeException(
                    'O handler não deveria ser executado.'
                );
            }
        };

    $response =
        $middleware->process(
            Request::create(
                'POST',
                '/teste',
                [
                    'X-CSRF-Token' =>
                        str_repeat(
                            '00',
                            32
                        ),
                ]
            ),
            $handler
        );

    assertCsrfMiddlewareSame(
        403,
        $response->statusCode(),
        'Um token CSRF inválido foi aceito.'
    );

    assertCsrfMiddlewareSame(
        'csrf_invalid',
        $response->payload()['error']['code']
            ?? null,
        'O código do erro está incorreto.'
    );

    $payload =
        $response->payload();

    assertCsrfMiddlewareTrue(
        !str_contains(
            json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE
            ) ?: '',
            $csrf->currentToken()
                ?? ''
        ),
        'A resposta expôs o token CSRF esperado.'
    );

    $session->destroy();
};

$tests[
    'permite método mutável com token válido'
] = static function () use (
    $temporarySessionPath
): void {
    $session = new SessionManager(
        csrfMiddlewareSessionConfig(),
        $temporarySessionPath,
        static fn (): int => 4_000_000
    );

    $session->start();

    $csrf =
        new CsrfTokenManager(
            $session,
            static fn (
                int $length
            ): string => str_repeat(
                "\x33",
                $length
            )
        );

    $token =
        $csrf->token();

    $middleware =
        new CsrfMiddleware(
            $csrf
        );

    $handler =
        new class implements RequestHandlerInterface {
            public function handle(
                Request $request
            ): JsonResponse {
                return JsonResponse::success(
                    [
                        'handled' => true,
                    ]
                );
            }
        };

    $response =
        $middleware->process(
            Request::create(
                'POST',
                '/teste',
                [
                    'X-CSRF-Token' =>
                        $token,
                ]
            ),
            $handler
        );

    assertCsrfMiddlewareSame(
        200,
        $response->statusCode(),
        'Uma requisição com token CSRF válido foi bloqueada.'
    );

    assertCsrfMiddlewareSame(
        true,
        $response->payload()['success']
            ?? null,
        'A resposta do handler foi alterada.'
    );

    assertCsrfMiddlewareSame(
        true,
        $response->payload()['data']['handled']
            ?? null,
        'O handler não foi executado corretamente.'
    );

    $session->destroy();
};

ob_start();

$results = [];

try {
    foreach (
        $tests as $name => $test
    ) {
        $results[] =
            runCsrfMiddlewareTest(
                $name,
                $test
            );
    }
} finally {
    resetCsrfMiddlewareNativeSession();

    $sessionFiles =
        glob(
            $temporarySessionPath
            . '/*'
        );

    if (
        is_array($sessionFiles)
    ) {
        foreach (
            $sessionFiles as $file
        ) {
            if (
                is_file($file)
            ) {
                unlink($file);
            }
        }
    }

    if (
        is_dir(
            $temporarySessionPath
        )
    ) {
        rmdir(
            $temporarySessionPath
        );
    }
}

$unexpectedOutput =
    ob_get_clean();

$passed = 0;

foreach (
    $results as $result
) {
    if ($result['passed']) {
        $passed++;

        fwrite(
            STDOUT,
            sprintf(
                "[OK] %s\n",
                $result['name']
            )
        );

        continue;
    }

    fwrite(
        STDERR,
        sprintf(
            "[FALHA] %s: %s\n",
            $result['name'],
            $result['error']
        )
    );
}

if (
    is_string($unexpectedOutput)
    && $unexpectedOutput !== ''
) {
    fwrite(
        STDERR,
        sprintf(
            "[FALHA] Saída inesperada durante os testes:\n%s\n",
            $unexpectedOutput
        )
    );
}

$total =
    count(
        $results
    );

fwrite(
    STDOUT,
    sprintf(
        "\nResultado: %d/%d testes aprovados.\n",
        $passed,
        $total
    )
);

exit(
    $passed === $total
    && $unexpectedOutput === ''
        ? 0
        : 1
);
