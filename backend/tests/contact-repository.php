<?php

declare(strict_types=1);

use AgendaInteligente\Infrastructure\Database\Connection;
use AgendaInteligente\Infrastructure\Persistence\ContactRepository;

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

$databaseConfig =
    $application['database'];

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
function assertContactRepositoryTrue(
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
function assertContactRepositorySame(
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
 * Cria um usuário exclusivamente para o teste.
 */
function createContactRepositoryUserFixture(
    PDO $pdo,
    string $username
): int {
    $passwordHash = password_hash(
        'senha-de-teste',
        PASSWORD_DEFAULT
    );

    assertContactRepositoryTrue(
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

    $id = (int) $pdo->lastInsertId();

    assertContactRepositoryTrue(
        $id > 0,
        'Usuário fixture não recebeu identificador válido.'
    );

    return $id;
}

/**
 * Cria um contato exclusivamente para o teste.
 */
function createContactRepositoryFixture(
    PDO $pdo,
    int $userId,
    string $name,
    string $createdAt
): int {
    $statement = $pdo->prepare(
        "
        INSERT INTO contacts (
            user_id,
            name,
            phone,
            email,
            cep,
            logradouro,
            numero,
            bairro,
            cidade,
            uf,
            created_at,
            updated_at
        )
        VALUES (
            :user_id,
            :name,
            :phone,
            :email,
            :cep,
            :logradouro,
            :numero,
            :bairro,
            :cidade,
            :uf,
            :created_at,
            :updated_at
        )
        "
    );

    $statement->execute([
        ':user_id' =>
            $userId,
        ':name' =>
            $name,
        ':phone' =>
            '(12) 99999-0000',
        ':email' =>
            strtolower(
                str_replace(
                    ' ',
                    '.',
                    $name
                )
            )
            . '@example.test',
        ':cep' =>
            '12210-000',
        ':logradouro' =>
            'Rua de Teste',
        ':numero' =>
            '100',
        ':bairro' =>
            'Centro',
        ':cidade' =>
            'São José dos Campos',
        ':uf' =>
            'SP',
        ':created_at' =>
            $createdAt,
        ':updated_at' =>
            $createdAt,
    ]);

    $id = (int) $pdo->lastInsertId();

    assertContactRepositoryTrue(
        $id > 0,
        'Contato fixture não recebeu identificador válido.'
    );

    return $id;
}

$tests = [];

$tests[
    'lista somente contatos do usuário'
] = static function () use (
    $pdo
): void {
    $suffix = bin2hex(
        random_bytes(5)
    );

    $userA =
        createContactRepositoryUserFixture(
            $pdo,
            "contact_repository_a_{$suffix}"
        );

    $userB =
        createContactRepositoryUserFixture(
            $pdo,
            "contact_repository_b_{$suffix}"
        );

    createContactRepositoryFixture(
        $pdo,
        $userA,
        'Contato A',
        '2026-08-09 10:00:00'
    );

    createContactRepositoryFixture(
        $pdo,
        $userB,
        'Contato B',
        '2026-08-09 11:00:00'
    );

    $repository =
        new ContactRepository(
            $pdo
        );

    $contacts =
        $repository->findAllByUserId(
            $userA
        );

    assertContactRepositorySame(
        1,
        count($contacts),
        'Repository deveria retornar somente um contato.'
    );

    assertContactRepositorySame(
        $userA,
        $contacts[0]['user_id']
            ?? null,
        'Contato retornado pertence ao usuário incorreto.'
    );

    assertContactRepositorySame(
        'Contato A',
        $contacts[0]['name']
            ?? null,
        'Contato retornado está incorreto.'
    );
};

$tests[
    'ordena contatos por criação decrescente'
] = static function () use (
    $pdo
): void {
    $suffix = bin2hex(
        random_bytes(5)
    );

    $userId =
        createContactRepositoryUserFixture(
            $pdo,
            "contact_repository_order_{$suffix}"
        );

    $oldId =
        createContactRepositoryFixture(
            $pdo,
            $userId,
            'Contato Antigo',
            '2026-08-08 10:00:00'
        );

    $newId =
        createContactRepositoryFixture(
            $pdo,
            $userId,
            'Contato Novo',
            '2026-08-09 10:00:00'
        );

    $repository =
        new ContactRepository(
            $pdo
        );

    $contacts =
        $repository->findAllByUserId(
            $userId
        );

    assertContactRepositorySame(
        2,
        count($contacts),
        'Repository deveria retornar dois contatos.'
    );

    assertContactRepositorySame(
        $newId,
        $contacts[0]['id']
            ?? null,
        'Contato mais recente deveria aparecer primeiro.'
    );

    assertContactRepositorySame(
        $oldId,
        $contacts[1]['id']
            ?? null,
        'Contato mais antigo deveria aparecer depois.'
    );
};

$tests[
    'retorna lista vazia para usuário sem contatos'
] = static function () use (
    $pdo
): void {
    $suffix = bin2hex(
        random_bytes(5)
    );

    $userId =
        createContactRepositoryUserFixture(
            $pdo,
            "contact_repository_empty_{$suffix}"
        );

    $repository =
        new ContactRepository(
            $pdo
        );

    $contacts =
        $repository->findAllByUserId(
            $userId
        );

    assertContactRepositorySame(
        [],
        $contacts,
        'Usuário sem contatos deveria retornar lista vazia.'
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
