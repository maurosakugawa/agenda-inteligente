<?php

declare(strict_types=1);

use AgendaInteligente\Application\Auth\CurrentUserProvider;
use AgendaInteligente\Infrastructure\Http\JsonResponse;
use AgendaInteligente\Infrastructure\Http\Middleware\AuthenticationMiddleware;
use AgendaInteligente\Infrastructure\Http\Request;
use AgendaInteligente\Infrastructure\Http\RequestHandlerInterface;

require_once dirname(__DIR__) . '/autoload.php';

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function assertAuthenticationMiddlewareSame(
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

final class AuthenticationMiddlewareCurrentUserFake
    implements CurrentUserProvider
{
    /**
     * @param array{
     *     id:int,
     *     username:string
     * }|null $user
     */
    public function __construct(
        private ?array $user
    ) {
    }

    public int $resolveCalls = 0;

    public function resolve(): ?array
    {
        ++$this->resolveCalls;

        return $this->user;
    }
}

final class AuthenticationMiddlewareHandlerFake
    implements RequestHandlerInterface
{
    public int $handleCalls = 0;

    public ?Request $receivedRequest = null;

    public function handle(
        Request $request
    ): JsonResponse {
        ++$this->handleCalls;

        $this->receivedRequest =
            $request;

        return JsonResponse::success(
            [
                'handled' => true,
            ]
        );
    }
}

$tests = [];

$tests[
    'rejeita requisição sem usuário autenticado'
] = static function (): void {
    $currentUser =
        new AuthenticationMiddlewareCurrentUserFake(
            null
        );

    $handler =
        new AuthenticationMiddlewareHandlerFake();

    $middleware =
        new AuthenticationMiddleware(
            $currentUser
        );

    $response =
        $middleware->process(
            Request::create(
                'GET',
                '/privada'
            ),
            $handler
        );

    assertAuthenticationMiddlewareSame(
        401,
        $response->statusCode(),
        'Requisição anônima não retornou HTTP 401.'
    );

    assertAuthenticationMiddlewareSame(
        [
            'error' =>
                'Autenticação necessária',
        ],
        $response->payload(),
        'Resposta 401 não preservou o contrato de autenticação.'
    );

    assertAuthenticationMiddlewareSame(
        1,
        $currentUser->resolveCalls,
        'Usuário atual não foi resolvido exatamente uma vez.'
    );

    assertAuthenticationMiddlewareSame(
        0,
        $handler->handleCalls,
        'Handler foi executado após falha de autenticação.'
    );
};

$tests[
    'propaga id autenticado autoritativo para próxima camada'
] = static function (): void {
    $currentUser =
        new AuthenticationMiddlewareCurrentUserFake(
            [
                'id' => 42,
                'username' =>
                    'usuario_atual',
            ]
        );

    $handler =
        new AuthenticationMiddlewareHandlerFake();

    $middleware =
        new AuthenticationMiddleware(
            $currentUser
        );

    $request =
        Request::create(
            'GET',
            '/privada'
        )->withAttribute(
            'authenticated_user_id',
            999
        );

    $response =
        $middleware->process(
            $request,
            $handler
        );

    assertAuthenticationMiddlewareSame(
        200,
        $response->statusCode(),
        'Resposta da próxima camada foi alterada.'
    );

    assertAuthenticationMiddlewareSame(
        [
            'success' => true,
            'data' => [
                'handled' => true,
            ],
        ],
        $response->payload(),
        'Resposta do handler não foi preservada.'
    );

    assertAuthenticationMiddlewareSame(
        1,
        $currentUser->resolveCalls,
        'Usuário atual não foi resolvido exatamente uma vez.'
    );

    assertAuthenticationMiddlewareSame(
        1,
        $handler->handleCalls,
        'Handler não foi executado exatamente uma vez.'
    );

    assertAuthenticationMiddlewareSame(
        42,
        $handler
            ->receivedRequest
            ?->attribute(
                'authenticated_user_id'
            ),
        'ID autenticado autoritativo não chegou à próxima camada.'
    );

    assertAuthenticationMiddlewareSame(
        999,
        $request->attribute(
            'authenticated_user_id'
        ),
        'Middleware modificou a requisição original.'
    );
};

$passed = 0;
$total = count(
    $tests
);

foreach (
    $tests as $name => $test
) {
    try {
        $test();

        ++$passed;

        fwrite(
            STDOUT,
            "[OK] {$name}\n"
        );
    } catch (Throwable $exception) {
        fwrite(
            STDERR,
            "[ERRO] {$name}: "
            . $exception->getMessage()
            . "\n"
        );
    }
}

fwrite(
    STDOUT,
    "\nAuthenticationMiddleware: "
    . "{$passed}/{$total} teste(s) aprovado(s).\n"
);

exit(
    $passed === $total
        ? 0
        : 1
);
