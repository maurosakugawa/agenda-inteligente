<?php

declare(strict_types=1);

use AgendaInteligente\Application\Auth\AuthenticationSession;
use AgendaInteligente\Application\Auth\CurrentUserProvider;
use AgendaInteligente\Application\Auth\CurrentUserResolver;
use AgendaInteligente\Infrastructure\Database\Connection;
use AgendaInteligente\Infrastructure\Persistence\UserRepository;

require_once dirname(__DIR__) . '/autoload.php';

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function assertCurrentUserResolverSame(
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
function createCurrentUserResolverFixture(
    PDO $pdo,
    string $username,
    int $active = 1,
    ?string $deletedAt = null
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
                :active,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP(),
                :deleted_at
            )
            "
        );

    $statement->execute([
        ':username' => $username,
        ':password_hash' =>
            '$2y$10$CurrentUserResolverTestHash0000000000000000000000000',
        ':active' => $active,
        ':deleted_at' => $deletedAt,
    ]);

    return [
        'id' =>
            (int) $pdo->lastInsertId(),
        'username' =>
            $username,
    ];
}

final class CurrentUserResolverAuthenticationSessionFake
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

    public int $establishCalls = 0;

    public int $currentCalls = 0;

    public int $terminateCalls = 0;

    public function establish(
        array $identity
    ): string {
        ++$this->establishCalls;

        return 'csrf-nao-utilizado';
    }

    public function current(): ?array
    {
        ++$this->currentCalls;

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
    'implementa contrato de resolução do usuário atual'
] = static function () use (
    $repository
): void {
    $session =
        new CurrentUserResolverAuthenticationSessionFake(
            null
        );

    $resolver =
        new CurrentUserResolver(
            $session,
            $repository
        );

    assertCurrentUserResolverSame(
        true,
        $resolver instanceof CurrentUserProvider,
        'CurrentUserResolver não implementa CurrentUserProvider.'
    );
};

$tests[
    'retorna null para sessão anônima sem encerrar sessão'
] = static function () use (
    $repository
): void {
    $session =
        new CurrentUserResolverAuthenticationSessionFake(
            null
        );

    $resolver =
        new CurrentUserResolver(
            $session,
            $repository
        );

    assertCurrentUserResolverSame(
        null,
        $resolver->resolve(),
        'Sessão anônima deveria retornar null.'
    );

    assertCurrentUserResolverSame(
        1,
        $session->currentCalls,
        'Identidade atual não foi consultada exatamente uma vez.'
    );

    assertCurrentUserResolverSame(
        0,
        $session->terminateCalls,
        'Sessão anônima não deveria ser encerrada.'
    );

    assertCurrentUserResolverSame(
        0,
        $session->establishCalls,
        'Resolver não deveria estabelecer autenticação.'
    );
};

$tests[
    'retorna usuário ativo usando dados atuais do banco'
] = static function () use (
    $pdo,
    $repository
): void {
    $fixture =
        createCurrentUserResolverFixture(
            $pdo,
            'current_user_active_test'
        );

    $session =
        new CurrentUserResolverAuthenticationSessionFake(
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

    assertCurrentUserResolverSame(
        [
            'id' =>
                $fixture['id'],
            'username' =>
                $fixture['username'],
        ],
        $resolver->resolve(),
        'Usuário ativo atual está incorreto.'
    );

    assertCurrentUserResolverSame(
        0,
        $session->terminateCalls,
        'Usuário ativo não deveria encerrar a sessão.'
    );
};

$tests[
    'encerra sessão quando usuário não existe'
] = static function () use (
    $repository
): void {
    $session =
        new CurrentUserResolverAuthenticationSessionFake(
            [
                'id' =>
                    PHP_INT_MAX,
                'username' =>
                    'current_user_missing_test',
            ]
        );

    $resolver =
        new CurrentUserResolver(
            $session,
            $repository
        );

    assertCurrentUserResolverSame(
        null,
        $resolver->resolve(),
        'Usuário inexistente deveria retornar null.'
    );

    assertCurrentUserResolverSame(
        1,
        $session->terminateCalls,
        'Sessão de usuário inexistente não foi encerrada exatamente uma vez.'
    );
};

$tests[
    'encerra sessão quando usuário está inativo'
] = static function () use (
    $pdo,
    $repository
): void {
    $fixture =
        createCurrentUserResolverFixture(
            $pdo,
            'current_user_inactive_test',
            0
        );

    $session =
        new CurrentUserResolverAuthenticationSessionFake(
            [
                'id' =>
                    $fixture['id'],
                'username' =>
                    $fixture['username'],
            ]
        );

    $resolver =
        new CurrentUserResolver(
            $session,
            $repository
        );

    assertCurrentUserResolverSame(
        null,
        $resolver->resolve(),
        'Usuário inativo deveria retornar null.'
    );

    assertCurrentUserResolverSame(
        1,
        $session->terminateCalls,
        'Sessão de usuário inativo não foi encerrada exatamente uma vez.'
    );
};

$tests[
    'encerra sessão quando usuário foi excluído logicamente'
] = static function () use (
    $pdo,
    $repository
): void {
    $fixture =
        createCurrentUserResolverFixture(
            $pdo,
            'current_user_deleted_test',
            1,
            '2026-01-01 00:00:00'
        );

    $session =
        new CurrentUserResolverAuthenticationSessionFake(
            [
                'id' =>
                    $fixture['id'],
                'username' =>
                    $fixture['username'],
            ]
        );

    $resolver =
        new CurrentUserResolver(
            $session,
            $repository
        );

    assertCurrentUserResolverSame(
        null,
        $resolver->resolve(),
        'Usuário excluído deveria retornar null.'
    );

    assertCurrentUserResolverSame(
        1,
        $session->terminateCalls,
        'Sessão de usuário excluído não foi encerrada exatamente uma vez.'
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
    "\nCurrentUserResolver: "
    . "{$passed}/{$total} teste(s) aprovado(s).\n"
);

exit(
    $passed === $total
        ? 0
        : 1
);
