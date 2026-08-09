<?php

declare(strict_types=1);

use AgendaInteligente\Application\Auth\AuthenticationSession;
use AgendaInteligente\Application\Auth\CurrentUserResolver;
use AgendaInteligente\Application\Auth\MeController;
use AgendaInteligente\Infrastructure\Database\Connection;
use AgendaInteligente\Infrastructure\Persistence\UserRepository;

require_once dirname(__DIR__) . '/autoload.php';

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function assertMeControllerSame(
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

/**
 * @return array{
 *     id:int,
 *     username:string
 * }
 */
function createMeControllerFixture(
    PDO $pdo,
    string $username
): array {
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
            'hash-nao-utilizado-no-teste-me',
    ]);

    return [
        'id' =>
            (int) $pdo->lastInsertId(),
        'username' =>
            $username,
    ];
}

final class MeControllerAuthenticationSessionFake
    implements AuthenticationSession
{
    /**
     * @param array{
     *     id:int,
     *     username:string
     * }|null $identity
     */
    public function __construct(
        private ?array $identity
    ) {
    }

    public int $terminateCalls = 0;

    public function establish(
        array $identity
    ): string {
        return 'csrf-nao-utilizado';
    }

    public function current(): ?array
    {
        return $this->identity;
    }

    public function terminate(): void
    {
        ++$this->terminateCalls;
    }
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

$tests = [];

$tests[
    'retorna usuário autenticado sem wrapper'
] = static function () use (
    $pdo,
    $repository
): void {
    $fixture =
        createMeControllerFixture(
            $pdo,
            'me_controller_active_test'
        );

    $session =
        new MeControllerAuthenticationSessionFake(
            [
                'id' =>
                    $fixture['id'],
                'username' =>
                    'username_antigo_da_sessao',
            ]
        );

    $resolver =
        new CurrentUserResolver(
            $session,
            $repository
        );

    $controller =
        new MeController(
            $resolver
        );

    $response =
        $controller->handle();

    assertMeControllerSame(
        200,
        $response->statusCode(),
        'MeController não retornou HTTP 200.'
    );

    assertMeControllerSame(
        [
            'id' =>
                $fixture['id'],
            'username' =>
                $fixture['username'],
        ],
        $response->payload(),
        'Resposta autenticada não preservou o contrato direto.'
    );

    assertMeControllerSame(
        0,
        $session->terminateCalls,
        'Sessão válida não deveria ser encerrada.'
    );
};

$tests[
    'retorna 401 para sessão sem usuário autenticado'
] = static function () use (
    $repository
): void {
    $session =
        new MeControllerAuthenticationSessionFake(
            null
        );

    $resolver =
        new CurrentUserResolver(
            $session,
            $repository
        );

    $controller =
        new MeController(
            $resolver
        );

    $response =
        $controller->handle();

    assertMeControllerSame(
        401,
        $response->statusCode(),
        'Sessão anônima não retornou HTTP 401.'
    );

    assertMeControllerSame(
        [
            'error' =>
                'Autenticação necessária',
        ],
        $response->payload(),
        'Resposta 401 não preservou o contrato de /auth/me.'
    );

    assertMeControllerSame(
        0,
        $session->terminateCalls,
        'Sessão anônima não deveria ser destruída pelo resolver.'
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
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

fwrite(
    STDOUT,
    "\nMeController: "
    . "{$passed}/{$total} teste(s) aprovado(s).\n"
);

exit(
    $passed === $total
        ? 0
        : 1
);
