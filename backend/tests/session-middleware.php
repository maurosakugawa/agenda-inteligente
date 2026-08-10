<?php

declare(strict_types=1);

use AgendaInteligente\Infrastructure\Http\JsonResponse;
use AgendaInteligente\Infrastructure\Http\Middleware\SessionMiddleware;
use AgendaInteligente\Infrastructure\Http\Request;
use AgendaInteligente\Infrastructure\Http\RequestHandlerInterface;
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
function middlewareSessionConfig(): array
{
    return [
        'name' => 'AGENDA_INTELIGENTE_MIDDLEWARE_TEST',
        'secure' => false,
        'same_site' => 'Lax',
        'idle_timeout' => 1800,
        'absolute_timeout' => 28800,
    ];
}

function assertSessionMiddlewareTrue(
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
function assertSessionMiddlewareSame(
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

function resetMiddlewareNativeSession(): void
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
function runSessionMiddlewareTest(
    string $name,
    callable $test
): array {
    resetMiddlewareNativeSession();

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
        resetMiddlewareNativeSession();
    }
}

$temporarySessionPath =
    sys_get_temp_dir()
    . '/agenda-session-middleware-tests-'
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
    'inicia sessão antes do handler'
] = static function () use (
    $temporarySessionPath
): void {
    $session = new SessionManager(
        middlewareSessionConfig(),
        $temporarySessionPath,
        static fn (): int => 1_000_000
    );

    $middleware =
        new SessionMiddleware(
            $session
        );

    $handler =
        new class (
            $session
        ) implements RequestHandlerInterface {
            public function __construct(
                private SessionManager $session
            ) {
            }

            public function handle(
                Request $request
            ): JsonResponse {
                assertSessionMiddlewareTrue(
                    $this->session->isStarted(),
                    'O handler recebeu a requisição sem sessão ativa.'
                );

                $this->session->set(
                    'example',
                    'persisted'
                );

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
                'GET',
                '/teste'
            ),
            $handler
        );

    assertSessionMiddlewareSame(
        200,
        $response->statusCode(),
        'A resposta do handler foi alterada.'
    );

    assertSessionMiddlewareSame(
        PHP_SESSION_NONE,
        session_status(),
        'A sessão permaneceu aberta após o handler.'
    );

    $session->start();

    assertSessionMiddlewareSame(
        'persisted',
        $session->get(
            'example'
        ),
        'Os dados não foram persistidos ao fechar a sessão.'
    );

    $session->destroy();
};

$tests[
    'fecha sessão quando handler lança exceção'
] = static function () use (
    $temporarySessionPath
): void {
    $session = new SessionManager(
        middlewareSessionConfig(),
        $temporarySessionPath,
        static fn (): int => 2_000_000
    );

    $middleware =
        new SessionMiddleware(
            $session
        );

    $handler =
        new class implements RequestHandlerInterface {
            public function handle(
                Request $request
            ): JsonResponse {
                assertSessionMiddlewareSame(
                    PHP_SESSION_ACTIVE,
                    session_status(),
                    'A sessão não estava ativa antes da exceção.'
                );

                throw new RuntimeException(
                    'falha-controlada'
                );
            }
        };

    try {
        $middleware->process(
            Request::create(
                'GET',
                '/falha'
            ),
            $handler
        );
    } catch (RuntimeException $exception) {
        assertSessionMiddlewareSame(
            'falha-controlada',
            $exception->getMessage(),
            'A exceção original foi alterada.'
        );

        assertSessionMiddlewareSame(
            PHP_SESSION_NONE,
            session_status(),
            'A sessão permaneceu aberta após uma exceção.'
        );

        return;
    }

    throw new RuntimeException(
        'A exceção do handler não foi propagada.'
    );
};

$tests[
    'tolera sessão destruída pelo handler'
] = static function () use (
    $temporarySessionPath
): void {
    $session = new SessionManager(
        middlewareSessionConfig(),
        $temporarySessionPath,
        static fn (): int => 3_000_000
    );

    $middleware =
        new SessionMiddleware(
            $session
        );

    $handler =
        new class (
            $session
        ) implements RequestHandlerInterface {
            public function __construct(
                private SessionManager $session
            ) {
            }

            public function handle(
                Request $request
            ): JsonResponse {
                $this->session->destroy();

                return JsonResponse::success(
                    [
                        'destroyed' => true,
                    ]
                );
            }
        };

    $response =
        $middleware->process(
            Request::create(
                'POST',
                '/logout-simulado'
            ),
            $handler
        );

    assertSessionMiddlewareSame(
        200,
        $response->statusCode(),
        'A resposta foi alterada após destruir a sessão.'
    );

    assertSessionMiddlewareSame(
        PHP_SESSION_NONE,
        session_status(),
        'A sessão continuou ativa após destroy().'
    );
};

ob_start();

$results = [];

try {
    foreach (
        $tests as $name => $test
    ) {
        $results[] =
            runSessionMiddlewareTest(
                $name,
                $test
            );
    }
} finally {
    resetMiddlewareNativeSession();

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
            if (is_file($file)) {
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
    count($results);

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
