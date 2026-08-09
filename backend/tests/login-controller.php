<?php

declare(strict_types=1);

use AgendaInteligente\Application\Auth\AuthenticationSession;
use AgendaInteligente\Application\Auth\CredentialValidator;
use AgendaInteligente\Application\Auth\CredentialVerifier;
use AgendaInteligente\Application\Auth\LoginController;
use AgendaInteligente\Application\Auth\LoginRateLimiter;
use AgendaInteligente\Application\Auth\RateLimitRepository;
use AgendaInteligente\Infrastructure\Database\Connection;
use AgendaInteligente\Infrastructure\Http\Request;
use AgendaInteligente\Infrastructure\Persistence\UserRepository;
use AgendaInteligente\Infrastructure\Security\PasswordHasher;

require_once dirname(__DIR__) . '/autoload.php';

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function assertLoginControllerSame(
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

function assertLoginControllerTrue(
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
 * @param callable(): void $test
 */
function runLoginControllerTest(
    string $name,
    callable $test
): bool {
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
    }
}

final class LoginControllerAuthenticationSessionFake
    implements AuthenticationSession
{
    public int $establishCalls = 0;

    /**
     * @var array{
     *     id:int,
     *     username:string
     * }|null
     */
    public ?array $lastIdentity = null;

    public function __construct(
        private string $csrfToken
    ) {
    }

    public function establish(
        array $identity
    ): string {
        ++$this->establishCalls;

        $this->lastIdentity =
            $identity;

        return $this->csrfToken;
    }

    public function terminate(): void
    {
    }

}

final class LoginControllerRateLimitRepositoryFake
    implements RateLimitRepository
{
    /** @var list<array{scope:string,key_hash:string,now:int}> */
    public array $blockedUntilCalls = [];

    /**
     * @var list<array{
     *     scope:string,
     *     key_hash:string,
     *     max_attempts:int,
     *     window_seconds:int,
     *     block_seconds:int,
     *     now:int
     * }>
     */
    public array $recordAttemptCalls = [];

    /** @var list<array{scope:string,key_hash:string}> */
    public array $clearCalls = [];

    /** @var array<string, ?int> */
    public array $blockedResponses = [];

    /** @var array<string, ?int> */
    public array $recordResponses = [];

    public function blockedUntil(
        string $scope,
        string $keyHash,
        int $now
    ): ?int {
        $this->blockedUntilCalls[] = [
            'scope' => $scope,
            'key_hash' => $keyHash,
            'now' => $now,
        ];

        return $this->blockedResponses[
            $scope
        ] ?? null;
    }

    public function recordAttempt(
        string $scope,
        string $keyHash,
        int $maxAttempts,
        int $windowSeconds,
        int $blockSeconds,
        int $now
    ): ?int {
        $this->recordAttemptCalls[] = [
            'scope' => $scope,
            'key_hash' => $keyHash,
            'max_attempts' =>
                $maxAttempts,
            'window_seconds' =>
                $windowSeconds,
            'block_seconds' =>
                $blockSeconds,
            'now' => $now,
        ];

        return $this->recordResponses[
            $scope
        ] ?? null;
    }

    public function clear(
        string $scope,
        string $keyHash
    ): void {
        $this->clearCalls[] = [
            'scope' => $scope,
            'key_hash' => $keyHash,
        ];
    }
}

/**
 * @return array{
 *     key_secret:string,
 *     ip:array{
 *         max_attempts:int,
 *         window_seconds:int,
 *         block_seconds:int
 *     },
 *     username_ip:array{
 *         max_failures:int,
 *         window_seconds:int,
 *         block_seconds:int
 *     }
 * }
 */
function loginControllerRateLimitConfig(): array
{
    return [
        'key_secret' =>
            '0123456789abcdef0123456789abcdef',

        'ip' => [
            'max_attempts' => 20,
            'window_seconds' => 300,
            'block_seconds' => 900,
        ],

        'username_ip' => [
            'max_failures' => 5,
            'window_seconds' => 900,
            'block_seconds' => 900,
        ],
    ];
}

function loginControllerTestNow(): int
{
    return 1_800_010_000;
}

function createLoginControllerUnderTest(
    CredentialVerifier $verifier,
    AuthenticationSession $session,
    ?LoginControllerRateLimitRepositoryFake $repository = null,
    ?int $now = null
): LoginController {
    $repository =
        $repository
        ?? new LoginControllerRateLimitRepositoryFake();

    $now =
        $now
        ?? loginControllerTestNow();

    $rateLimiter =
        new LoginRateLimiter(
            $repository,
            loginControllerRateLimitConfig(),
            static fn (): int => $now
        );

    return new LoginController(
        $verifier,
        new CredentialValidator(),
        $rateLimiter,
        $session,
        static fn (): int => $now
    );
}

/**
 * @return array{
 *     id:int,
 *     username:string,
 *     password:string
 * }
 */
function createLoginControllerFixture(
    PDO $pdo,
    PasswordHasher $hasher,
    string $username,
    string $password
): array {
    $passwordHash =
        $hasher->hash(
            $password
        );

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
                1,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP(),
                NULL
            )
            "
        );

    $statement->execute([
        ':username' =>
            $username,
        ':password_hash' =>
            $passwordHash,
    ]);

    return [
        'id' =>
            (int) $pdo->lastInsertId(),
        'username' =>
            $username,
        'password' =>
            $password,
    ];
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

$repository =
    new UserRepository(
        $pdo
    );

$hasher =
    new PasswordHasher();

$validator =
    new CredentialValidator();

$verifier =
    new CredentialVerifier(
        $repository,
        $hasher,
        $validator
    );

$tests = [];

$tests[
    'realiza login e retorna identidade com novo csrf'
] = static function () use (
    $pdo,
    $hasher,
    $verifier
): void {
    $fixture =
        createLoginControllerFixture(
            $pdo,
            $hasher,
            'login_controller_success_test',
            'senha-correta-login'
        );

    $session =
        new LoginControllerAuthenticationSessionFake(
            str_repeat(
                'a',
                64
            )
        );

    $controller =
        createLoginControllerUnderTest(
            $verifier,
            $session
        );

    $response =
        $controller->handle(
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
                            $fixture['username'],
                        'password' =>
                            $fixture['password'],
                    ],
                    JSON_THROW_ON_ERROR
                ),
                '203.0.113.10'
            )
        );

    assertLoginControllerSame(
        200,
        $response->statusCode(),
        'Login válido não retornou HTTP 200.'
    );

    assertLoginControllerSame(
        true,
        $response->payload()['success']
            ?? null,
        'Login válido não retornou success=true.'
    );

    assertLoginControllerSame(
        'Login realizado',
        $response
            ->payload()['data']['message']
            ?? null,
        'Mensagem de sucesso está incorreta.'
    );

    assertLoginControllerSame(
        [
            'id' => $fixture['id'],
            'username' => $fixture['username'],
        ],
        $response
            ->payload()['data']['user']
            ?? null,
        'Identidade retornada está incorreta.'
    );

    assertLoginControllerSame(
        str_repeat(
            'a',
            64
        ),
        $response
            ->payload()['data']['csrf_token']
            ?? null,
        'Novo token CSRF não foi retornado.'
    );

    assertLoginControllerSame(
        1,
        $session->establishCalls,
        'Sessão autenticada não foi estabelecida exatamente uma vez.'
    );

    assertLoginControllerSame(
        [
            'id' => $fixture['id'],
            'username' => $fixture['username'],
        ],
        $session->lastIdentity,
        'Identidade incorreta foi enviada à sessão.'
    );

    $payloadJson =
        $response->toJson();

    assertLoginControllerTrue(
        !str_contains(
            $payloadJson,
            'password_hash'
        ),
        'A resposta expôs password_hash.'
    );

    assertLoginControllerTrue(
        !str_contains(
            $payloadJson,
            $fixture['password']
        ),
        'A resposta expôs a senha.'
    );
};

$tests[
    'rejeita campos obrigatórios ausentes'
] = static function () use (
    $verifier
): void {
    $session =
        new LoginControllerAuthenticationSessionFake(
            'csrf-nao-utilizado'
        );

    $controller =
        createLoginControllerUnderTest(
            $verifier,
            $session
        );

    $response =
        $controller->handle(
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
                            'usuario',
                    ],
                    JSON_THROW_ON_ERROR
                ),
                '203.0.113.10'
            )
        );

    assertLoginControllerSame(
        400,
        $response->statusCode(),
        'Campo obrigatório ausente não retornou 400.'
    );

    assertLoginControllerSame(
        'invalid_credentials_input',
        $response
            ->payload()['error']['code']
            ?? null,
        'Código de erro para campo ausente está incorreto.'
    );

    assertLoginControllerSame(
        0,
        $session->establishCalls,
        'Sessão foi criada com entrada incompleta.'
    );
};

$tests[
    'rejeita tipo inválido sem provocar TypeError'
] = static function () use (
    $verifier
): void {
    $session =
        new LoginControllerAuthenticationSessionFake(
            'csrf-nao-utilizado'
        );

    $controller =
        createLoginControllerUnderTest(
            $verifier,
            $session
        );

    $response =
        $controller->handle(
            Request::create(
                'POST',
                '/auth/login',
                [
                    'Content-Type' =>
                        'application/json',
                ],
                json_encode(
                    [
                        'username' => 123,
                        'password' => [
                            'senha',
                        ],
                    ],
                    JSON_THROW_ON_ERROR
                ),
                '203.0.113.10'
            )
        );

    assertLoginControllerSame(
        400,
        $response->statusCode(),
        'Tipo inválido não retornou 400.'
    );

    assertLoginControllerSame(
        'invalid_credentials_input',
        $response
            ->payload()['error']['code']
            ?? null,
        'Código de erro para tipo inválido está incorreto.'
    );

    assertLoginControllerSame(
        0,
        $session->establishCalls,
        'Sessão foi criada com tipos inválidos.'
    );
};

$tests[
    'traduz validação estrutural para HTTP 400'
] = static function () use (
    $verifier
): void {
    $session =
        new LoginControllerAuthenticationSessionFake(
            'csrf-nao-utilizado'
        );

    $repository =
        new LoginControllerRateLimitRepositoryFake();

    $controller =
        createLoginControllerUnderTest(
            $verifier,
            $session,
            $repository
        );

    $response =
        $controller->handle(
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
                            '',
                        'password' =>
                            'senha',
                    ],
                    JSON_THROW_ON_ERROR
                ),
                '203.0.113.10'
            )
        );

    assertLoginControllerSame(
        400,
        $response->statusCode(),
        'Entrada estruturalmente inválida não retornou 400.'
    );

    assertLoginControllerSame(
        'invalid_credentials_input',
        $response
            ->payload()['error']['code']
            ?? null,
        'Validação estrutural retornou código incorreto.'
    );

    assertLoginControllerSame(
        0,
        count(
            $repository
                ->blockedUntilCalls
        ),
        'Entrada estruturalmente inválida consultou bloqueio.'
    );

    assertLoginControllerSame(
        0,
        count(
            $repository
                ->recordAttemptCalls
        ),
        'Entrada estruturalmente inválida consumiu rate limit.'
    );

    assertLoginControllerSame(
        0,
        count(
            $repository->clearCalls
        ),
        'Entrada estruturalmente inválida alterou buckets.'
    );

    assertLoginControllerSame(
        0,
        $session->establishCalls,
        'Sessão foi criada após falha de validação.'
    );
};

$tests[
    'rejeita login estruturalmente válido sem endereço remoto'
] = static function () use (
    $verifier
): void {
    $session =
        new LoginControllerAuthenticationSessionFake(
            'csrf-nao-utilizado'
        );

    $repository =
        new LoginControllerRateLimitRepositoryFake();

    $controller =
        createLoginControllerUnderTest(
            $verifier,
            $session,
            $repository
        );

    $response =
        $controller->handle(
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
                            'usuario-sem-origem',
                        'password' =>
                            'senha-valida-login',
                    ],
                    JSON_THROW_ON_ERROR
                )
            )
        );

    assertLoginControllerSame(
        503,
        $response->statusCode(),
        'Ausência de endereço remoto não retornou 503.'
    );

    assertLoginControllerSame(
        'service_unavailable',
        $response
            ->payload()['error']['code']
            ?? null,
        'Código para ausência de origem está incorreto.'
    );

    assertLoginControllerSame(
        0,
        count(
            $repository
                ->blockedUntilCalls
        ),
        'Rate limiter foi consultado sem origem remota.'
    );

    assertLoginControllerSame(
        0,
        $session->establishCalls,
        'Sessão foi estabelecida sem origem remota.'
    );
};

$tests[
    'bloqueio prévio retorna 429 com Retry-After'
] = static function () use (
    $verifier
): void {
    $now =
        loginControllerTestNow();

    $session =
        new LoginControllerAuthenticationSessionFake(
            'csrf-nao-utilizado'
        );

    $repository =
        new LoginControllerRateLimitRepositoryFake();

    $repository->blockedResponses[
        'login_ip'
    ] = $now + 120;

    $controller =
        createLoginControllerUnderTest(
            $verifier,
            $session,
            $repository,
            $now
        );

    $response =
        $controller->handle(
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
                            'usuario-bloqueado',
                        'password' =>
                            'senha-valida-login',
                    ],
                    JSON_THROW_ON_ERROR
                ),
                '198.51.100.20'
            )
        );

    assertLoginControllerSame(
        429,
        $response->statusCode(),
        'Bloqueio prévio não retornou 429.'
    );

    assertLoginControllerSame(
        'login_rate_limited',
        $response
            ->payload()['error']['code']
            ?? null,
        'Código do rate limiting está incorreto.'
    );

    assertLoginControllerSame(
        '120',
        $response
            ->headers()['Retry-After']
            ?? null,
        'Retry-After do bloqueio prévio está incorreto.'
    );

    assertLoginControllerSame(
        2,
        count(
            $repository
                ->blockedUntilCalls
        ),
        'Os dois buckets não foram consultados.'
    );

    assertLoginControllerSame(
        0,
        count(
            $repository
                ->recordAttemptCalls
        ),
        'Tentativa foi registrada apesar de bloqueio prévio.'
    );

    assertLoginControllerSame(
        0,
        $session->establishCalls,
        'Sessão foi estabelecida durante bloqueio.'
    );
};

$tests[
    'bloqueio concorrente no bucket de IP interrompe verificação'
] = static function () use (
    $verifier
): void {
    $now =
        loginControllerTestNow();

    $session =
        new LoginControllerAuthenticationSessionFake(
            'csrf-nao-utilizado'
        );

    $repository =
        new LoginControllerRateLimitRepositoryFake();

    $repository->recordResponses[
        'login_ip'
    ] = $now + 90;

    $controller =
        createLoginControllerUnderTest(
            $verifier,
            $session,
            $repository,
            $now
        );

    $response =
        $controller->handle(
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
                            'usuario-corrida-ip',
                        'password' =>
                            'senha-valida-login',
                    ],
                    JSON_THROW_ON_ERROR
                ),
                '198.51.100.21'
            )
        );

    assertLoginControllerSame(
        429,
        $response->statusCode(),
        'Bloqueio concorrente por IP não retornou 429.'
    );

    assertLoginControllerSame(
        '90',
        $response
            ->headers()['Retry-After']
            ?? null,
        'Retry-After concorrente por IP está incorreto.'
    );

    assertLoginControllerSame(
        1,
        count(
            $repository
                ->recordAttemptCalls
        ),
        'Deveria registrar somente a tentativa geral de IP.'
    );

    assertLoginControllerSame(
        'login_ip',
        $repository
            ->recordAttemptCalls[0]['scope']
            ?? null,
        'Bucket incorreto foi registrado.'
    );

    assertLoginControllerSame(
        0,
        $session->establishCalls,
        'Sessão foi estabelecida após bloqueio concorrente.'
    );
};

$tests[
    'falha de credenciais registra IP e username com IP'
] = static function () use (
    $verifier
): void {
    $session =
        new LoginControllerAuthenticationSessionFake(
            'csrf-nao-utilizado'
        );

    $repository =
        new LoginControllerRateLimitRepositoryFake();

    $controller =
        createLoginControllerUnderTest(
            $verifier,
            $session,
            $repository
        );

    $username =
        'rate_failure_'
        . bin2hex(
            random_bytes(6)
        );

    $response =
        $controller->handle(
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
                            $username,
                        'password' =>
                            'senha-incorreta',
                    ],
                    JSON_THROW_ON_ERROR
                ),
                '192.0.2.40'
            )
        );

    assertLoginControllerSame(
        401,
        $response->statusCode(),
        'Falha comum de credenciais não retornou 401.'
    );

    assertLoginControllerSame(
        2,
        count(
            $repository
                ->recordAttemptCalls
        ),
        'Falha deveria registrar dois buckets.'
    );

    assertLoginControllerSame(
        'login_ip',
        $repository
            ->recordAttemptCalls[0]['scope']
            ?? null,
        'Primeiro registro não foi no bucket geral de IP.'
    );

    assertLoginControllerSame(
        'login_username_ip',
        $repository
            ->recordAttemptCalls[1]['scope']
            ?? null,
        'Falha não foi registrada no bucket username+IP.'
    );

    assertLoginControllerSame(
        0,
        count(
            $repository->clearCalls
        ),
        'Falha de credenciais limpou bucket indevidamente.'
    );
};

$tests[
    'bloqueio concorrente após falha retorna 429'
] = static function () use (
    $verifier
): void {
    $now =
        loginControllerTestNow();

    $session =
        new LoginControllerAuthenticationSessionFake(
            'csrf-nao-utilizado'
        );

    $repository =
        new LoginControllerRateLimitRepositoryFake();

    $repository->recordResponses[
        'login_username_ip'
    ] = $now + 75;

    $controller =
        createLoginControllerUnderTest(
            $verifier,
            $session,
            $repository,
            $now
        );

    $username =
        'rate_race_'
        . bin2hex(
            random_bytes(6)
        );

    $response =
        $controller->handle(
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
                            $username,
                        'password' =>
                            'senha-incorreta',
                    ],
                    JSON_THROW_ON_ERROR
                ),
                '192.0.2.41'
            )
        );

    assertLoginControllerSame(
        429,
        $response->statusCode(),
        'Bloqueio concorrente após falha não retornou 429.'
    );

    assertLoginControllerSame(
        '75',
        $response
            ->headers()['Retry-After']
            ?? null,
        'Retry-After após falha está incorreto.'
    );

    assertLoginControllerSame(
        2,
        count(
            $repository
                ->recordAttemptCalls
        ),
        'Fluxo de falha concorrente deveria tocar os dois buckets.'
    );

    assertLoginControllerSame(
        0,
        $session->establishCalls,
        'Sessão foi estabelecida após falha bloqueada.'
    );
};

$tests[
    'login bem sucedido limpa somente falhas de username e IP'
] = static function () use (
    $pdo,
    $hasher,
    $verifier
): void {
    $fixture =
        createLoginControllerFixture(
            $pdo,
            $hasher,
            'login_controller_rate_success_test',
            'senha-correta-login'
        );

    $session =
        new LoginControllerAuthenticationSessionFake(
            str_repeat(
                'b',
                64
            )
        );

    $repository =
        new LoginControllerRateLimitRepositoryFake();

    $controller =
        createLoginControllerUnderTest(
            $verifier,
            $session,
            $repository
        );

    $response =
        $controller->handle(
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
                            $fixture['username'],
                        'password' =>
                            $fixture['password'],
                    ],
                    JSON_THROW_ON_ERROR
                ),
                '192.0.2.42'
            )
        );

    assertLoginControllerSame(
        200,
        $response->statusCode(),
        'Login válido com limiter não retornou 200.'
    );

    assertLoginControllerSame(
        1,
        count(
            $repository
                ->recordAttemptCalls
        ),
        'Sucesso deveria registrar somente o bucket geral de IP.'
    );

    assertLoginControllerSame(
        'login_ip',
        $repository
            ->recordAttemptCalls[0]['scope']
            ?? null,
        'Sucesso registrou bucket incorreto.'
    );

    assertLoginControllerSame(
        1,
        count(
            $repository->clearCalls
        ),
        'Sucesso deveria limpar exatamente um bucket.'
    );

    assertLoginControllerSame(
        'login_username_ip',
        $repository
            ->clearCalls[0]['scope']
            ?? null,
        'Sucesso não limpou o bucket username+IP.'
    );

    assertLoginControllerSame(
        1,
        $session->establishCalls,
        'Sessão válida não foi estabelecida.'
    );
};

$tests[
    'retorna 401 genérico para credenciais inválidas'
] = static function () use (
    $pdo,
    $hasher,
    $verifier
): void {
    $fixture =
        createLoginControllerFixture(
            $pdo,
            $hasher,
            'login_controller_invalid_test',
            'senha-correta-login'
        );

    $session =
        new LoginControllerAuthenticationSessionFake(
            'csrf-nao-utilizado'
        );

    $controller =
        createLoginControllerUnderTest(
            $verifier,
            $session
        );

    $response =
        $controller->handle(
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
                            $fixture['username'],
                        'password' =>
                            'senha-incorreta',
                    ],
                    JSON_THROW_ON_ERROR
                ),
                '203.0.113.10'
            )
        );

    assertLoginControllerSame(
        401,
        $response->statusCode(),
        'Credenciais inválidas não retornaram 401.'
    );

    assertLoginControllerSame(
        'invalid_credentials',
        $response
            ->payload()['error']['code']
            ?? null,
        'Código para credenciais inválidas está incorreto.'
    );

    assertLoginControllerSame(
        'Usuário ou senha inválidos.',
        $response
            ->payload()['error']['message']
            ?? null,
        'Mensagem de credenciais inválidas está incorreta.'
    );

    assertLoginControllerSame(
        0,
        $session->establishCalls,
        'Sessão foi criada com credenciais inválidas.'
    );
};

$passed = 0;
$total = count(
    $tests
);

$pdo->beginTransaction();

try {
    foreach (
        $tests as $name => $test
    ) {
        if (
            runLoginControllerTest(
                $name,
                $test
            )
        ) {
            ++$passed;
        }
    }
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

fwrite(
    STDOUT,
    "\nLoginController: "
    . "{$passed}/{$total} teste(s) aprovado(s).\n"
);

exit(
    $passed === $total
        ? 0
        : 1
);
