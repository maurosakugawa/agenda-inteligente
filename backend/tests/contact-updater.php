<?php

declare(strict_types=1);

use AgendaInteligente\Application\Contacts\ContactNotFoundException;
use AgendaInteligente\Application\Contacts\ContactUpdater;
use AgendaInteligente\Application\Contacts\ContactValidator;
use AgendaInteligente\Application\Contacts\InvalidContactInputException;
use AgendaInteligente\Infrastructure\Database\Connection;
use AgendaInteligente\Infrastructure\Persistence\ContactRepository;

require_once dirname(__DIR__) . '/autoload.php';

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function assertContactUpdaterSame(
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

function assertContactUpdaterTrue(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException(
            $message
        );
    }
}

function createContactUpdaterUserFixture(
    PDO $pdo,
    string $username
): int {
    $passwordHash =
        password_hash(
            'contact-updater-fixture-password',
            PASSWORD_DEFAULT
        );

    if (!is_string($passwordHash)) {
        throw new RuntimeException(
            'Não foi possível gerar hash para fixture de usuário.'
        );
    }

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
        ':username' => $username,
        ':password_hash' => $passwordHash,
    ]);

    $id =
        (int) $pdo->lastInsertId();

    if ($id <= 0) {
        throw new RuntimeException(
            'Fixture de usuário não recebeu identificador válido.'
        );
    }

    return $id;
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
    new ContactRepository(
        $pdo
    );

$validator =
    new ContactValidator();

$updater =
    new ContactUpdater(
        $repository,
        $validator
    );

$tests = [];

$tests[
    'atualiza e retorna contato completo'
] = static function () use (
    $pdo,
    $repository,
    $updater
): void {
    $suffix =
        bin2hex(
            random_bytes(5)
        );

    $userId =
        createContactUpdaterUserFixture(
            $pdo,
            "contact_updater_full_{$suffix}"
        );

    $contactId =
        $repository->create(
            $userId,
            'Nome Original',
            '1111',
            'original@example.test'
        );

    $contact =
        $updater->update(
            $contactId,
            $userId,
            'Nome Atualizado',
            '(12) 99999-8888',
            'novo@example.test',
            '12210-000',
            'Rua Atualizada',
            '250',
            'Centro',
            'São José dos Campos',
            'SP'
        );

    assertContactUpdaterSame(
        $contactId,
        $contact['id'] ?? null,
        'Identificador do contato foi alterado.'
    );

    assertContactUpdaterSame(
        $userId,
        $contact['user_id'] ?? null,
        'user_id retornado está incorreto.'
    );

    assertContactUpdaterSame(
        'Nome Atualizado',
        $contact['name'] ?? null,
        'Nome atualizado está incorreto.'
    );

    assertContactUpdaterSame(
        '(12) 99999-8888',
        $contact['phone'] ?? null,
        'Telefone atualizado está incorreto.'
    );

    assertContactUpdaterSame(
        'novo@example.test',
        $contact['email'] ?? null,
        'E-mail atualizado está incorreto.'
    );

    assertContactUpdaterSame(
        '12210-000',
        $contact['cep'] ?? null,
        'CEP atualizado está incorreto.'
    );

    assertContactUpdaterSame(
        'Rua Atualizada',
        $contact['logradouro'] ?? null,
        'Logradouro atualizado está incorreto.'
    );

    assertContactUpdaterSame(
        '250',
        $contact['numero'] ?? null,
        'Número atualizado está incorreto.'
    );

    assertContactUpdaterSame(
        'Centro',
        $contact['bairro'] ?? null,
        'Bairro atualizado está incorreto.'
    );

    assertContactUpdaterSame(
        'São José dos Campos',
        $contact['cidade'] ?? null,
        'Cidade atualizada está incorreta.'
    );

    assertContactUpdaterSame(
        'SP',
        $contact['uf'] ?? null,
        'UF atualizada está incorreta.'
    );

    assertContactUpdaterTrue(
        is_string(
            $contact['created_at']
            ?? null
        ),
        'created_at deveria ser retornado.'
    );

    assertContactUpdaterTrue(
        is_string(
            $contact['updated_at']
            ?? null
        ),
        'updated_at deveria ser retornado.'
    );
};

$tests[
    'considera sucesso quando dados são idênticos'
] = static function () use (
    $pdo,
    $repository,
    $updater
): void {
    $suffix =
        bin2hex(
            random_bytes(5)
        );

    $userId =
        createContactUpdaterUserFixture(
            $pdo,
            "contact_updater_same_{$suffix}"
        );

    $contactId =
        $repository->create(
            $userId,
            'Contato Idêntico',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            ''
        );

    $contact =
        $updater->update(
            $contactId,
            $userId,
            'Contato Idêntico',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            ''
        );

    assertContactUpdaterSame(
        $contactId,
        $contact['id'] ?? null,
        'Atualização idêntica deveria continuar retornando o contato.'
    );

    assertContactUpdaterSame(
        'Contato Idêntico',
        $contact['name'] ?? null,
        'Nome deveria permanecer inalterado.'
    );
};

$tests[
    'valida antes de persistir atualização'
] = static function () use (
    $pdo,
    $repository,
    $updater
): void {
    $suffix =
        bin2hex(
            random_bytes(5)
        );

    $userId =
        createContactUpdaterUserFixture(
            $pdo,
            "contact_updater_invalid_{$suffix}"
        );

    $contactId =
        $repository->create(
            $userId,
            'Contato Original',
            '1234'
        );

    try {
        $updater->update(
            $contactId,
            $userId,
            ''
        );
    } catch (InvalidContactInputException) {
        $contact =
            $repository->findByIdAndUserId(
                $contactId,
                $userId
            );

        assertContactUpdaterTrue(
            is_array($contact),
            'Contato original deveria continuar existindo.'
        );

        assertContactUpdaterSame(
            'Contato Original',
            $contact['name'],
            'Entrada inválida não deveria alterar o contato.'
        );

        assertContactUpdaterSame(
            '1234',
            $contact['phone'],
            'Entrada inválida alterou dados persistidos.'
        );

        return;
    }

    throw new RuntimeException(
        'Era esperada uma InvalidContactInputException.'
    );
};

$tests[
    'retorna não encontrado para id inexistente'
] = static function () use (
    $pdo,
    $updater
): void {
    $suffix =
        bin2hex(
            random_bytes(5)
        );

    $userId =
        createContactUpdaterUserFixture(
            $pdo,
            "contact_updater_missing_{$suffix}"
        );

    try {
        $updater->update(
            PHP_INT_MAX,
            $userId,
            'Contato Inexistente'
        );
    } catch (ContactNotFoundException $exception) {
        assertContactUpdaterSame(
            'Contato não encontrado.',
            $exception->getMessage(),
            'Mensagem de contato inexistente está incorreta.'
        );

        return;
    }

    throw new RuntimeException(
        'Era esperada uma ContactNotFoundException.'
    );
};

$tests[
    'retorna não encontrado para contato de outro usuário'
] = static function () use (
    $pdo,
    $repository,
    $updater
): void {
    $suffix =
        bin2hex(
            random_bytes(5)
        );

    $ownerId =
        createContactUpdaterUserFixture(
            $pdo,
            "contact_updater_owner_{$suffix}"
        );

    $otherUserId =
        createContactUpdaterUserFixture(
            $pdo,
            "contact_updater_other_{$suffix}"
        );

    $contactId =
        $repository->create(
            $ownerId,
            'Contato Protegido',
            '5678'
        );

    try {
        $updater->update(
            $contactId,
            $otherUserId,
            'Tentativa Indevida'
        );
    } catch (ContactNotFoundException $exception) {
        assertContactUpdaterSame(
            'Contato não encontrado.',
            $exception->getMessage(),
            'Outro usuário deveria receber o mesmo erro de não encontrado.'
        );

        return;
    }

    throw new RuntimeException(
        'Era esperada uma ContactNotFoundException.'
    );
};

$tests[
    'tentativa de outro usuário preserva contato original'
] = static function () use (
    $pdo,
    $repository,
    $updater
): void {
    $suffix =
        bin2hex(
            random_bytes(5)
        );

    $ownerId =
        createContactUpdaterUserFixture(
            $pdo,
            "contact_updater_preserve_owner_{$suffix}"
        );

    $otherUserId =
        createContactUpdaterUserFixture(
            $pdo,
            "contact_updater_preserve_other_{$suffix}"
        );

    $contactId =
        $repository->create(
            $ownerId,
            'Nome Protegido',
            '9999',
            'protegido@example.test'
        );

    try {
        $updater->update(
            $contactId,
            $otherUserId,
            'Nome Indevido',
            '0000',
            'indevido@example.test'
        );
    } catch (ContactNotFoundException) {
        $contact =
            $repository->findByIdAndUserId(
                $contactId,
                $ownerId
            );

        assertContactUpdaterTrue(
            is_array($contact),
            'Contato do proprietário deveria continuar existindo.'
        );

        assertContactUpdaterSame(
            'Nome Protegido',
            $contact['name'],
            'Outro usuário alterou indevidamente o nome.'
        );

        assertContactUpdaterSame(
            '9999',
            $contact['phone'],
            'Outro usuário alterou indevidamente o telefone.'
        );

        assertContactUpdaterSame(
            'protegido@example.test',
            $contact['email'],
            'Outro usuário alterou indevidamente o e-mail.'
        );

        return;
    }

    throw new RuntimeException(
        'Era esperada uma ContactNotFoundException.'
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
    "\nContactUpdater: "
    . "{$passed}/{$total} teste(s) aprovado(s).\n"
);

exit(
    $passed === $total
        ? 0
        : 1
);
