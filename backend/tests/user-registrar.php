<?php

declare(strict_types=1);

use AgendaInteligente\Application\Auth\CredentialValidator;
use AgendaInteligente\Application\Auth\InvalidCredentialsInputException;
use AgendaInteligente\Application\Auth\UserRegistrar;
use AgendaInteligente\Infrastructure\Database\Connection;
use AgendaInteligente\Infrastructure\Persistence\DuplicateUsernameException;
use AgendaInteligente\Infrastructure\Persistence\UserRepository;
use AgendaInteligente\Infrastructure\Security\PasswordHasher;

require_once dirname(__DIR__) . '/autoload.php';

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function assertUserRegistrarSame(
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

function assertUserRegistrarTrue(
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

$registrar =
    new UserRegistrar(
        $repository,
        $hasher,
        $validator
    );

$tests = [];

$tests[
    'registra usuário e retorna identidade mínima'
] = static function () use (
    $registrar
): void {
    $identity =
        $registrar->register(
            'registrar_identity_test',
            'senha-de-registro'
        );

    assertUserRegistrarTrue(
        is_int(
            $identity['id']
            ?? null
        ),
        'Identificador retornado deveria ser inteiro.'
    );

    assertUserRegistrarTrue(
        $identity['id'] > 0,
        'Identificador retornado deveria ser positivo.'
    );

    assertUserRegistrarSame(
        'registrar_identity_test',
        $identity['username']
        ?? null,
        'Username retornado está incorreto.'
    );

    assertUserRegistrarSame(
        false,
        array_key_exists(
            'password',
            $identity
        ),
        'Resultado do registro não deve expor password.'
    );

    assertUserRegistrarSame(
        false,
        array_key_exists(
            'password_hash',
            $identity
        ),
        'Resultado do registro não deve expor password_hash.'
    );
};

$tests[
    'armazena somente hash verificável da senha'
] = static function () use (
    $registrar,
    $repository,
    $hasher
): void {
    $username =
        'registrar_password_test';

    $password =
        'senha-original-do-usuario';

    $identity =
        $registrar->register(
            $username,
            $password
        );

    $user =
        $repository->findById(
            $identity['id']
        );

    assertUserRegistrarTrue(
        $user !== null,
        'Usuário registrado não foi encontrado.'
    );

    $passwordHash =
        $user['password_hash']
        ?? null;

    assertUserRegistrarTrue(
        is_string(
            $passwordHash
        ),
        'Registro deveria persistir um hash de senha.'
    );

    assertUserRegistrarTrue(
        $passwordHash !== $password,
        'Senha não pode ser persistida em texto puro.'
    );

    assertUserRegistrarTrue(
        $hasher->verify(
            $password,
            $passwordHash
        ),
        'Hash persistido não valida a senha original.'
    );
};

$tests[
    'cria usuário ativo'
] = static function () use (
    $registrar,
    $repository
): void {
    $identity =
        $registrar->register(
            'registrar_active_test',
            'senha-de-registro'
        );

    $user =
        $repository->findById(
            $identity['id']
        );

    assertUserRegistrarTrue(
        $user !== null,
        'Usuário registrado não foi encontrado.'
    );

    assertUserRegistrarSame(
        1,
        $user['active']
        ?? null,
        'Novo usuário deveria estar ativo.'
    );
};

$tests[
    'propaga conflito de username duplicado'
] = static function () use (
    $registrar
): void {
    $username =
        'registrar_duplicate_test';

    $registrar->register(
        $username,
        'primeira-senha-valida'
    );

    try {
        $registrar->register(
            $username,
            'segunda-senha-valida'
        );
    } catch (DuplicateUsernameException) {
        return;
    }

    throw new RuntimeException(
        'Era esperada uma DuplicateUsernameException.'
    );
};

$tests[
    'valida credenciais antes de persistir usuário'
] = static function () use (
    $registrar,
    $repository
): void {
    $username =
        'ab';

    try {
        $registrar->register(
            $username,
            'uma-senha-longa-segura'
        );
    } catch (InvalidCredentialsInputException) {
        $user =
            $repository->findByUsername(
                $username
            );

        assertUserRegistrarSame(
            null,
            $user,
            'Usuário com credenciais inválidas não deveria ser persistido.'
        );

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
    "\nUserRegistrar: "
    . "{$passed}/{$total} teste(s) aprovado(s).\n"
);

exit(
    $passed === $total
        ? 0
        : 1
);
