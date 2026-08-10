<?php

declare(strict_types=1);

use AgendaInteligente\Application\Contacts\ContactCreator;
use AgendaInteligente\Application\Contacts\ContactValidator;
use AgendaInteligente\Application\Contacts\InvalidContactInputException;
use AgendaInteligente\Infrastructure\Database\Connection;
use AgendaInteligente\Infrastructure\Persistence\ContactRepository;

require_once dirname(__DIR__) . '/autoload.php';

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function assertContactCreatorSame(
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

function assertContactCreatorTrue(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException(
            $message
        );
    }
}

function createContactCreatorUserFixture(
    \PDO $pdo,
    string $username
): int {
    $passwordHash =
        password_hash(
            'contact-creator-fixture-password',
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

$creator =
    new ContactCreator(
        $repository,
        $validator
    );

$tests = [];

$tests[
    'cria e retorna contato completo'
] = static function () use (
    $pdo,
    $creator
): void {
    $suffix =
        bin2hex(
            random_bytes(5)
        );

    $userId =
        createContactCreatorUserFixture(
            $pdo,
            "contact_creator_full_{$suffix}"
        );

    $contact =
        $creator->create(
            $userId,
            'Maria da Silva',
            '(12) 99999-8888',
            'maria@example.test',
            '12210-000',
            'Rua das Flores',
            '100',
            'Centro',
            'São José dos Campos',
            'SP'
        );

    assertContactCreatorTrue(
        is_int(
            $contact['id']
            ?? null
        ),
        'Contato deveria retornar identificador inteiro.'
    );

    assertContactCreatorTrue(
        $contact['id'] > 0,
        'Contato deveria retornar identificador positivo.'
    );

    assertContactCreatorSame(
        $userId,
        $contact['user_id']
        ?? null,
        'user_id retornado está incorreto.'
    );

    assertContactCreatorSame(
        'Maria da Silva',
        $contact['name']
        ?? null,
        'Nome retornado está incorreto.'
    );

    assertContactCreatorSame(
        '(12) 99999-8888',
        $contact['phone']
        ?? null,
        'Telefone retornado está incorreto.'
    );

    assertContactCreatorSame(
        'maria@example.test',
        $contact['email']
        ?? null,
        'E-mail retornado está incorreto.'
    );

    assertContactCreatorSame(
        '12210-000',
        $contact['cep']
        ?? null,
        'CEP retornado está incorreto.'
    );

    assertContactCreatorSame(
        'Rua das Flores',
        $contact['logradouro']
        ?? null,
        'Logradouro retornado está incorreto.'
    );

    assertContactCreatorSame(
        '100',
        $contact['numero']
        ?? null,
        'Número retornado está incorreto.'
    );

    assertContactCreatorSame(
        'Centro',
        $contact['bairro']
        ?? null,
        'Bairro retornado está incorreto.'
    );

    assertContactCreatorSame(
        'São José dos Campos',
        $contact['cidade']
        ?? null,
        'Cidade retornada está incorreta.'
    );

    assertContactCreatorSame(
        'SP',
        $contact['uf']
        ?? null,
        'UF retornada está incorreta.'
    );

    assertContactCreatorTrue(
        is_string(
            $contact['created_at']
            ?? null
        ),
        'created_at deveria ser retornado.'
    );

    assertContactCreatorTrue(
        is_string(
            $contact['updated_at']
            ?? null
        ),
        'updated_at deveria ser retornado.'
    );
};

$tests[
    'preserva campos opcionais vazios'
] = static function () use (
    $pdo,
    $creator
): void {
    $suffix =
        bin2hex(
            random_bytes(5)
        );

    $userId =
        createContactCreatorUserFixture(
            $pdo,
            "contact_creator_empty_{$suffix}"
        );

    $contact =
        $creator->create(
            $userId,
            'Contato com Vazios',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            ''
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
        assertContactCreatorTrue(
            array_key_exists(
                $field,
                $contact
            ),
            "Campo {$field} não foi retornado."
        );

        assertContactCreatorSame(
            '',
            $contact[$field],
            "Campo {$field} deveria preservar string vazia."
        );
    }
};

$tests[
    'valida antes de persistir contato'
] = static function () use (
    $pdo,
    $creator,
    $repository
): void {
    $suffix =
        bin2hex(
            random_bytes(5)
        );

    $userId =
        createContactCreatorUserFixture(
            $pdo,
            "contact_creator_invalid_{$suffix}"
        );

    try {
        $creator->create(
            $userId,
            ''
        );
    } catch (InvalidContactInputException) {
        $contacts =
            $repository->findAllByUserId(
                $userId
            );

        assertContactCreatorSame(
            [],
            $contacts,
            'Contato inválido não deveria ser persistido.'
        );

        return;
    }

    throw new RuntimeException(
        'Era esperada uma InvalidContactInputException.'
    );
};

$tests[
    'associa contato somente ao usuário informado'
] = static function () use (
    $pdo,
    $creator,
    $repository
): void {
    $suffix =
        bin2hex(
            random_bytes(5)
        );

    $ownerId =
        createContactCreatorUserFixture(
            $pdo,
            "contact_creator_owner_{$suffix}"
        );

    $otherUserId =
        createContactCreatorUserFixture(
            $pdo,
            "contact_creator_other_{$suffix}"
        );

    $contact =
        $creator->create(
            $ownerId,
            'Contato do Proprietário'
        );

    $contactId =
        $contact['id'];

    assertContactCreatorSame(
        $ownerId,
        $contact['user_id']
        ?? null,
        'Contato deveria pertencer ao usuário informado.'
    );

    $ownerContact =
        $repository->findByIdAndUserId(
            $contactId,
            $ownerId
        );

    assertContactCreatorTrue(
        is_array($ownerContact),
        'Proprietário deveria conseguir recuperar o contato.'
    );

    $otherContact =
        $repository->findByIdAndUserId(
            $contactId,
            $otherUserId
        );

    assertContactCreatorSame(
        null,
        $otherContact,
        'Outro usuário não deveria acessar o contato criado.'
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
    "\nContactCreator: "
    . "{$passed}/{$total} teste(s) aprovado(s).\n"
);

exit(
    $passed === $total
        ? 0
        : 1
);
