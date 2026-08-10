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


$tests[
    'cria contato e retorna identificador'
] = static function () use (
    $pdo
): void {
    $suffix = bin2hex(
        random_bytes(5)
    );

    $userId =
        createContactRepositoryUserFixture(
            $pdo,
            "contact_repository_create_{$suffix}"
        );

    $repository =
        new ContactRepository(
            $pdo
        );

    $contactId =
        $repository->create(
            $userId,
            'Maria da Silva',
            '(12) 99999-1234',
            'maria@example.test',
            '12210-000',
            'Rua das Flores',
            '250',
            'Centro',
            'São José dos Campos',
            'SP'
        );

    assertContactRepositoryTrue(
        $contactId > 0,
        'Repository não retornou identificador válido.'
    );

    $statement = $pdo->prepare(
        "
        SELECT
            id,
            user_id,
            name,
            phone,
            email,
            cep,
            logradouro,
            numero,
            bairro,
            cidade,
            uf
        FROM contacts
        WHERE id = :id
        LIMIT 1
        "
    );

    $statement->execute([
        ':id' => $contactId,
    ]);

    $contact = $statement->fetch();

    assertContactRepositoryTrue(
        is_array($contact),
        'Contato criado não foi encontrado no banco.'
    );

    assertContactRepositorySame(
        $userId,
        $contact['user_id'] ?? null,
        'Contato foi associado ao usuário incorreto.'
    );

    assertContactRepositorySame(
        'Maria da Silva',
        $contact['name'] ?? null,
        'Nome não foi preservado.'
    );

    assertContactRepositorySame(
        '(12) 99999-1234',
        $contact['phone'] ?? null,
        'Telefone não foi preservado.'
    );

    assertContactRepositorySame(
        'maria@example.test',
        $contact['email'] ?? null,
        'E-mail não foi preservado.'
    );

    assertContactRepositorySame(
        '12210-000',
        $contact['cep'] ?? null,
        'CEP não foi preservado.'
    );

    assertContactRepositorySame(
        'Rua das Flores',
        $contact['logradouro'] ?? null,
        'Logradouro não foi preservado.'
    );

    assertContactRepositorySame(
        '250',
        $contact['numero'] ?? null,
        'Número não foi preservado.'
    );

    assertContactRepositorySame(
        'Centro',
        $contact['bairro'] ?? null,
        'Bairro não foi preservado.'
    );

    assertContactRepositorySame(
        'São José dos Campos',
        $contact['cidade'] ?? null,
        'Cidade não foi preservada.'
    );

    assertContactRepositorySame(
        'SP',
        $contact['uf'] ?? null,
        'UF não foi preservada.'
    );
};

$tests[
    'aceita campos opcionais nulos'
] = static function () use (
    $pdo
): void {
    $suffix = bin2hex(
        random_bytes(5)
    );

    $userId =
        createContactRepositoryUserFixture(
            $pdo,
            "contact_repository_nullable_{$suffix}"
        );

    $repository =
        new ContactRepository(
            $pdo
        );

    $contactId =
        $repository->create(
            $userId,
            'Contato Mínimo'
        );

    $statement = $pdo->prepare(
        "
        SELECT
            name,
            phone,
            email,
            cep,
            logradouro,
            numero,
            bairro,
            cidade,
            uf
        FROM contacts
        WHERE id = :id
        LIMIT 1
        "
    );

    $statement->execute([
        ':id' => $contactId,
    ]);

    $contact = $statement->fetch();

    assertContactRepositoryTrue(
        is_array($contact),
        'Contato mínimo não foi encontrado.'
    );

    assertContactRepositorySame(
        'Contato Mínimo',
        $contact['name'] ?? null,
        'Nome obrigatório não foi preservado.'
    );

    foreach (
        [
            'phone',
            'email',
            'cep',
            'logradouro',
            'numero',
            'bairro',
            'cidade',
            'uf',
        ] as $field
    ) {
        assertContactRepositorySame(
            null,
            $contact[$field] ?? null,
            "Campo opcional {$field} deveria permanecer NULL."
        );
    }
};

$tests[
    'preenche timestamps ao criar contato'
] = static function () use (
    $pdo
): void {
    $suffix = bin2hex(
        random_bytes(5)
    );

    $userId =
        createContactRepositoryUserFixture(
            $pdo,
            "contact_repository_timestamp_{$suffix}"
        );

    $repository =
        new ContactRepository(
            $pdo
        );

    $contactId =
        $repository->create(
            $userId,
            'Contato Timestamp'
        );

    $statement = $pdo->prepare(
        "
        SELECT
            created_at,
            updated_at
        FROM contacts
        WHERE id = :id
        LIMIT 1
        "
    );

    $statement->execute([
        ':id' => $contactId,
    ]);

    $contact = $statement->fetch();

    assertContactRepositoryTrue(
        is_array($contact),
        'Contato para teste de timestamps não foi encontrado.'
    );

    assertContactRepositoryTrue(
        is_string(
            $contact['created_at'] ?? null
        )
        && $contact['created_at'] !== '',
        'created_at não foi preenchido.'
    );

    assertContactRepositoryTrue(
        is_string(
            $contact['updated_at'] ?? null
        )
        && $contact['updated_at'] !== '',
        'updated_at não foi preenchido.'
    );
};


$tests[
    'busca contato por id e usuário'
] = static function () use (
    $pdo
): void {
    $suffix = bin2hex(
        random_bytes(5)
    );

    $userId =
        createContactRepositoryUserFixture(
            $pdo,
            "contact_repository_find_{$suffix}"
        );

    $contactId =
        createContactRepositoryFixture(
            $pdo,
            $userId,
            'Contato Localizado',
            '2026-08-09 12:00:00'
        );

    $repository =
        new ContactRepository(
            $pdo
        );

    $contact =
        $repository->findByIdAndUserId(
            $contactId,
            $userId
        );

    assertContactRepositoryTrue(
        is_array($contact),
        'Contato existente não foi encontrado.'
    );

    assertContactRepositorySame(
        $contactId,
        $contact['id'] ?? null,
        'Repository retornou identificador incorreto.'
    );

    assertContactRepositorySame(
        $userId,
        $contact['user_id'] ?? null,
        'Repository retornou usuário incorreto.'
    );

    assertContactRepositorySame(
        'Contato Localizado',
        $contact['name'] ?? null,
        'Repository retornou contato incorreto.'
    );
};

$tests[
    'retorna null para id de contato inexistente'
] = static function () use (
    $pdo
): void {
    $suffix = bin2hex(
        random_bytes(5)
    );

    $userId =
        createContactRepositoryUserFixture(
            $pdo,
            "contact_repository_missing_{$suffix}"
        );

    $repository =
        new ContactRepository(
            $pdo
        );

    $contact =
        $repository->findByIdAndUserId(
            PHP_INT_MAX,
            $userId
        );

    assertContactRepositorySame(
        null,
        $contact,
        'Contato inexistente deveria retornar null.'
    );
};

$tests[
    'não retorna contato pertencente a outro usuário'
] = static function () use (
    $pdo
): void {
    $suffix = bin2hex(
        random_bytes(5)
    );

    $ownerId =
        createContactRepositoryUserFixture(
            $pdo,
            "contact_repository_owner_{$suffix}"
        );

    $otherUserId =
        createContactRepositoryUserFixture(
            $pdo,
            "contact_repository_other_{$suffix}"
        );

    $contactId =
        createContactRepositoryFixture(
            $pdo,
            $ownerId,
            'Contato Privado',
            '2026-08-09 13:00:00'
        );

    $repository =
        new ContactRepository(
            $pdo
        );

    $contact =
        $repository->findByIdAndUserId(
            $contactId,
            $otherUserId
        );

    assertContactRepositorySame(
        null,
        $contact,
        'Contato de outro usuário não deveria ser acessível.'
    );
};


$tests[
    'atualiza contato pertencente ao usuário'
] = static function () use (
    $pdo
): void {
    $suffix = bin2hex(
        random_bytes(5)
    );

    $userId =
        createContactRepositoryUserFixture(
            $pdo,
            "contact_repository_update_{$suffix}"
        );

    $repository =
        new ContactRepository(
            $pdo
        );

    $contactId =
        $repository->create(
            $userId,
            'Contato Original',
            '(12) 90000-0000',
            'original@example.test',
            '12210-000',
            'Rua Original',
            '10',
            'Bairro Original',
            'São José dos Campos',
            'SP'
        );

    $updated =
        $repository->update(
            $contactId,
            $userId,
            'Contato Atualizado',
            '(12) 98888-7777',
            'atualizado@example.test',
            '12345-678',
            'Rua Atualizada',
            '200',
            null,
            'Jacareí',
            'SP'
        );

    assertContactRepositorySame(
        true,
        $updated,
        'Atualização de contato existente deveria retornar true.'
    );

    $contact =
        $repository->findByIdAndUserId(
            $contactId,
            $userId
        );

    assertContactRepositoryTrue(
        is_array($contact),
        'Contato atualizado não foi encontrado.'
    );

    assertContactRepositorySame(
        'Contato Atualizado',
        $contact['name'] ?? null,
        'Nome atualizado está incorreto.'
    );

    assertContactRepositorySame(
        '(12) 98888-7777',
        $contact['phone'] ?? null,
        'Telefone atualizado está incorreto.'
    );

    assertContactRepositorySame(
        'atualizado@example.test',
        $contact['email'] ?? null,
        'E-mail atualizado está incorreto.'
    );

    assertContactRepositorySame(
        '12345-678',
        $contact['cep'] ?? null,
        'CEP atualizado está incorreto.'
    );

    assertContactRepositorySame(
        'Rua Atualizada',
        $contact['logradouro'] ?? null,
        'Logradouro atualizado está incorreto.'
    );

    assertContactRepositorySame(
        '200',
        $contact['numero'] ?? null,
        'Número atualizado está incorreto.'
    );

    assertContactRepositorySame(
        null,
        $contact['bairro'] ?? null,
        'Bairro deveria ter sido limpo para NULL.'
    );

    assertContactRepositorySame(
        'Jacareí',
        $contact['cidade'] ?? null,
        'Cidade atualizada está incorreta.'
    );

    assertContactRepositorySame(
        'SP',
        $contact['uf'] ?? null,
        'UF atualizada está incorreta.'
    );
};

$tests[
    'considera sucesso quando dados atualizados são idênticos'
] = static function () use (
    $pdo
): void {
    $suffix = bin2hex(
        random_bytes(5)
    );

    $userId =
        createContactRepositoryUserFixture(
            $pdo,
            "contact_repository_same_update_{$suffix}"
        );

    $repository =
        new ContactRepository(
            $pdo
        );

    $contactId =
        $repository->create(
            $userId,
            'Contato Sem Alteração',
            '(12) 97777-6666',
            'igual@example.test',
            '12210-000',
            'Rua Igual',
            '50',
            'Centro',
            'São José dos Campos',
            'SP'
        );

    $updated =
        $repository->update(
            $contactId,
            $userId,
            'Contato Sem Alteração',
            '(12) 97777-6666',
            'igual@example.test',
            '12210-000',
            'Rua Igual',
            '50',
            'Centro',
            'São José dos Campos',
            'SP'
        );

    assertContactRepositorySame(
        true,
        $updated,
        'Contato existente deveria ser considerado atualizado mesmo sem mudança de valores.'
    );
};

$tests[
    'não atualiza contato pertencente a outro usuário'
] = static function () use (
    $pdo
): void {
    $suffix = bin2hex(
        random_bytes(5)
    );

    $ownerId =
        createContactRepositoryUserFixture(
            $pdo,
            "contact_repository_update_owner_{$suffix}"
        );

    $otherUserId =
        createContactRepositoryUserFixture(
            $pdo,
            "contact_repository_update_other_{$suffix}"
        );

    $repository =
        new ContactRepository(
            $pdo
        );

    $contactId =
        $repository->create(
            $ownerId,
            'Contato Protegido'
        );

    $updated =
        $repository->update(
            $contactId,
            $otherUserId,
            'Tentativa Indevida'
        );

    assertContactRepositorySame(
        false,
        $updated,
        'Outro usuário não deveria conseguir atualizar o contato.'
    );

    $contact =
        $repository->findByIdAndUserId(
            $contactId,
            $ownerId
        );

    assertContactRepositoryTrue(
        is_array($contact),
        'Contato original não foi encontrado.'
    );

    assertContactRepositorySame(
        'Contato Protegido',
        $contact['name'] ?? null,
        'Tentativa de outro usuário modificou o contato.'
    );
};

$tests[
    'retorna false ao atualizar contato inexistente'
] = static function () use (
    $pdo
): void {
    $suffix = bin2hex(
        random_bytes(5)
    );

    $userId =
        createContactRepositoryUserFixture(
            $pdo,
            "contact_repository_update_missing_{$suffix}"
        );

    $repository =
        new ContactRepository(
            $pdo
        );

    $updated =
        $repository->update(
            PHP_INT_MAX,
            $userId,
            'Contato Inexistente'
        );

    assertContactRepositorySame(
        false,
        $updated,
        'Atualização de contato inexistente deveria retornar false.'
    );
};


$tests[
    'remove contato pertencente ao usuário'
] = static function () use (
    $pdo
): void {
    $suffix = bin2hex(
        random_bytes(5)
    );

    $userId =
        createContactRepositoryUserFixture(
            $pdo,
            "contact_repository_delete_{$suffix}"
        );

    $repository =
        new ContactRepository(
            $pdo
        );

    $contactId =
        $repository->create(
            $userId,
            'Contato para Exclusão'
        );

    $deleted =
        $repository->delete(
            $contactId,
            $userId
        );

    assertContactRepositorySame(
        true,
        $deleted,
        'Exclusão de contato existente deveria retornar true.'
    );

    $contact =
        $repository->findByIdAndUserId(
            $contactId,
            $userId
        );

    assertContactRepositorySame(
        null,
        $contact,
        'Contato removido ainda foi encontrado.'
    );
};

$tests[
    'não remove contato pertencente a outro usuário'
] = static function () use (
    $pdo
): void {
    $suffix = bin2hex(
        random_bytes(5)
    );

    $ownerId =
        createContactRepositoryUserFixture(
            $pdo,
            "contact_repository_delete_owner_{$suffix}"
        );

    $otherUserId =
        createContactRepositoryUserFixture(
            $pdo,
            "contact_repository_delete_other_{$suffix}"
        );

    $repository =
        new ContactRepository(
            $pdo
        );

    $contactId =
        $repository->create(
            $ownerId,
            'Contato Protegido contra Exclusão'
        );

    $deleted =
        $repository->delete(
            $contactId,
            $otherUserId
        );

    assertContactRepositorySame(
        false,
        $deleted,
        'Outro usuário não deveria conseguir remover o contato.'
    );

    $contact =
        $repository->findByIdAndUserId(
            $contactId,
            $ownerId
        );

    assertContactRepositoryTrue(
        is_array($contact),
        'Contato do proprietário foi removido indevidamente.'
    );
};

$tests[
    'retorna false ao remover contato inexistente'
] = static function () use (
    $pdo
): void {
    $suffix = bin2hex(
        random_bytes(5)
    );

    $userId =
        createContactRepositoryUserFixture(
            $pdo,
            "contact_repository_delete_missing_{$suffix}"
        );

    $repository =
        new ContactRepository(
            $pdo
        );

    $deleted =
        $repository->delete(
            PHP_INT_MAX,
            $userId
        );

    assertContactRepositorySame(
        false,
        $deleted,
        'Exclusão de contato inexistente deveria retornar false.'
    );
};

$tests[
    'remove vínculo com evento ao excluir contato'
] = static function () use (
    $pdo
): void {
    $suffix = bin2hex(
        random_bytes(5)
    );

    $userId =
        createContactRepositoryUserFixture(
            $pdo,
            "contact_repository_delete_event_{$suffix}"
        );

    $repository =
        new ContactRepository(
            $pdo
        );

    $contactId =
        $repository->create(
            $userId,
            'Contato Vinculado'
        );

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
        ':title' => 'Evento de Teste',
        ':event_date' => '2026-08-10',
    ]);

    $eventId =
        (int) $pdo->lastInsertId();

    assertContactRepositoryTrue(
        $eventId > 0,
        'Evento fixture não recebeu identificador válido.'
    );

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

    $deleted =
        $repository->delete(
            $contactId,
            $userId
        );

    assertContactRepositorySame(
        true,
        $deleted,
        'Contato vinculado deveria ter sido removido.'
    );

    $statement = $pdo->prepare(
        "
        SELECT COUNT(*)
        FROM event_contacts
        WHERE event_id = :event_id
          AND contact_id = :contact_id
        "
    );

    $statement->execute([
        ':event_id' => $eventId,
        ':contact_id' => $contactId,
    ]);

    assertContactRepositorySame(
        0,
        (int) $statement->fetchColumn(),
        'Vínculo com evento deveria ser removido por cascade.'
    );

    $statement = $pdo->prepare(
        "
        SELECT COUNT(*)
        FROM events
        WHERE id = :id
        "
    );

    $statement->execute([
        ':id' => $eventId,
    ]);

    assertContactRepositorySame(
        1,
        (int) $statement->fetchColumn(),
        'A exclusão do contato não deveria remover o evento.'
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
