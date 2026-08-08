<?php

declare(strict_types=1);

use AgendaInteligente\Application\Auth\CredentialValidator;
use AgendaInteligente\Application\Auth\RegisterController;
use AgendaInteligente\Application\Auth\UserRegistrar;
use AgendaInteligente\Infrastructure\Database\Connection;
use AgendaInteligente\Infrastructure\Http\Request;
use AgendaInteligente\Infrastructure\Persistence\UserRepository;
use AgendaInteligente\Infrastructure\Security\PasswordHasher;

require_once dirname(__DIR__) . '/autoload.php';

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function assertRegisterControllerSame(
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

function assertRegisterControllerTrue(
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

$registrar =
    new UserRegistrar(
        $repository,
        new PasswordHasher(),
        new CredentialValidator()
    );

$controller =
    new RegisterController(
        $registrar
    );

$tests = [];

$tests[
    'registra usuário e retorna contrato HTTP de criação'
] = static function () use (
    $controller,
    $repository
): void {
    $username =
        'register_controller_success';

    $response =
        $controller->handle(
            Request::create(
                'POST',
                '/auth/register',
                [
                    'Content-Type' =>
                        'application/json',
                ],
                json_encode(
                    [
                        'username' =>
                            $username,
                        'password' =>
                            'senha-valida-de-registro',
                    ],
                    JSON_THROW_ON_ERROR
                )
            )
        );

    assertRegisterControllerSame(
        201,
        $response->statusCode(),
        'Registro válido não retornou 201.'
    );

    assertRegisterControllerSame(
        true,
        $response->payload()['success']
            ?? null,
        'Resposta de registro não indicou sucesso.'
    );

    assertRegisterControllerSame(
        'Usuário criado',
        $response->payload()['data']['message']
            ?? null,
        'Mensagem de criação está incorreta.'
    );

    $userId =
        $response->payload()['data']['userId']
        ?? null;

    assertRegisterControllerTrue(
        is_int($userId),
        'userId deveria ser inteiro.'
    );

    assertRegisterControllerTrue(
        $userId > 0,
        'userId deveria ser positivo.'
    );

    $user =
        $repository->findById(
            $userId
        );

    assertRegisterControllerTrue(
        $user !== null,
        'Usuário criado não foi encontrado.'
    );

    assertRegisterControllerSame(
        $username,
        $user['username']
            ?? null,
        'Username persistido está incorreto.'
    );

    $payload =
        $response->payload();

    assertRegisterControllerSame(
        false,
        array_key_exists(
            'password',
            $payload['data']
                ?? []
        ),
        'Resposta não pode expor senha.'
    );

    assertRegisterControllerSame(
        false,
        array_key_exists(
            'password_hash',
            $payload['data']
                ?? []
        ),
        'Resposta não pode expor hash da senha.'
    );
};

$tests[
    'rejeita campos obrigatórios ausentes'
] = static function () use (
    $controller
): void {
    $response =
        $controller->handle(
            Request::create(
                'POST',
                '/auth/register',
                [
                    'Content-Type' =>
                        'application/json',
                ],
                json_encode(
                    [
                        'username' =>
                            'register_missing_password',
                    ],
                    JSON_THROW_ON_ERROR
                )
            )
        );

    assertRegisterControllerSame(
        400,
        $response->statusCode(),
        'Campo obrigatório ausente não retornou 400.'
    );

    assertRegisterControllerSame(
        'invalid_credentials_input',
        $response->payload()['error']['code']
            ?? null,
        'Código do erro de entrada está incorreto.'
    );
};

$tests[
    'rejeita tipo inválido sem provocar TypeError'
] = static function () use (
    $controller
): void {
    $response =
        $controller->handle(
            Request::create(
                'POST',
                '/auth/register',
                [
                    'Content-Type' =>
                        'application/json',
                ],
                json_encode(
                    [
                        'username' => [
                            'valor-invalido',
                        ],
                        'password' =>
                            'senha-valida-de-registro',
                    ],
                    JSON_THROW_ON_ERROR
                )
            )
        );

    assertRegisterControllerSame(
        400,
        $response->statusCode(),
        'Tipo inválido não retornou 400.'
    );

    assertRegisterControllerSame(
        'invalid_credentials_input',
        $response->payload()['error']['code']
            ?? null,
        'Tipo inválido retornou código incorreto.'
    );
};

$tests[
    'traduz validação de credenciais para HTTP 400'
] = static function () use (
    $controller,
    $repository
): void {
    $username =
        'ab';

    $response =
        $controller->handle(
            Request::create(
                'POST',
                '/auth/register',
                [
                    'Content-Type' =>
                        'application/json',
                ],
                json_encode(
                    [
                        'username' =>
                            $username,
                        'password' =>
                            'uma-senha-longa-segura',
                    ],
                    JSON_THROW_ON_ERROR
                )
            )
        );

    assertRegisterControllerSame(
        400,
        $response->statusCode(),
        'Credenciais inválidas não retornaram 400.'
    );

    assertRegisterControllerSame(
        'invalid_credentials_input',
        $response->payload()['error']['code']
            ?? null,
        'Código para credenciais inválidas está incorreto.'
    );

    assertRegisterControllerSame(
        null,
        $repository->findByUsername(
            $username
        ),
        'Credenciais inválidas resultaram em persistência.'
    );
};

$tests[
    'traduz username duplicado para HTTP 409'
] = static function () use (
    $controller
): void {
    $username =
        'register_controller_duplicate';

    $firstResponse =
        $controller->handle(
            Request::create(
                'POST',
                '/auth/register',
                [
                    'Content-Type' =>
                        'application/json',
                ],
                json_encode(
                    [
                        'username' =>
                            $username,
                        'password' =>
                            'primeira-senha-valida',
                    ],
                    JSON_THROW_ON_ERROR
                )
            )
        );

    assertRegisterControllerSame(
        201,
        $firstResponse->statusCode(),
        'Preparação do teste duplicado falhou.'
    );

    $secondResponse =
        $controller->handle(
            Request::create(
                'POST',
                '/auth/register',
                [
                    'Content-Type' =>
                        'application/json',
                ],
                json_encode(
                    [
                        'username' =>
                            $username,
                        'password' =>
                            'segunda-senha-valida',
                    ],
                    JSON_THROW_ON_ERROR
                )
            )
        );

    assertRegisterControllerSame(
        409,
        $secondResponse->statusCode(),
        'Username duplicado não retornou 409.'
    );

    assertRegisterControllerSame(
        'username_already_exists',
        $secondResponse
            ->payload()['error']['code']
            ?? null,
        'Código do conflito de username está incorreto.'
    );

    assertRegisterControllerSame(
        'Usuário já existe.',
        $secondResponse
            ->payload()['error']['message']
            ?? null,
        'Mensagem de username duplicado está incorreta.'
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
    "\nRegisterController: "
    . "{$passed}/{$total} teste(s) aprovado(s).\n"
);

exit(
    $passed === $total
        ? 0
        : 1
);
