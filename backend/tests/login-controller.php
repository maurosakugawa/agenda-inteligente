<?php

declare(strict_types=1);

use AgendaInteligente\Application\Auth\AuthenticationSession;
use AgendaInteligente\Application\Auth\CredentialValidator;
use AgendaInteligente\Application\Auth\CredentialVerifier;
use AgendaInteligente\Application\Auth\LoginController;
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
        new LoginController(
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
                )
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
        new LoginController(
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
                )
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
        new LoginController(
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
                )
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

    $controller =
        new LoginController(
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
                            '',
                        'password' =>
                            'senha',
                    ],
                    JSON_THROW_ON_ERROR
                )
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
        $session->establishCalls,
        'Sessão foi criada após falha de validação.'
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
        new LoginController(
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
                )
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
