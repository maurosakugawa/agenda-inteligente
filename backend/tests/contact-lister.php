<?php

declare(strict_types=1);

use AgendaInteligente\Application\Contacts\ContactLister;
use AgendaInteligente\Infrastructure\Database\Connection;
use AgendaInteligente\Infrastructure\Persistence\ContactRepository;

require_once dirname(__DIR__) . '/autoload.php';

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function assertContactListerSame(
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

function createContactListerUserFixture(
    \PDO $pdo,
    string $username
): int {
    $passwordHash =
        password_hash(
            'contact-lister-fixture-password',
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

$lister =
    new ContactLister(
        $repository
    );

$tests = [];

$tests[
    'lista somente contatos do usuário na ordem esperada'
] = static function () use (
    $pdo,
    $repository,
    $lister
): void {
    $suffix =
        bin2hex(
            random_bytes(5)
        );

    $userId =
        createContactListerUserFixture(
            $pdo,
            "contact_lister_owner_{$suffix}"
        );

    $otherUserId =
        createContactListerUserFixture(
            $pdo,
            "contact_lister_other_{$suffix}"
        );

    $firstContactId =
        $repository->create(
            $userId,
            'Contato Mais Antigo'
        );

    /*
     * O repository ordena por created_at DESC.
     * Como DATETIME possui precisão de segundos no schema atual,
     * ajustamos explicitamente os timestamps da fixture para que
     * a ordem testada não dependa da velocidade da execução.
     */
    $statement = $pdo->prepare(
        "
        UPDATE contacts
        SET created_at = :created_at
        WHERE id = :id
        "
    );

    $statement->execute([
        ':created_at' => '2030-01-01 10:00:00',
        ':id' => $firstContactId,
    ]);

    $secondContactId =
        $repository->create(
            $userId,
            'Contato Mais Recente'
        );

    $statement->execute([
        ':created_at' => '2030-01-01 11:00:00',
        ':id' => $secondContactId,
    ]);

    $repository->create(
        $otherUserId,
        'Contato de Outro Usuário'
    );

    $contacts =
        $lister->list(
            $userId
        );

    assertContactListerSame(
        2,
        count($contacts),
        'Listagem deveria conter somente os contatos do usuário.'
    );

    assertContactListerSame(
        $secondContactId,
        $contacts[0]['id'] ?? null,
        'Contato mais recente deveria aparecer primeiro.'
    );

    assertContactListerSame(
        'Contato Mais Recente',
        $contacts[0]['name'] ?? null,
        'Primeiro contato listado está incorreto.'
    );

    assertContactListerSame(
        $firstContactId,
        $contacts[1]['id'] ?? null,
        'Contato mais antigo deveria aparecer depois.'
    );

    foreach ($contacts as $contact) {
        assertContactListerSame(
            $userId,
            $contact['user_id'] ?? null,
            'Listagem retornou contato de outro usuário.'
        );
    }
};

$tests[
    'retorna lista vazia para usuário sem contatos'
] = static function () use (
    $pdo,
    $lister
): void {
    $suffix =
        bin2hex(
            random_bytes(5)
        );

    $userId =
        createContactListerUserFixture(
            $pdo,
            "contact_lister_empty_{$suffix}"
        );

    $contacts =
        $lister->list(
            $userId
        );

    assertContactListerSame(
        [],
        $contacts,
        'Usuário sem contatos deveria receber lista vazia.'
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
    "\nContactLister: "
    . "{$passed}/{$total} teste(s) aprovado(s).\n"
);

exit(
    $passed === $total
        ? 0
        : 1
);
