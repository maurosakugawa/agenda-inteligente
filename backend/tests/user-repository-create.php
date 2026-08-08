<?php

declare(strict_types=1);

use AgendaInteligente\Infrastructure\Database\Connection;
use AgendaInteligente\Infrastructure\Persistence\DuplicateUsernameException;
use AgendaInteligente\Infrastructure\Persistence\UserRepository;

require_once dirname(__DIR__) . '/autoload.php';

$application =
    require dirname(__DIR__)
        . '/bootstrap.php';

$databaseConfig =
    $application['database'];

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
    fwrite(
        STDERR,
        "[ERRO] Teste recusado: conexão não está em agenda_inteligente_test.\n"
    );

    exit(1);
}

function assertUserRepositoryCreateTrue(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException(
            $message
        );
    }
}

function assertUserRepositoryCreateSame(
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

$repository =
    new UserRepository(
        $pdo
    );

$tests = [];

$tests[
    'cria usuário e retorna identificador'
] = static function () use (
    $repository
): void {
    $id =
        $repository->create(
            'repository_create_test',
            'hash_repository_create_test'
        );

    assertUserRepositoryCreateTrue(
        $id > 0,
        'Repository deveria retornar um identificador válido.'
    );

    $user =
        $repository->findById(
            $id
        );

    assertUserRepositoryCreateTrue(
        is_array($user),
        'Usuário criado não foi encontrado.'
    );

    assertUserRepositoryCreateSame(
        'repository_create_test',
        $user['username'] ?? null,
        'Username persistido está incorreto.'
    );

    assertUserRepositoryCreateSame(
        'hash_repository_create_test',
        $user['password_hash'] ?? null,
        'Password hash persistido está incorreto.'
    );
};

$tests[
    'cria usuário ativo e não excluído'
] = static function () use (
    $repository
): void {
    $id =
        $repository->create(
            'repository_create_state_test',
            'hash_repository_state_test'
        );

    $user =
        $repository->findById(
            $id
        );

    assertUserRepositoryCreateTrue(
        is_array($user),
        'Usuário criado não foi encontrado.'
    );

    assertUserRepositoryCreateSame(
        1,
        $user['active'] ?? null,
        'Novo usuário deveria estar ativo.'
    );

    assertUserRepositoryCreateSame(
        null,
        $user['deleted_at'] ?? null,
        'Novo usuário não deveria estar excluído.'
    );
};

$tests[
    'preenche timestamps obrigatórios'
] = static function () use (
    $repository
): void {
    $id =
        $repository->create(
            'repository_create_timestamp_test',
            'hash_repository_timestamp_test'
        );

    $user =
        $repository->findById(
            $id
        );

    assertUserRepositoryCreateTrue(
        is_array($user),
        'Usuário criado não foi encontrado.'
    );

    assertUserRepositoryCreateTrue(
        isset($user['created_at'])
        && is_string($user['created_at'])
        && $user['created_at'] !== '',
        'created_at deveria ser preenchido.'
    );

    assertUserRepositoryCreateTrue(
        isset($user['updated_at'])
        && is_string($user['updated_at'])
        && $user['updated_at'] !== '',
        'updated_at deveria ser preenchido.'
    );
};

$tests[
    'rejeita username duplicado com exceção específica'
] = static function () use (
    $repository
): void {
    $username =
        'repository_duplicate_test';

    $repository->create(
        $username,
        'primeiro_hash'
    );

    try {
        $repository->create(
            $username,
            'segundo_hash'
        );
    } catch (DuplicateUsernameException) {
        return;
    }

    throw new RuntimeException(
        'Era esperada uma DuplicateUsernameException.'
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
    "\nUserRepository create: {$passed}/{$total} teste(s) aprovado(s).\n"
);

exit(
    $passed === $total
        ? 0
        : 1
);
