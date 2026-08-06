<?php

declare(strict_types=1);

use AgendaInteligente\Application\Health\HealthController;
use AgendaInteligente\Application\HttpKernel;
use AgendaInteligente\Infrastructure\Http\JsonResponse;
use AgendaInteligente\Infrastructure\Http\MiddlewareInterface;
use AgendaInteligente\Infrastructure\Http\Request;
use AgendaInteligente\Infrastructure\Http\RequestHandlerInterface;
use AgendaInteligente\Infrastructure\Http\Router;

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

$tests['despacha rota estática'] = static function (): void {
    $router = new Router();
    $router->get(
        '/teste',
        static fn (Request $request): JsonResponse => JsonResponse::success(
            [
                'path' => $request->path(),
            ]
        )
    );

    $response = $router->handle(
        Request::create(
            'GET',
            '/teste'
        )
    );

    assertHttpSame(
        200,
        $response->statusCode(),
        'A rota estática não foi despachada.'
    );

    assertHttpSame(
        '/teste',
        $response->payload()['data']['path'] ?? null,
        'O handler não recebeu a requisição correta.'
    );
};

$tests['extrai parâmetro de rota'] = static function (): void {
    $router = new Router();
    $router->get(
        '/api/contacts/{id}',
        static fn (Request $request): JsonResponse => JsonResponse::success(
            [
                'id' => $request->routeParam('id'),
            ]
        )
    );

    $response = $router->handle(
        Request::create(
            'GET',
            '/api/contacts/contato%2042'
        )
    );

    assertHttpSame(
        'contato 42',
        $response->payload()['data']['id'] ?? null,
        'O parâmetro de rota não foi extraído ou decodificado.'
    );
};

$tests['retorna 404 para rota desconhecida'] = static function (): void {
    $router = new Router();

    $response = $router->handle(
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

$tests['retorna 405 com todos os métodos permitidos'] = static function (): void {
    $router = new Router();
    $handler = static fn (Request $request): JsonResponse => JsonResponse::success(
        []
    );

    $router->add(
        'PUT',
        '/api/contacts/{id}',
        $handler
    );
    $router->get(
        '/api/contacts/{id}',
        $handler
    );
    $router->add(
        'DELETE',
        '/api/contacts/{id}',
        $handler
    );

    $response = $router->handle(
        Request::create(
            'POST',
            '/api/contacts/10'
        )
    );

    assertHttpSame(
        405,
        $response->statusCode(),
        'O método inválido não retornou 405.'
    );

    assertHttpSame(
        'DELETE, GET, PUT',
        $response->headers()['Allow'] ?? null,
        'O cabeçalho Allow está incorreto.'
    );

    assertHttpSame(
        [
            'DELETE',
            'GET',
            'PUT',
        ],
        $response->payload()['data']['allowed_methods'] ?? null,
        'A lista de métodos permitidos está incorreta.'
    );
};

$tests['executa middlewares em ordem determinística'] = static function (): void {
    $trace = new ArrayObject();

    $first = new class ($trace) implements MiddlewareInterface {
        public function __construct(
            private ArrayObject $trace
        ) {
        }

        public function process(
            Request $request,
            RequestHandlerInterface $next
        ): JsonResponse {
            $this->trace->append(
                'first.before'
            );
            $response = $next->handle(
                $request
            );
            $this->trace->append(
                'first.after'
            );

            return $response;
        }
    };

    $second = new class ($trace) implements MiddlewareInterface {
        public function __construct(
            private ArrayObject $trace
        ) {
        }

        public function process(
            Request $request,
            RequestHandlerInterface $next
        ): JsonResponse {
            $this->trace->append(
                'second.before'
            );
            $response = $next->handle(
                $request
            );
            $this->trace->append(
                'second.after'
            );

            return $response;
        }
    };

    $router = new Router();
    $router->get(
        '/pipeline',
        static function (
            Request $request
        ) use (
            $trace
        ): JsonResponse {
            $trace->append(
                'handler'
            );

            return JsonResponse::success(
                []
            );
        },
        [
            $first,
            $second,
        ]
    );

    $router->handle(
        Request::create(
            'GET',
            '/pipeline'
        )
    );

    assertHttpSame(
        [
            'first.before',
            'second.before',
            'handler',
            'second.after',
            'first.after',
        ],
        $trace->getArrayCopy(),
        'A ordem do pipeline está incorreta.'
    );
};

$tests['middleware pode interromper o pipeline'] = static function (): void {
    $handlerExecutions = new ArrayObject();

    $blockingMiddleware = new class () implements MiddlewareInterface {
        public function process(
            Request $request,
            RequestHandlerInterface $next
        ): JsonResponse {
            return JsonResponse::error(
                'blocked',
                'Requisição bloqueada.',
                403
            );
        }
    };

    $router = new Router();
    $router->get(
        '/bloqueada',
        static function (
            Request $request
        ) use (
            $handlerExecutions
        ): JsonResponse {
            $handlerExecutions->append(
                true
            );

            return JsonResponse::success(
                []
            );
        },
        [
            $blockingMiddleware,
        ]
    );

    $response = $router->handle(
        Request::create(
            'GET',
            '/bloqueada'
        )
    );

    assertHttpSame(
        403,
        $response->statusCode(),
        'O middleware não interrompeu a requisição.'
    );

    assertHttpSame(
        0,
        $handlerExecutions->count(),
        'O handler foi executado após a interrupção.'
    );
};

$tests['rejeita rota duplicada'] = static function (): void {
    $router = new Router();
    $handler = static fn (Request $request): JsonResponse => JsonResponse::success(
        []
    );

    $router->get(
        '/duplicada',
        $handler
    );

    try {
        $router->get(
            '/duplicada/',
            $handler
        );
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException(
        'O roteador aceitou uma rota duplicada.'
    );
};

$tests['rejeita rota estruturalmente duplicada'] = static function (): void {
    $router = new Router();
    $handler = static fn (Request $request): JsonResponse => JsonResponse::success(
        []
    );

    $router->get(
        '/api/items/{id}',
        $handler
    );

    try {
        $router->get(
            '/api/items/{itemId}',
            $handler
        );
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException(
        'O roteador aceitou duas rotas estruturalmente idênticas.'
    );
};

$tests['kernel delega para o roteador'] = static function (): void {
    $router = new Router();
    $router->get(
        '/kernel',
        static fn (Request $request): JsonResponse => JsonResponse::success(
            [
                'delegated' => true,
            ]
        )
    );

    $kernel = new HttpKernel(
        $router
    );
    $response = $kernel->handle(
        Request::create(
            'GET',
            '/kernel'
        )
    );

    assertHttpSame(
        true,
        $response->payload()['data']['delegated'] ?? null,
        'O kernel não delegou a requisição.'
    );
};

$tests['registra as duas rotas de health'] = static function (): void {
    $healthController = new HealthController(
        static function (): void {
        }
    );

    /** @var callable(HealthController): Router $routeFactory */
    $routeFactory = require dirname(__DIR__)
        . '/routes/http.php';
    $router = $routeFactory(
        $healthController
    );

    foreach (
        [
            '/health',
            '/api/health',
        ] as $path
    ) {
        $response = $router->handle(
            Request::create(
                'GET',
                $path
            )
        );

        assertHttpSame(
            200,
            $response->statusCode(),
            sprintf(
                'A rota %s não retornou 200.',
                $path
            )
        );
    }
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
