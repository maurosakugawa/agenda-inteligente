<?php

declare(strict_types=1);

use AgendaInteligente\Application\Health\HealthController;
use AgendaInteligente\Application\HttpKernel;
use AgendaInteligente\Infrastructure\Http\JsonResponse;
use AgendaInteligente\Infrastructure\Http\Request;

require_once dirname(__DIR__) . '/autoload.php';

/**
 * @param callable(): void $test
 */
function runHttpTest(
    string $name,
    callable $test
): bool {
    try {
        $test();

        fwrite(
            STDOUT,
            sprintf(
                "[OK] %s\n",
                $name
            )
        );

        return true;
    } catch (Throwable $exception) {
        fwrite(
            STDERR,
            sprintf(
                "[FALHA] %s: %s\n",
                $name,
                $exception->getMessage()
            )
        );

        return false;
    }
}

function assertHttpTrue(
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
function assertHttpSame(
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

$temporaryLog = sys_get_temp_dir()
    . '/agenda-http-tests-'
    . bin2hex(random_bytes(8))
    . '.log';

$previousErrorLog = ini_get(
    'error_log'
);

ini_set(
    'error_log',
    $temporaryLog
);

$tests = [];

$tests['normaliza método e caminho'] = static function (): void {
    $request = Request::create(
        ' get ',
        '/api//health/?origem=teste'
    );

    assertHttpSame(
        'GET',
        $request->method(),
        'O método não foi normalizado.'
    );

    assertHttpSame(
        '/api/health',
        $request->path(),
        'O caminho não foi normalizado.'
    );
};

$tests['usa valores padrão para requisição vazia'] = static function (): void {
    $request = Request::create(
        '',
        ''
    );

    assertHttpSame(
        'GET',
        $request->method(),
        'O método padrão está incorreto.'
    );

    assertHttpSame(
        '/',
        $request->path(),
        'O caminho padrão está incorreto.'
    );
};

$tests['cria resposta JSON de sucesso'] = static function (): void {
    $response = JsonResponse::success(
        [
            'status' => 'ok',
        ]
    );

    assertHttpSame(
        200,
        $response->statusCode(),
        'O status HTTP está incorreto.'
    );

    assertHttpTrue(
        str_contains(
            $response->toJson(),
            '"success":true'
        ),
        'A resposta de sucesso está incorreta.'
    );
};

$tests['cria resposta JSON de erro'] = static function (): void {
    $response = JsonResponse::error(
        'route_not_found',
        'Rota não encontrada.',
        404
    );

    assertHttpSame(
        404,
        $response->statusCode(),
        'O status do erro está incorreto.'
    );

    assertHttpSame(
        'route_not_found',
        $response->payload()['error']['code'] ?? null,
        'O código do erro está incorreto.'
    );
};

$tests['retorna 404 para rota desconhecida'] = static function (): void {
    $healthController = new HealthController(
        static function (): void {
        }
    );

    $kernel = new HttpKernel(
        $healthController
    );

    $response = $kernel->handle(
        Request::create(
            'GET',
            '/rota-inexistente'
        )
    );

    assertHttpSame(
        404,
        $response->statusCode(),
        'A rota desconhecida não retornou 404.'
    );
};

$tests['retorna 405 para método não permitido'] = static function (): void {
    $healthController = new HealthController(
        static function (): void {
        }
    );

    $kernel = new HttpKernel(
        $healthController
    );

    $response = $kernel->handle(
        Request::create(
            'POST',
            '/api/health'
        )
    );

    assertHttpSame(
        405,
        $response->statusCode(),
        'O método inválido não retornou 405.'
    );

    assertHttpSame(
        'GET',
        $response->headers()['Allow'] ?? null,
        'O cabeçalho Allow está incorreto.'
    );
};

$tests['health retorna sucesso com banco disponível'] = static function (): void {
    $healthController = new HealthController(
        static function (): void {
        }
    );

    $response = $healthController->handle();
    $payload = $response->payload();

    assertHttpSame(
        200,
        $response->statusCode(),
        'O health disponível não retornou 200.'
    );

    assertHttpSame(
        'ok',
        $payload['data']['checks']['database']['status'] ?? null,
        'O banco não foi marcado como disponível.'
    );
};

$tests['health retorna 503 sem expor erro técnico'] = static function (): void {
    $healthController = new HealthController(
        static function (): void {
            throw new RuntimeException(
                'detalhe-secreto-do-banco'
            );
        }
    );

    $response = $healthController->handle();
    $json = $response->toJson();

    assertHttpSame(
        503,
        $response->statusCode(),
        'O health degradado não retornou 503.'
    );

    assertHttpTrue(
        !str_contains(
            $json,
            'detalhe-secreto-do-banco'
        ),
        'A resposta expôs o erro técnico.'
    );

    assertHttpSame(
        'unavailable',
        $response->payload()['data']['checks']['database']['status'] ?? null,
        'O banco não foi marcado como indisponível.'
    );
};

$passed = 0;
$total = count($tests);

try {
    foreach ($tests as $name => $test) {
        if (
            runHttpTest(
                $name,
                $test
            )
        ) {
            $passed++;
        }
    }
} finally {
    if (
        is_string($previousErrorLog)
        && $previousErrorLog !== ''
    ) {
        ini_set(
            'error_log',
            $previousErrorLog
        );
    }

    if (is_file($temporaryLog)) {
        unlink(
            $temporaryLog
        );
    }
}

fwrite(
    STDOUT,
    sprintf(
        "\nResultado HTTP: %d/%d testes aprovados.\n",
        $passed,
        $total
    )
);

exit(
    $passed === $total
        ? 0
        : 1
);
