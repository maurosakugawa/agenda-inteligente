<?php

declare(strict_types=1);

use AgendaInteligente\Application\Auth\CurrentUserProvider;
use AgendaInteligente\Infrastructure\Http\JsonResponse;
use AgendaInteligente\Infrastructure\Http\Middleware\AuthenticationMiddleware;
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
function assertContactsRouteSame(
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

function assertContactsRouteTrue(
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
function contactsRouteSessionConfig(): array
{
    return [
        'name' =>
            'AGENDA_INTELIGENTE_CONTACTS_ROUTE_TEST',
        'secure' => false,
        'same_site' => 'Lax',
        'idle_timeout' => 1800,
        'absolute_timeout' => 28800,
    ];
}

function resetContactsRouteNativeSession(): void
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

function removeContactsRouteSessionDirectory(
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
 *     current_user:object,
 *     calls:ArrayObject
 * }
 */
function buildContactsRouteTestContext(
    string $sessionPath
): array {
    $session =
        new SessionManager(
            contactsRouteSessionConfig(),
            $sessionPath,
            static fn (): int =>
                1_000_000
        );

    $csrf =
        new CsrfTokenManager(
            $session,
            static fn (
                int $length
            ): string =>
                str_repeat(
                    "\x44",
                    $length
                )
        );

    $sessionMiddleware =
        new SessionMiddleware(
            $session
        );

    $currentUser =
        new class(
            $session
        ) implements CurrentUserProvider {
            public bool $authenticated = false;

            public function __construct(
                private SessionManager $session
            ) {
            }

            public function resolve(): ?array
            {
                if (
                    !$this->session->isStarted()
                ) {
                    throw new RuntimeException(
                        'AuthenticationMiddleware executou antes de SessionMiddleware.'
                    );
                }

                if (!$this->authenticated) {
                    return null;
                }

                return [
                    'id' => 42,
                    'username' =>
                        'contacts_route_user',
                ];
            }
        };

    $authenticationMiddleware =
        new AuthenticationMiddleware(
            $currentUser
        );

    $csrfMiddleware =
        new CsrfMiddleware(
            $csrf
        );

    $calls =
        new ArrayObject();

    $handlers = [
        'list' =>
            static function (
                Request $request
            ) use (
                $calls,
                $session
            ): JsonResponse {
                assertContactsRouteTrue(
                    $session->isStarted(),
                    'Handler list recebeu requisição sem sessão ativa.'
                );

                $calls->append([
                    'handler' => 'list',
                    'user_id' =>
                        $request->attribute(
                            'authenticated_user_id'
                        ),
                    'id' =>
                        $request->routeParam(
                            'id'
                        ),
                ]);

                return new JsonResponse(
                    [
                        'handler' => 'list',
                    ],
                    200
                );
            },

        'create' =>
            static function (
                Request $request
            ) use (
                $calls,
                $session
            ): JsonResponse {
                assertContactsRouteTrue(
                    $session->isStarted(),
                    'Handler create recebeu requisição sem sessão ativa.'
                );

                $calls->append([
                    'handler' => 'create',
                    'user_id' =>
                        $request->attribute(
                            'authenticated_user_id'
                        ),
                    'id' =>
                        $request->routeParam(
                            'id'
                        ),
                ]);

                return new JsonResponse(
                    [
                        'handler' => 'create',
                    ],
                    201
                );
            },

        'update' =>
            static function (
                Request $request
            ) use (
                $calls,
                $session
            ): JsonResponse {
                assertContactsRouteTrue(
                    $session->isStarted(),
                    'Handler update recebeu requisição sem sessão ativa.'
                );

                $calls->append([
                    'handler' => 'update',
                    'user_id' =>
                        $request->attribute(
                            'authenticated_user_id'
                        ),
                    'id' =>
                        $request->routeParam(
                            'id'
                        ),
                ]);

                return new JsonResponse(
                    [
                        'handler' => 'update',
                    ],
                    200
                );
            },

        'delete' =>
            static function (
                Request $request
            ) use (
                $calls,
                $session
            ): JsonResponse {
                assertContactsRouteTrue(
                    $session->isStarted(),
                    'Handler delete recebeu requisição sem sessão ativa.'
                );

                $calls->append([
                    'handler' => 'delete',
                    'user_id' =>
                        $request->attribute(
                            'authenticated_user_id'
                        ),
                    'id' =>
                        $request->routeParam(
                            'id'
                        ),
                ]);

                return new JsonResponse(
                    [
                        'handler' => 'delete',
                    ],
                    200
                );
            },
    ];

    /**
     * @var callable(
     *     Router,
     *     SessionMiddleware,
     *     AuthenticationMiddleware,
     *     CsrfMiddleware,
     *     array<string, callable>
     * ): Router $routeRegistrar
     */
    $routeRegistrar =
        require dirname(__DIR__)
            . '/routes/contacts.php';

    $router =
        $routeRegistrar(
            new Router(),
            $sessionMiddleware,
            $authenticationMiddleware,
            $csrfMiddleware,
            $handlers
        );

    return [
        'router' => $router,
        'session' => $session,
        'csrf' => $csrf,
        'current_user' => $currentUser,
        'calls' => $calls,
    ];
}

function issueContactsRouteCsrfToken(
    SessionManager $session,
    CsrfTokenManager $csrf
): string {
    $session->start();

    try {
        return $csrf->token();
    } finally {
        $session->close();
    }
}

/**
 * @param callable(): void $test
 */
function runContactsRouteTest(
    string $name,
    callable $test
): bool {
    resetContactsRouteNativeSession();

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
        resetContactsRouteNativeSession();
    }
}

$tests = [];

$tests[
    'get anônimo retorna 401 antes do handler'
] = static function (): void {
    $sessionPath =
        sys_get_temp_dir()
        . '/agenda-contacts-route-'
        . bin2hex(
            random_bytes(8)
        );

    mkdir(
        $sessionPath,
        0700,
        true
    );

    try {
        $context =
            buildContactsRouteTestContext(
                $sessionPath
            );

        $response =
            $context['router']->handle(
                Request::create(
                    'GET',
                    '/api/contacts'
                )
            );

        assertContactsRouteSame(
            401,
            $response->statusCode(),
            'GET anônimo deveria retornar 401.'
        );

        assertContactsRouteSame(
            0,
            $context['calls']->count(),
            'Handler GET não deveria ser executado anonimamente.'
        );

        assertContactsRouteSame(
            PHP_SESSION_NONE,
            session_status(),
            'Sessão permaneceu aberta após GET anônimo.'
        );
    } finally {
        removeContactsRouteSessionDirectory(
            $sessionPath
        );
    }
};

$tests[
    'post anônimo retorna 401 antes do csrf'
] = static function (): void {
    $sessionPath =
        sys_get_temp_dir()
        . '/agenda-contacts-route-'
        . bin2hex(
            random_bytes(8)
        );

    mkdir(
        $sessionPath,
        0700,
        true
    );

    try {
        $context =
            buildContactsRouteTestContext(
                $sessionPath
            );

        $response =
            $context['router']->handle(
                Request::create(
                    'POST',
                    '/api/contacts'
                )
            );

        assertContactsRouteSame(
            401,
            $response->statusCode(),
            'POST anônimo deveria retornar 401 antes da validação CSRF.'
        );

        assertContactsRouteSame(
            0,
            $context['calls']->count(),
            'Handler POST não deveria ser executado anonimamente.'
        );

        assertContactsRouteSame(
            PHP_SESSION_NONE,
            session_status(),
            'Sessão permaneceu aberta após POST anônimo.'
        );
    } finally {
        removeContactsRouteSessionDirectory(
            $sessionPath
        );
    }
};

$tests[
    'post autenticado sem csrf retorna 403'
] = static function (): void {
    $sessionPath =
        sys_get_temp_dir()
        . '/agenda-contacts-route-'
        . bin2hex(
            random_bytes(8)
        );

    mkdir(
        $sessionPath,
        0700,
        true
    );

    try {
        $context =
            buildContactsRouteTestContext(
                $sessionPath
            );

        $context[
            'current_user'
        ]->authenticated = true;

        $response =
            $context['router']->handle(
                Request::create(
                    'POST',
                    '/api/contacts'
                )
            );

        assertContactsRouteSame(
            403,
            $response->statusCode(),
            'POST autenticado sem CSRF deveria retornar 403.'
        );

        assertContactsRouteSame(
            'csrf_invalid',
            $response
                ->payload()['error']['code']
                ?? null,
            'Código de erro CSRF está incorreto.'
        );

        assertContactsRouteSame(
            0,
            $context['calls']->count(),
            'Handler POST foi executado sem CSRF.'
        );

        assertContactsRouteSame(
            PHP_SESSION_NONE,
            session_status(),
            'Sessão permaneceu aberta após bloqueio CSRF.'
        );
    } finally {
        removeContactsRouteSessionDirectory(
            $sessionPath
        );
    }
};

$tests[
    'post autenticado com csrf chega ao handler'
] = static function (): void {
    $sessionPath =
        sys_get_temp_dir()
        . '/agenda-contacts-route-'
        . bin2hex(
            random_bytes(8)
        );

    mkdir(
        $sessionPath,
        0700,
        true
    );

    try {
        $context =
            buildContactsRouteTestContext(
                $sessionPath
            );

        $context[
            'current_user'
        ]->authenticated = true;

        $token =
            issueContactsRouteCsrfToken(
                $context['session'],
                $context['csrf']
            );

        $response =
            $context['router']->handle(
                Request::create(
                    'POST',
                    '/api/contacts',
                    [
                        'X-CSRF-Token' =>
                            $token,
                    ],
                    '{}'
                )
            );

        assertContactsRouteSame(
            201,
            $response->statusCode(),
            'POST autenticado com CSRF não chegou ao handler.'
        );

        assertContactsRouteSame(
            1,
            $context['calls']->count(),
            'Handler POST deveria executar exatamente uma vez.'
        );

        $call =
            $context['calls'][0];

        assertContactsRouteSame(
            'create',
            $call['handler']
                ?? null,
            'Handler incorreto recebeu POST.'
        );

        assertContactsRouteSame(
            42,
            $call['user_id']
                ?? null,
            'AuthenticationMiddleware não adicionou authenticated_user_id.'
        );

        assertContactsRouteSame(
            PHP_SESSION_NONE,
            session_status(),
            'Sessão permaneceu aberta após POST válido.'
        );
    } finally {
        removeContactsRouteSessionDirectory(
            $sessionPath
        );
    }
};

$tests[
    'get autenticado não exige csrf'
] = static function (): void {
    $sessionPath =
        sys_get_temp_dir()
        . '/agenda-contacts-route-'
        . bin2hex(
            random_bytes(8)
        );

    mkdir(
        $sessionPath,
        0700,
        true
    );

    try {
        $context =
            buildContactsRouteTestContext(
                $sessionPath
            );

        $context[
            'current_user'
        ]->authenticated = true;

        $response =
            $context['router']->handle(
                Request::create(
                    'GET',
                    '/api/contacts'
                )
            );

        assertContactsRouteSame(
            200,
            $response->statusCode(),
            'GET autenticado deveria funcionar sem CSRF.'
        );

        assertContactsRouteSame(
            1,
            $context['calls']->count(),
            'Handler GET deveria executar exatamente uma vez.'
        );

        $call =
            $context['calls'][0];

        assertContactsRouteSame(
            'list',
            $call['handler']
                ?? null,
            'Handler incorreto recebeu GET.'
        );

        assertContactsRouteSame(
            42,
            $call['user_id']
                ?? null,
            'GET não recebeu authenticated_user_id.'
        );

        assertContactsRouteSame(
            PHP_SESSION_NONE,
            session_status(),
            'Sessão permaneceu aberta após GET autenticado.'
        );
    } finally {
        removeContactsRouteSessionDirectory(
            $sessionPath
        );
    }
};

$tests[
    'put encaminha id da rota ao handler'
] = static function (): void {
    $sessionPath =
        sys_get_temp_dir()
        . '/agenda-contacts-route-'
        . bin2hex(
            random_bytes(8)
        );

    mkdir(
        $sessionPath,
        0700,
        true
    );

    try {
        $context =
            buildContactsRouteTestContext(
                $sessionPath
            );

        $context[
            'current_user'
        ]->authenticated = true;

        $token =
            issueContactsRouteCsrfToken(
                $context['session'],
                $context['csrf']
            );

        $response =
            $context['router']->handle(
                Request::create(
                    'PUT',
                    '/api/contacts/123',
                    [
                        'X-CSRF-Token' =>
                            $token,
                    ],
                    '{}'
                )
            );

        assertContactsRouteSame(
            200,
            $response->statusCode(),
            'PUT válido não chegou ao handler.'
        );

        $call =
            $context['calls'][0]
            ?? [];

        assertContactsRouteSame(
            'update',
            $call['handler']
                ?? null,
            'Handler incorreto recebeu PUT.'
        );

        assertContactsRouteSame(
            '123',
            $call['id']
                ?? null,
            'Parâmetro {id} do PUT não foi encaminhado.'
        );

        assertContactsRouteSame(
            42,
            $call['user_id']
                ?? null,
            'PUT não recebeu authenticated_user_id.'
        );

        assertContactsRouteSame(
            PHP_SESSION_NONE,
            session_status(),
            'Sessão permaneceu aberta após PUT.'
        );
    } finally {
        removeContactsRouteSessionDirectory(
            $sessionPath
        );
    }
};

$tests[
    'delete encaminha id da rota ao handler'
] = static function (): void {
    $sessionPath =
        sys_get_temp_dir()
        . '/agenda-contacts-route-'
        . bin2hex(
            random_bytes(8)
        );

    mkdir(
        $sessionPath,
        0700,
        true
    );

    try {
        $context =
            buildContactsRouteTestContext(
                $sessionPath
            );

        $context[
            'current_user'
        ]->authenticated = true;

        $token =
            issueContactsRouteCsrfToken(
                $context['session'],
                $context['csrf']
            );

        $response =
            $context['router']->handle(
                Request::create(
                    'DELETE',
                    '/api/contacts/456',
                    [
                        'X-CSRF-Token' =>
                            $token,
                    ]
                )
            );

        assertContactsRouteSame(
            200,
            $response->statusCode(),
            'DELETE válido não chegou ao handler.'
        );

        $call =
            $context['calls'][0]
            ?? [];

        assertContactsRouteSame(
            'delete',
            $call['handler']
                ?? null,
            'Handler incorreto recebeu DELETE.'
        );

        assertContactsRouteSame(
            '456',
            $call['id']
                ?? null,
            'Parâmetro {id} do DELETE não foi encaminhado.'
        );

        assertContactsRouteSame(
            42,
            $call['user_id']
                ?? null,
            'DELETE não recebeu authenticated_user_id.'
        );

        assertContactsRouteSame(
            PHP_SESSION_NONE,
            session_status(),
            'Sessão permaneceu aberta após DELETE.'
        );
    } finally {
        removeContactsRouteSessionDirectory(
            $sessionPath
        );
    }
};

$passed = 0;
$total = count(
    $tests
);

foreach (
    $tests as $name => $test
) {
    if (
        runContactsRouteTest(
            $name,
            $test
        )
    ) {
        ++$passed;
    }
}

fwrite(
    STDOUT,
    "\nContacts route: "
    . "{$passed}/{$total} teste(s) aprovado(s).\n"
);

exit(
    $passed === $total
        ? 0
        : 1
);
