<?php

declare(strict_types=1);

use AgendaInteligente\Application\Auth\CredentialValidator;
use AgendaInteligente\Application\Auth\CredentialVerifier;
use AgendaInteligente\Application\Auth\InvalidCredentialsInputException;
use AgendaInteligente\Infrastructure\Database\Connection;
use AgendaInteligente\Infrastructure\Persistence\UserRepository;
use AgendaInteligente\Infrastructure\Security\PasswordHasher;

require_once dirname(__DIR__) . '/autoload.php';

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function assertCredentialVerifierSame(
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

function assertCredentialVerifierTrue(
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
 * Cria um usuário exclusivamente para os testes.
 *
 * @return array{
 *     id:int,
 *     username:string,
 *     password:string
 * }
 */
function createCredentialVerifierFixture(
    PDO $pdo,
    PasswordHasher $hasher,
    string $username,
    string $password,
    int $active = 1,
    ?string $deletedAt = null
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
                :active,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP(),
                :deleted_at
            )
            "
        );

    $statement->execute([
        ':username' => $username,
        ':password_hash' => $passwordHash,
        ':active' => $active,
        ':deleted_at' => $deletedAt,
    ]);

    return [
        'id' => (int) $pdo->lastInsertId(),
        'username' => $username,
        'password' => $password,
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
    'aceita credenciais válidas de usuário ativo'
] = static function () use (
    $pdo,
    $hasher,
    $verifier
): void {
    $fixture =
        createCredentialVerifierFixture(
            $pdo,
            $hasher,
            'credential_valid_test',
            'senha-correta'
        );

    $identity =
        $verifier->verify(
            $fixture['username'],
            $fixture['password']
        );

    assertCredentialVerifierSame(
        [
            'id' => $fixture['id'],
            'username' => $fixture['username'],
        ],
        $identity,
        'Credenciais válidas deveriam retornar a identidade mínima.'
    );

    assertCredentialVerifierTrue(
        !array_key_exists(
            'password_hash',
            $identity
        ),
        'A identidade não deve expor password_hash.'
    );
};

$tests[
    'rejeita senha incorreta'
] = static function () use (
    $pdo,
    $hasher,
    $verifier
): void {
    $fixture =
        createCredentialVerifierFixture(
            $pdo,
            $hasher,
            'credential_wrong_password_test',
            'senha-correta'
        );

    $identity =
        $verifier->verify(
            $fixture['username'],
            'senha-incorreta'
        );

    assertCredentialVerifierSame(
        null,
        $identity,
        'Senha incorreta deveria ser rejeitada.'
    );
};

$tests[
    'rejeita usuário inexistente'
] = static function () use (
    $verifier
): void {
    $identity =
        $verifier->verify(
            'usuario_inexistente_credential_test',
            'qualquer-senha'
        );

    assertCredentialVerifierSame(
        null,
        $identity,
        'Usuário inexistente deveria ser rejeitado.'
    );
};

$tests[
    'rejeita usuário inativo'
] = static function () use (
    $pdo,
    $hasher,
    $verifier
): void {
    $fixture =
        createCredentialVerifierFixture(
            $pdo,
            $hasher,
            'credential_inactive_test',
            'senha-correta',
            0
        );

    $identity =
        $verifier->verify(
            $fixture['username'],
            $fixture['password']
        );

    assertCredentialVerifierSame(
        null,
        $identity,
        'Usuário inativo deveria ser rejeitado.'
    );
};

$tests[
    'rejeita usuário excluído logicamente'
] = static function () use (
    $pdo,
    $hasher,
    $verifier
): void {
    $fixture =
        createCredentialVerifierFixture(
            $pdo,
            $hasher,
            'credential_deleted_test',
            'senha-correta',
            1,
            '2026-01-01 00:00:00'
        );

    $identity =
        $verifier->verify(
            $fixture['username'],
            $fixture['password']
        );

    assertCredentialVerifierSame(
        null,
        $identity,
        'Usuário excluído deveria ser rejeitado.'
    );
};

$tests[
    'rejeita entrada estruturalmente inválida'
] = static function () use (
    $verifier
): void {
    try {
        $verifier->verify(
            '',
            'senha'
        );
    } catch (InvalidCredentialsInputException) {
        return;
    }

    throw new RuntimeException(
        'Era esperada uma InvalidCredentialsInputException.'
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
    "\nCredentialVerifier: "
    . "{$passed}/{$total} teste(s) aprovado(s).\n"
);

exit(
    $passed === $total
        ? 0
        : 1
);
