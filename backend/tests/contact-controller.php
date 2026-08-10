<?php

declare(strict_types=1);

use AgendaInteligente\Application\Contacts\ContactController;
use AgendaInteligente\Application\Contacts\ContactCreator;
use AgendaInteligente\Application\Contacts\ContactDeleter;
use AgendaInteligente\Application\Contacts\ContactLister;
use AgendaInteligente\Application\Contacts\ContactUpdater;
use AgendaInteligente\Application\Contacts\ContactValidator;
use AgendaInteligente\Infrastructure\Database\Connection;
use AgendaInteligente\Infrastructure\Http\Request;
use AgendaInteligente\Infrastructure\Persistence\ContactRepository;

require_once dirname(__DIR__) . '/autoload.php';

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function assertContactControllerSame(
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

function assertContactControllerTrue(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException(
            $message
        );
    }
}

function createContactControllerUserFixture(
    PDO $pdo,
    string $username
): int {
    $passwordHash =
        password_hash(
            'contact-controller-fixture-password',
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
 * @param array<string, mixed> $body
 */
function createContactControllerRequest(
    string $method,
    string $uri,
    array $body = [],
    ?int $authenticatedUserId = null,
    ?string $routeId = null
): Request {
    $json =
        json_encode(
            $body,
            JSON_THROW_ON_ERROR
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        );

    $request =
        Request::create(
            $method,
            $uri,
            [
                'Content-Type' =>
                    'application/json',
            ],
            $json
        );

    if ($authenticatedUserId !== null) {
        $request =
            $request->withAttribute(
                'authenticated_user_id',
                $authenticatedUserId
            );
    }

    if ($routeId !== null) {
        $request =
            $request->withRouteParams(
                [
                    'id' => $routeId,
                ]
            );
    }

    return $request;
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

$controller =
    new ContactController(
        new ContactLister(
            $repository
        ),
        new ContactCreator(
            $repository,
            $validator
        ),
        new ContactUpdater(
            $repository,
            $validator
        ),
        new ContactDeleter(
            $repository
        )
    );

$tests = [];

$tests[
    'lista contatos em payload cru'
] = static function () use (
    $pdo,
    $repository,
    $controller
): void {
    $suffix =
        bin2hex(
            random_bytes(5)
        );

    $userId =
        createContactControllerUserFixture(
            $pdo,
            "contact_controller_list_{$suffix}"
        );

    $otherUserId =
        createContactControllerUserFixture(
            $pdo,
            "contact_controller_list_other_{$suffix}"
        );

    $repository->create(
        $userId,
        'Contato do Usuário'
    );

    $repository->create(
        $otherUserId,
        'Contato de Outro Usuário'
    );

    $request =
        createContactControllerRequest(
            'GET',
            '/api/contacts',
            [],
            $userId
        );

    $response =
        $controller->list(
            $request
        );

    assertContactControllerSame(
        200,
        $response->statusCode(),
        'Listagem deveria retornar HTTP 200.'
    );

    $payload =
        $response->payload();

    assertContactControllerSame(
        1,
        count($payload),
        'Listagem deveria retornar somente contatos do usuário autenticado.'
    );

    assertContactControllerSame(
        $userId,
        $payload[0]['user_id'] ?? null,
        'Contato retornado pertence ao usuário incorreto.'
    );

    assertContactControllerSame(
        'Contato do Usuário',
        $payload[0]['name'] ?? null,
        'Contato retornado está incorreto.'
    );

    assertContactControllerTrue(
        !array_key_exists(
            'success',
            $payload
        ),
        'Listagem não deve usar wrapper success/data.'
    );

    assertContactControllerTrue(
        !array_key_exists(
            'data',
            $payload
        ),
        'Listagem não deve usar wrapper data.'
    );
};

$tests[
    'rejeita requisição sem usuário autenticado'
] = static function () use (
    $controller
): void {
    $request =
        createContactControllerRequest(
            'GET',
            '/api/contacts'
        );

    $response =
        $controller->list(
            $request
        );

    assertContactControllerSame(
        401,
        $response->statusCode(),
        'Requisição sem usuário deveria retornar HTTP 401.'
    );

    assertContactControllerSame(
        [
            'error' =>
                'Autenticação necessária',
        ],
        $response->payload(),
        'Resposta 401 está incompatível com o contrato esperado.'
    );
};

$tests[
    'cria contato cru e ignora user_id do payload'
] = static function () use (
    $pdo,
    $repository,
    $controller
): void {
    $suffix =
        bin2hex(
            random_bytes(5)
        );

    $userId =
        createContactControllerUserFixture(
            $pdo,
            "contact_controller_create_{$suffix}"
        );

    $otherUserId =
        createContactControllerUserFixture(
            $pdo,
            "contact_controller_create_other_{$suffix}"
        );

    $request =
        createContactControllerRequest(
            'POST',
            '/api/contacts',
            [
                'user_id' =>
                    $otherUserId,
                'name' =>
                    'Contato Criado',
                'phone' =>
                    '',
                'email' =>
                    null,
            ],
            $userId
        );

    $response =
        $controller->create(
            $request
        );

    assertContactControllerSame(
        201,
        $response->statusCode(),
        'Criação deveria retornar HTTP 201.'
    );

    $payload =
        $response->payload();

    assertContactControllerSame(
        $userId,
        $payload['user_id'] ?? null,
        'user_id enviado pelo cliente não pode controlar ownership.'
    );

    assertContactControllerSame(
        'Contato Criado',
        $payload['name'] ?? null,
        'Nome criado está incorreto.'
    );

    assertContactControllerSame(
        '',
        $payload['phone'] ?? null,
        'String vazia deveria ser preservada.'
    );

    assertContactControllerTrue(
        array_key_exists(
            'email',
            $payload
        ),
        'Campo email deveria existir no contato retornado.'
    );

    assertContactControllerSame(
        null,
        $payload['email'],
        'null deveria ser preservado.'
    );

    assertContactControllerTrue(
        !array_key_exists(
            'success',
            $payload
        ),
        'Criação não deve usar wrapper success/data.'
    );

    $contactId =
        $payload['id']
        ?? null;

    assertContactControllerTrue(
        is_int($contactId),
        'Contato criado deveria possuir ID inteiro.'
    );

    $otherUserContact =
        $repository->findByIdAndUserId(
            $contactId,
            $otherUserId
        );

    assertContactControllerSame(
        null,
        $otherUserContact,
        'user_id malicioso não pode associar o contato a outro usuário.'
    );
};

$tests[
    'rejeita tipo inválido em campo opcional'
] = static function () use (
    $pdo,
    $repository,
    $controller
): void {
    $suffix =
        bin2hex(
            random_bytes(5)
        );

    $userId =
        createContactControllerUserFixture(
            $pdo,
            "contact_controller_invalid_optional_{$suffix}"
        );

    $before =
        count(
            $repository->findAllByUserId(
                $userId
            )
        );

    $request =
        createContactControllerRequest(
            'POST',
            '/api/contacts',
            [
                'name' =>
                    'Contato Inválido',
                'phone' =>
                    123456,
            ],
            $userId
        );

    $response =
        $controller->create(
            $request
        );

    assertContactControllerSame(
        400,
        $response->statusCode(),
        'Campo opcional de tipo inválido deveria retornar HTTP 400.'
    );

    assertContactControllerSame(
        [
            'error' =>
                'Telefone deve ser texto ou null.',
        ],
        $response->payload(),
        'Erro de tipo inválido está incorreto.'
    );

    $after =
        count(
            $repository->findAllByUserId(
                $userId
            )
        );

    assertContactControllerSame(
        $before,
        $after,
        'Entrada inválida não deveria persistir contato.'
    );
};

$tests[
    'rejeita criação sem nome'
] = static function () use (
    $pdo,
    $controller
): void {
    $suffix =
        bin2hex(
            random_bytes(5)
        );

    $userId =
        createContactControllerUserFixture(
            $pdo,
            "contact_controller_missing_name_{$suffix}"
        );

    $request =
        createContactControllerRequest(
            'POST',
            '/api/contacts',
            [
                'phone' =>
                    '1234',
            ],
            $userId
        );

    $response =
        $controller->create(
            $request
        );

    assertContactControllerSame(
        400,
        $response->statusCode(),
        'Nome ausente deveria retornar HTTP 400.'
    );

    assertContactControllerSame(
        [
            'error' =>
                'Nome do contato é obrigatório.',
        ],
        $response->payload(),
        'Erro para nome ausente está incorreto.'
    );
};

$tests[
    'atualiza contato em payload cru'
] = static function () use (
    $pdo,
    $repository,
    $controller
): void {
    $suffix =
        bin2hex(
            random_bytes(5)
        );

    $userId =
        createContactControllerUserFixture(
            $pdo,
            "contact_controller_update_{$suffix}"
        );

    $contactId =
        $repository->create(
            $userId,
            'Nome Original',
            '1111',
            'original@example.test'
        );

    $request =
        createContactControllerRequest(
            'PUT',
            "/api/contacts/{$contactId}",
            [
                'name' =>
                    'Nome Atualizado',
                'phone' =>
                    '',
                'email' =>
                    null,
                'cidade' =>
                    'São José dos Campos',
                'uf' =>
                    'SP',
            ],
            $userId,
            (string) $contactId
        );

    $response =
        $controller->update(
            $request
        );

    assertContactControllerSame(
        200,
        $response->statusCode(),
        'Atualização deveria retornar HTTP 200.'
    );

    $payload =
        $response->payload();

    assertContactControllerSame(
        $contactId,
        $payload['id'] ?? null,
        'ID do contato atualizado está incorreto.'
    );

    assertContactControllerSame(
        $userId,
        $payload['user_id'] ?? null,
        'Ownership do contato atualizado está incorreto.'
    );

    assertContactControllerSame(
        'Nome Atualizado',
        $payload['name'] ?? null,
        'Nome atualizado está incorreto.'
    );

    assertContactControllerSame(
        '',
        $payload['phone'] ?? null,
        'String vazia deveria ser preservada na atualização.'
    );

    assertContactControllerTrue(
        array_key_exists(
            'email',
            $payload
        ),
        'Campo email deveria existir após atualização.'
    );

    assertContactControllerSame(
        null,
        $payload['email'],
        'null deveria ser preservado na atualização.'
    );

    assertContactControllerSame(
        'São José dos Campos',
        $payload['cidade'] ?? null,
        'Cidade atualizada está incorreta.'
    );

    assertContactControllerSame(
        'SP',
        $payload['uf'] ?? null,
        'UF atualizada está incorreta.'
    );

    assertContactControllerTrue(
        !array_key_exists(
            'success',
            $payload
        ),
        'Atualização não deve usar wrapper success/data.'
    );
};

$tests[
    'rejeita id de contato inválido'
] = static function () use (
    $pdo,
    $controller
): void {
    $suffix =
        bin2hex(
            random_bytes(5)
        );

    $userId =
        createContactControllerUserFixture(
            $pdo,
            "contact_controller_invalid_id_{$suffix}"
        );

    $request =
        createContactControllerRequest(
            'PUT',
            '/api/contacts/abc',
            [
                'name' =>
                    'Contato',
            ],
            $userId,
            'abc'
        );

    $response =
        $controller->update(
            $request
        );

    assertContactControllerSame(
        400,
        $response->statusCode(),
        'ID inválido deveria retornar HTTP 400.'
    );

    assertContactControllerSame(
        [
            'error' =>
                'ID de contato inválido.',
        ],
        $response->payload(),
        'Resposta para ID inválido está incorreta.'
    );
};

$tests[
    'não revela diferença entre contato inexistente e de outro usuário'
] = static function () use (
    $pdo,
    $repository,
    $controller
): void {
    $suffix =
        bin2hex(
            random_bytes(5)
        );

    $ownerId =
        createContactControllerUserFixture(
            $pdo,
            "contact_controller_hidden_owner_{$suffix}"
        );

    $otherUserId =
        createContactControllerUserFixture(
            $pdo,
            "contact_controller_hidden_other_{$suffix}"
        );

    $contactId =
        $repository->create(
            $ownerId,
            'Contato Protegido'
        );

    $otherUserRequest =
        createContactControllerRequest(
            'PUT',
            "/api/contacts/{$contactId}",
            [
                'name' =>
                    'Tentativa Indevida',
            ],
            $otherUserId,
            (string) $contactId
        );

    $otherUserResponse =
        $controller->update(
            $otherUserRequest
        );

    $missingRequest =
        createContactControllerRequest(
            'PUT',
            '/api/contacts/2147483647',
            [
                'name' =>
                    'Contato Inexistente',
            ],
            $otherUserId,
            '2147483647'
        );

    $missingResponse =
        $controller->update(
            $missingRequest
        );

    assertContactControllerSame(
        404,
        $otherUserResponse->statusCode(),
        'Contato de outro usuário deveria retornar HTTP 404.'
    );

    assertContactControllerSame(
        404,
        $missingResponse->statusCode(),
        'Contato inexistente deveria retornar HTTP 404.'
    );

    assertContactControllerSame(
        $missingResponse->payload(),
        $otherUserResponse->payload(),
        'API não deve revelar diferença entre inexistência e ownership alheio.'
    );

    assertContactControllerSame(
        [
            'error' =>
                'Contato não encontrado',
        ],
        $otherUserResponse->payload(),
        'Payload de contato não encontrado está incorreto.'
    );

    $original =
        $repository->findByIdAndUserId(
            $contactId,
            $ownerId
        );

    assertContactControllerSame(
        'Contato Protegido',
        $original['name'] ?? null,
        'Tentativa de outro usuário não pode alterar o contato.'
    );
};

$tests[
    'remove contato e retorna mensagem compatível'
] = static function () use (
    $pdo,
    $repository,
    $controller
): void {
    $suffix =
        bin2hex(
            random_bytes(5)
        );

    $userId =
        createContactControllerUserFixture(
            $pdo,
            "contact_controller_delete_{$suffix}"
        );

    $contactId =
        $repository->create(
            $userId,
            'Contato para Remover'
        );

    $request =
        createContactControllerRequest(
            'DELETE',
            "/api/contacts/{$contactId}",
            [],
            $userId,
            (string) $contactId
        );

    $response =
        $controller->delete(
            $request
        );

    assertContactControllerSame(
        200,
        $response->statusCode(),
        'Exclusão deveria retornar HTTP 200.'
    );

    assertContactControllerSame(
        [
            'message' =>
                'Contato removido',
        ],
        $response->payload(),
        'Resposta de exclusão está incompatível com o frontend.'
    );

    $contact =
        $repository->findByIdAndUserId(
            $contactId,
            $userId
        );

    assertContactControllerSame(
        null,
        $contact,
        'Contato deveria ter sido removido.'
    );
};

$tests[
    'delete também oculta ownership'
] = static function () use (
    $pdo,
    $repository,
    $controller
): void {
    $suffix =
        bin2hex(
            random_bytes(5)
        );

    $ownerId =
        createContactControllerUserFixture(
            $pdo,
            "contact_controller_delete_owner_{$suffix}"
        );

    $otherUserId =
        createContactControllerUserFixture(
            $pdo,
            "contact_controller_delete_other_{$suffix}"
        );

    $contactId =
        $repository->create(
            $ownerId,
            'Contato Protegido para Exclusão'
        );

    $request =
        createContactControllerRequest(
            'DELETE',
            "/api/contacts/{$contactId}",
            [],
            $otherUserId,
            (string) $contactId
        );

    $response =
        $controller->delete(
            $request
        );

    assertContactControllerSame(
        404,
        $response->statusCode(),
        'Exclusão por outro usuário deveria retornar HTTP 404.'
    );

    assertContactControllerSame(
        [
            'error' =>
                'Contato não encontrado',
        ],
        $response->payload(),
        'Exclusão indevida deveria usar resposta genérica de não encontrado.'
    );

    $contact =
        $repository->findByIdAndUserId(
            $contactId,
            $ownerId
        );

    assertContactControllerTrue(
        is_array($contact),
        'Contato do proprietário deveria continuar existindo.'
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
    "\nContactController: "
    . "{$passed}/{$total} teste(s) aprovado(s).\n"
);

exit(
    $passed === $total
        ? 0
        : 1
);
