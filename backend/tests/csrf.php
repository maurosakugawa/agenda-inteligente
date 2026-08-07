<?php

declare(strict_types=1);

use AgendaInteligente\Infrastructure\Security\CsrfTokenManager;
use AgendaInteligente\Infrastructure\Session\SessionManager;

require_once dirname(__DIR__) . '/autoload.php';

/**
 * @return array{
 *     name:string,
 *     secure:bool,
 *     same_site:string,
 *     idle_timeout:int,
 *     absolute_timeout:int
 * }
 */
function csrfSessionConfig(): array
{
    return [
        'name' => 'AGENDA_INTELIGENTE_CSRF_TEST',
        'secure' => false,
        'same_site' => 'Lax',
        'idle_timeout' => 1800,
        'absolute_timeout' => 28800,
    ];
}

function assertCsrfTrue(
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
 * @param mixed $expected
 * @param mixed $actual
 */
function assertCsrfSame(
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

function resetCsrfNativeSession(): void
{
    if (
        session_status()
        === PHP_SESSION_ACTIVE
    ) {
        $_SESSION = [];
        session_destroy();
    }

    if (
        session_status()
        === PHP_SESSION_NONE
    ) {
        session_id('');
    }

    $_SESSION = [];
}

function createCsrfSessionManager(
    string $sessionPath,
    int $now = 1_000_000
): SessionManager {
    return new SessionManager(
        csrfSessionConfig(),
        $sessionPath,
        static fn (): int => $now
    );
}

/**
 * @return array{
 *     passed:bool,
 *     name:string,
 *     error:?string
 * }
 */
function runCsrfTest(
    string $name,
    callable $test
): array {
    resetCsrfNativeSession();

    try {
        $test();

        return [
            'passed' => true,
            'name' => $name,
            'error' => null,
        ];
    } catch (Throwable $exception) {
        return [
            'passed' => false,
            'name' => $name,
            'error' => $exception->getMessage(),
        ];
    } finally {
        resetCsrfNativeSession();
    }
}

$temporarySessionPath =
    sys_get_temp_dir()
    . '/agenda-csrf-tests-'
    . bin2hex(
        random_bytes(8)
    );

if (
    !mkdir(
        $temporarySessionPath,
        0700,
        true
    )
) {
    throw new RuntimeException(
        'Não foi possível criar o diretório temporário de sessões.'
    );
}

$tests = [];

$tests[
    'gera token hexadecimal com 256 bits'
] = static function () use (
    $temporarySessionPath
): void {
    $session =
        createCsrfSessionManager(
            $temporarySessionPath
        );

    $session->start();

    $calls = 0;

    $csrf = new CsrfTokenManager(
        $session,
        static function (
            int $length
        ) use (
            &$calls
        ): string {
            $calls++;

            return str_repeat(
                "\xAB",
                $length
            );
        }
    );

    $token = $csrf->token();

    assertCsrfSame(
        64,
        strlen($token),
        'O token não possui 64 caracteres hexadecimais.'
    );

    assertCsrfTrue(
        ctype_xdigit($token),
        'O token contém caracteres não hexadecimais.'
    );

    assertCsrfSame(
        str_repeat(
            'ab',
            32
        ),
        $token,
        'A codificação hexadecimal está incorreta.'
    );

    assertCsrfSame(
        1,
        $calls,
        'A fonte aleatória não foi chamada exatamente uma vez.'
    );

    $security =
        $session->get(
            'security'
        );

    assertCsrfSame(
        $token,
        $security['csrf_token']
            ?? null,
        'O token não foi armazenado no estado de segurança.'
    );

    $session->destroy();
};

$tests[
    'reutiliza token válido existente'
] = static function () use (
    $temporarySessionPath
): void {
    $session =
        createCsrfSessionManager(
            $temporarySessionPath
        );

    $session->start();

    $calls = 0;

    $csrf = new CsrfTokenManager(
        $session,
        static function (
            int $length
        ) use (
            &$calls
        ): string {
            $calls++;

            return str_repeat(
                "\x01",
                $length
            );
        }
    );

    $firstToken =
        $csrf->token();

    $secondToken =
        $csrf->token();

    assertCsrfSame(
        $firstToken,
        $secondToken,
        'O token existente não foi reutilizado.'
    );

    assertCsrfSame(
        1,
        $calls,
        'Um novo token foi gerado sem necessidade.'
    );

    $session->destroy();
};

$tests[
    'rotaciona token explicitamente'
] = static function () use (
    $temporarySessionPath
): void {
    $session =
        createCsrfSessionManager(
            $temporarySessionPath
        );

    $session->start();

    $sequence = 0;

    $csrf = new CsrfTokenManager(
        $session,
        static function (
            int $length
        ) use (
            &$sequence
        ): string {
            $sequence++;

            return str_repeat(
                chr($sequence),
                $length
            );
        }
    );

    $firstToken =
        $csrf->token();

    $secondToken =
        $csrf->rotate();

    assertCsrfTrue(
        $firstToken !== $secondToken,
        'A rotação não produziu um novo token.'
    );

    assertCsrfSame(
        str_repeat(
            '02',
            32
        ),
        $secondToken,
        'O token rotacionado está incorreto.'
    );

    assertCsrfSame(
        $secondToken,
        $csrf->currentToken(),
        'O token rotacionado não se tornou o token atual.'
    );

    $session->destroy();
};

$tests[
    'valida somente token correto'
] = static function () use (
    $temporarySessionPath
): void {
    $session =
        createCsrfSessionManager(
            $temporarySessionPath
        );

    $session->start();

    $csrf = new CsrfTokenManager(
        $session,
        static fn (
            int $length
        ): string => str_repeat(
            "\x7F",
            $length
        )
    );

    $token =
        $csrf->token();

    assertCsrfSame(
        true,
        $csrf->validate(
            $token
        ),
        'O token correto foi rejeitado.'
    );

    assertCsrfSame(
        false,
        $csrf->validate(
            str_repeat(
                '00',
                32
            )
        ),
        'Um token incorreto foi aceito.'
    );

    assertCsrfSame(
        false,
        $csrf->validate(null),
        'Token ausente foi aceito.'
    );

    assertCsrfSame(
        false,
        $csrf->validate(''),
        'Token vazio foi aceito.'
    );

    $session->destroy();
};

$tests[
    'não cria token durante validação'
] = static function () use (
    $temporarySessionPath
): void {
    $session =
        createCsrfSessionManager(
            $temporarySessionPath
        );

    $session->start();

    $calls = 0;

    $csrf = new CsrfTokenManager(
        $session,
        static function (
            int $length
        ) use (
            &$calls
        ): string {
            $calls++;

            return str_repeat(
                "\xAA",
                $length
            );
        }
    );

    $valid =
        $csrf->validate(
            str_repeat(
                'aa',
                32
            )
        );

    assertCsrfSame(
        false,
        $valid,
        'A validação aceitou token sem token armazenado na sessão.'
    );

    assertCsrfSame(
        0,
        $calls,
        'A validação gerou um token automaticamente.'
    );

    assertCsrfSame(
        null,
        $csrf->currentToken(),
        'Um token foi criado durante a validação.'
    );

    $session->destroy();
};

$tests[
    'substitui token armazenado com formato inválido'
] = static function () use (
    $temporarySessionPath
): void {
    $session =
        createCsrfSessionManager(
            $temporarySessionPath
        );

    $session->start();

    $security =
        $session->get(
            'security'
        );

    $security['csrf_token'] =
        'token-invalido';

    $session->set(
        'security',
        $security
    );

    $csrf = new CsrfTokenManager(
        $session,
        static fn (
            int $length
        ): string => str_repeat(
            "\x03",
            $length
        )
    );

    assertCsrfSame(
        null,
        $csrf->currentToken(),
        'Um token inválido foi considerado atual.'
    );

    $token =
        $csrf->token();

    assertCsrfSame(
        str_repeat(
            '03',
            32
        ),
        $token,
        'O token inválido não foi substituído.'
    );

    $session->destroy();
};

$tests[
    'rejeita fonte aleatória com tamanho incorreto'
] = static function () use (
    $temporarySessionPath
): void {
    $session =
        createCsrfSessionManager(
            $temporarySessionPath
        );

    $session->start();

    $csrf = new CsrfTokenManager(
        $session,
        static fn (
            int $length
        ): string => str_repeat(
            "\x01",
            $length - 1
        )
    );

    try {
        $csrf->token();
    } catch (RuntimeException $exception) {
        assertCsrfTrue(
            str_contains(
                $exception->getMessage(),
                'quantidade esperada de bytes'
            ),
            'A mensagem da fonte aleatória inválida está incorreta.'
        );

        $session->destroy();

        return;
    }

    $session->destroy();

    throw new RuntimeException(
        'Uma fonte aleatória inválida foi aceita.'
    );
};

$tests[
    'exige sessão iniciada'
] = static function () use (
    $temporarySessionPath
): void {
    $session =
        createCsrfSessionManager(
            $temporarySessionPath
        );

    $csrf = new CsrfTokenManager(
        $session
    );

    try {
        $csrf->token();
    } catch (RuntimeException $exception) {
        assertCsrfTrue(
            str_contains(
                $exception->getMessage(),
                'sessão ainda não foi iniciada'
            ),
            'A mensagem para sessão não iniciada está incorreta.'
        );

        return;
    }

    throw new RuntimeException(
        'O gerenciador CSRF funcionou sem sessão iniciada.'
    );
};

ob_start();

$results = [];

try {
    foreach (
        $tests as $name => $test
    ) {
        $results[] =
            runCsrfTest(
                $name,
                $test
            );
    }
} finally {
    resetCsrfNativeSession();

    $sessionFiles =
        glob(
            $temporarySessionPath
            . '/*'
        );

    if (
        is_array($sessionFiles)
    ) {
        foreach (
            $sessionFiles as $file
        ) {
            if (
                is_file($file)
            ) {
                unlink($file);
            }
        }
    }

    if (
        is_dir(
            $temporarySessionPath
        )
    ) {
        rmdir(
            $temporarySessionPath
        );
    }
}

$unexpectedOutput =
    ob_get_clean();

$passed = 0;

foreach (
    $results as $result
) {
    if ($result['passed']) {
        $passed++;

        fwrite(
            STDOUT,
            sprintf(
                "[OK] %s\n",
                $result['name']
            )
        );

        continue;
    }

    fwrite(
        STDERR,
        sprintf(
            "[FALHA] %s: %s\n",
            $result['name'],
            $result['error']
        )
    );
}

if (
    is_string($unexpectedOutput)
    && $unexpectedOutput !== ''
) {
    fwrite(
        STDERR,
        sprintf(
            "[FALHA] Saída inesperada durante os testes:\n%s\n",
            $unexpectedOutput
        )
    );
}

$total =
    count($results);

fwrite(
    STDOUT,
    sprintf(
        "\nResultado: %d/%d testes aprovados.\n",
        $passed,
        $total
    )
);

exit(
    $passed === $total
    && $unexpectedOutput === ''
        ? 0
        : 1
);
