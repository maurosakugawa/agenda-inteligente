<?php

declare(strict_types=1);

use AgendaInteligente\Infrastructure\Database\Connection;
use AgendaInteligente\Infrastructure\Persistence\UserRepository;

require_once dirname(__DIR__) . '/autoload.php';

/**
 * @var array{
 *     config:array<string, mixed>,
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
$application = require dirname(__DIR__)
    . '/bootstrap.php';

$databaseConfig = $application['database'];

$pdo = Connection::make(
    $databaseConfig
);

$currentDatabase = $pdo
    ->query('SELECT DATABASE()')
    ->fetchColumn();

if (
    $currentDatabase
    !== 'agenda_inteligente_test'
) {
    fwrite(
        STDERR,
        "[ERRO] Teste recusado: conexão não está em agenda_inteligente_test.\n"
    );

    exit(1);
}

/**
 * Verifica uma condição booleana.
 */
function assertUserRepositoryTrue(
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
 * Compara dois valores usando comparação estrita.
 */
function assertUserRepositorySame(
    mixed $expected,
    mixed $actual,
    string $message
): void {
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message
            . ' Esperado: '
            . var_export(
                $expected,
                true
            )
            . '; obtido: '
            . var_export(
                $actual,
                true
            )
        );
    }
}

/**
 * Cria um usuário exclusivamente para os testes do repository.
 *
 * O fixture respeita o contrato real da tabela users:
 * - created_at é obrigatório;
 * - updated_at é obrigatório;
 * - deleted_at pode ser NULL;
 * - active pode ser 0 ou 1.
 *
 * Todos os registros criados por esta função ficam dentro da
 * transação aberta pelo teste e serão removidos por rollback.
 *
 * @return array{
 *     id:int,
 *     username:string,
 *     password_hash:string,
 *     active:int
 * }
 */
function createUserRepositoryFixture(
    PDO $pdo,
    string $username,
    int $active = 1,
    bool $deleted = false
): array {
    $passwordHash = password_hash(
        'senha-de-teste',
        PASSWORD_DEFAULT
    );

    assertUserRepositoryTrue(
        is_string($passwordHash),
        'Não foi possível gerar hash para o fixture.'
    );

    $statement = $pdo->prepare(
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
            CASE
                WHEN :deleted = 1
                THEN UTC_TIMESTAMP()
                ELSE NULL
            END
        )
        "
    );

    $statement->execute([
        ':username' => $username,
        ':password_hash' => $passwordHash,
        ':active' => $active,
        ':deleted' => $deleted
            ? 1
            : 0,
    ]);

    return [
        'id' => (int) $pdo->lastInsertId(),
        'username' => $username,
        'password_hash' => $passwordHash,
        'active' => $active,
    ];
}

$tests = [];

$tests[
    'busca usuário por username'
] = static function () use (
    $pdo
): void {
    $fixture =
        createUserRepositoryFixture(
            $pdo,
            'repository_username_test'
        );

    $repository =
        new UserRepository(
            $pdo
        );

    $user =
        $repository->findByUsername(
            $fixture['username']
        );

    assertUserRepositoryTrue(
        is_array($user),
        'Usuário existente não foi encontrado.'
    );

    assertUserRepositorySame(
        $fixture['username'],
        $user['username'] ?? null,
        'Username retornado está incorreto.'
    );

    assertUserRepositorySame(
        $fixture['password_hash'],
        $user['password_hash'] ?? null,
        'Hash armazenado não foi preservado.'
    );

    assertUserRepositorySame(
        1,
        $user['active'] ?? null,
        'Status active retornado está incorreto.'
    );
};

$tests[
    'busca usuário por id'
] = static function () use (
    $pdo
): void {
    $fixture =
        createUserRepositoryFixture(
            $pdo,
            'repository_id_test'
        );

    $repository =
        new UserRepository(
            $pdo
        );

    $user =
        $repository->findById(
            $fixture['id']
        );

    assertUserRepositoryTrue(
        is_array($user),
        'Usuário existente não foi encontrado pelo id.'
    );

    assertUserRepositorySame(
        $fixture['id'],
        $user['id'] ?? null,
        'ID retornado está incorreto.'
    );

    assertUserRepositorySame(
        $fixture['username'],
        $user['username'] ?? null,
        'Username retornado pelo id está incorreto.'
    );

    assertUserRepositorySame(
        $fixture['password_hash'],
        $user['password_hash'] ?? null,
        'Hash retornado pelo id está incorreto.'
    );
};

$tests[
    'retorna null para username inexistente'
] = static function () use (
    $pdo
): void {
    $repository =
        new UserRepository(
            $pdo
        );

    $username =
        'usuario_inexistente_'
        . bin2hex(
            random_bytes(8)
        );

    $user =
        $repository->findByUsername(
            $username
        );

    assertUserRepositorySame(
        null,
        $user,
        'Username inexistente deveria retornar null.'
    );
};

$tests[
    'retorna null para id inexistente'
] = static function () use (
    $pdo
): void {
    $repository =
        new UserRepository(
            $pdo
        );

    $user =
        $repository->findById(
            PHP_INT_MAX
        );

    assertUserRepositorySame(
        null,
        $user,
        'ID inexistente deveria retornar null.'
    );
};

$tests[
    'ignora usuário excluído logicamente'
] = static function () use (
    $pdo
): void {
    $fixture =
        createUserRepositoryFixture(
            $pdo,
            'repository_deleted_test',
            1,
            true
        );

    $repository =
        new UserRepository(
            $pdo
        );

    $userByUsername =
        $repository->findByUsername(
            $fixture['username']
        );

    assertUserRepositorySame(
        null,
        $userByUsername,
        'Usuário excluído não deveria ser encontrado pelo username.'
    );

    $userById =
        $repository->findById(
            $fixture['id']
        );

    assertUserRepositorySame(
        null,
        $userById,
        'Usuário excluído não deveria ser encontrado pelo id.'
    );
};

$tests[
    'retorna usuário inativo para a camada de aplicação decidir'
] = static function () use (
    $pdo
): void {
    $fixture =
        createUserRepositoryFixture(
            $pdo,
            'repository_inactive_test',
            0
        );

    $repository =
        new UserRepository(
            $pdo
        );

    $user =
        $repository->findByUsername(
            $fixture['username']
        );

    assertUserRepositoryTrue(
        is_array($user),
        'Repository não deveria ocultar usuário apenas por estar inativo.'
    );

    assertUserRepositorySame(
        0,
        $user['active'] ?? null,
        'Repository não preservou o estado inativo.'
    );

    assertUserRepositorySame(
        $fixture['username'],
        $user['username'] ?? null,
        'Repository retornou username incorreto para usuário inativo.'
    );
};

$passed = 0;
$total = count($tests);

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
    "\n{$passed}/{$total} testes passaram.\n"
);

exit(
    $passed === $total
        ? 0
        : 1
);
