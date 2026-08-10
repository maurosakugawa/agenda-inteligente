<?php

declare(strict_types=1);

use AgendaInteligente\Application\Contacts\ContactDeleter;
use AgendaInteligente\Application\Contacts\ContactNotFoundException;
use AgendaInteligente\Infrastructure\Database\Connection;
use AgendaInteligente\Infrastructure\Persistence\ContactRepository;

require_once dirname(__DIR__) . '/autoload.php';

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function assertContactDeleterSame(
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

function assertContactDeleterTrue(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException(
            $message
        );
    }
}

function createContactDeleterUserFixture(
    \PDO $pdo,
    string $username
): int {
    $passwordHash =
        password_hash(
            'contact-deleter-fixture-password',
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

function createContactDeleterEventFixture(
    \PDO $pdo,
    int $userId,
    string $title
): int {
    $statement = $pdo->prepare(
        "
        INSERT INTO events (
            user_id,
            title,
            event_date
        )
        VALUES (
            :user_id,
            :title,
            :event_date
        )
        "
    );

    $statement->execute([
        ':user_id' => $userId,
        ':title' => $title,
        ':event_date' => '2030-01-15',
    ]);

    $id =
        (int) $pdo->lastInsertId();

    if ($id <= 0) {
        throw new RuntimeException(
            'Fixture de evento não recebeu identificador válido.'
        );
    }

    return $id;
}

function createContactDeleterEventContactFixture(
    \PDO $pdo,
    int $eventId,
    int $contactId
): void {
    $statement = $pdo->prepare(
        "
        INSERT INTO event_contacts (
            event_id,
            contact_id,
            created_at
        )
        VALUES (
            :event_id,
            :contact_id,
            UTC_TIMESTAMP()
        )
        "
    );

    $statement->execute([
        ':event_id' => $eventId,
        ':contact_id' => $contactId,
    ]);
}

function countContactDeleterRows(
    \PDO $pdo,
    string $sql,
    array $parameters
): int {
    $statement =
        $pdo->prepare(
            $sql
        );

    $statement->execute(
        $parameters
    );

    return (int) $statement->fetchColumn();
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

$deleter =
    new ContactDeleter(
        $repository
    );

$tests = [];

$tests[
    'remove contato pertencente ao usuário'
] = static function () use (
    $pdo,
    $repository,
    $deleter
): void {
    $suffix =
        bin2hex(
            random_bytes(5)
        );

    $userId =
        createContactDeleterUserFixture(
            $pdo,
            "contact_deleter_owner_{$suffix}"
        );

    $contactId =
        $repository->create(
            $userId,
            'Contato para Exclusão'
        );

    $deleter->delete(
        $contactId,
        $userId
    );

    $contact =
        $repository->findByIdAndUserId(
            $contactId,
            $userId
        );

    assertContactDeleterSame(
        null,
        $contact,
        'Contato deveria ter sido removido.'
    );
};

$tests[
    'retorna não encontrado para id inexistente'
] = static function () use (
    $pdo,
    $deleter
): void {
    $suffix =
        bin2hex(
            random_bytes(5)
        );

    $userId =
        createContactDeleterUserFixture(
            $pdo,
            "contact_deleter_missing_{$suffix}"
        );

    try {
        $deleter->delete(
            PHP_INT_MAX,
            $userId
        );
    } catch (ContactNotFoundException $exception) {
        assertContactDeleterSame(
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
    'não remove contato pertencente a outro usuário'
] = static function () use (
    $pdo,
    $repository,
    $deleter
): void {
    $suffix =
        bin2hex(
            random_bytes(5)
        );

    $ownerId =
        createContactDeleterUserFixture(
            $pdo,
            "contact_deleter_protected_owner_{$suffix}"
        );

    $otherUserId =
        createContactDeleterUserFixture(
            $pdo,
            "contact_deleter_other_{$suffix}"
        );

    $contactId =
        $repository->create(
            $ownerId,
            'Contato Protegido',
            '123456'
        );

    try {
        $deleter->delete(
            $contactId,
            $otherUserId
        );
    } catch (ContactNotFoundException $exception) {
        assertContactDeleterSame(
            'Contato não encontrado.',
            $exception->getMessage(),
            'Outro usuário deveria receber o mesmo erro de não encontrado.'
        );

        $contact =
            $repository->findByIdAndUserId(
                $contactId,
                $ownerId
            );

        assertContactDeleterTrue(
            is_array($contact),
            'Contato do proprietário deveria continuar existindo.'
        );

        assertContactDeleterSame(
            'Contato Protegido',
            $contact['name'] ?? null,
            'Tentativa indevida alterou o contato.'
        );

        assertContactDeleterSame(
            '123456',
            $contact['phone'] ?? null,
            'Tentativa indevida alterou os dados do contato.'
        );

        return;
    }

    throw new RuntimeException(
        'Era esperada uma ContactNotFoundException.'
    );
};

$tests[
    'remove vínculo com evento ao excluir contato'
] = static function () use (
    $pdo,
    $repository,
    $deleter
): void {
    $suffix =
        bin2hex(
            random_bytes(5)
        );

    $userId =
        createContactDeleterUserFixture(
            $pdo,
            "contact_deleter_event_{$suffix}"
        );

    $contactId =
        $repository->create(
            $userId,
            'Contato Vinculado'
        );

    $eventId =
        createContactDeleterEventFixture(
            $pdo,
            $userId,
            'Evento com Contato'
        );

    createContactDeleterEventContactFixture(
        $pdo,
        $eventId,
        $contactId
    );

    $linksBefore =
        countContactDeleterRows(
            $pdo,
            "
            SELECT COUNT(*)
            FROM event_contacts
            WHERE event_id = :event_id
              AND contact_id = :contact_id
            ",
            [
                ':event_id' => $eventId,
                ':contact_id' => $contactId,
            ]
        );

    assertContactDeleterSame(
        1,
        $linksBefore,
        'Vínculo com evento deveria existir antes da exclusão.'
    );

    $deleter->delete(
        $contactId,
        $userId
    );

    $linksAfter =
        countContactDeleterRows(
            $pdo,
            "
            SELECT COUNT(*)
            FROM event_contacts
            WHERE event_id = :event_id
              AND contact_id = :contact_id
            ",
            [
                ':event_id' => $eventId,
                ':contact_id' => $contactId,
            ]
        );

    assertContactDeleterSame(
        0,
        $linksAfter,
        'Vínculo deveria ser removido por cascade.'
    );

    $eventsAfter =
        countContactDeleterRows(
            $pdo,
            "
            SELECT COUNT(*)
            FROM events
            WHERE id = :event_id
            ",
            [
                ':event_id' => $eventId,
            ]
        );

    assertContactDeleterSame(
        1,
        $eventsAfter,
        'Excluir contato não deve remover o evento.'
    );
};

$tests[
    'contato removido não pode ser excluído novamente'
] = static function () use (
    $pdo,
    $repository,
    $deleter
): void {
    $suffix =
        bin2hex(
            random_bytes(5)
        );

    $userId =
        createContactDeleterUserFixture(
            $pdo,
            "contact_deleter_twice_{$suffix}"
        );

    $contactId =
        $repository->create(
            $userId,
            'Contato Removido Uma Vez'
        );

    $deleter->delete(
        $contactId,
        $userId
    );

    try {
        $deleter->delete(
            $contactId,
            $userId
        );
    } catch (ContactNotFoundException) {
        return;
    }

    throw new RuntimeException(
        'Segunda exclusão deveria resultar em ContactNotFoundException.'
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
    "\nContactDeleter: "
    . "{$passed}/{$total} teste(s) aprovado(s).\n"
);

exit(
    $passed === $total
        ? 0
        : 1
);
