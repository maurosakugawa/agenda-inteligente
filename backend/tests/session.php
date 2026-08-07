<?php

declare(strict_types=1);

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
function sessionTestConfig(): array
{
    return [
        'name' => 'AGENDA_INTELIGENTE_TEST',
        'secure' => false,
        'same_site' => 'Lax',
        'idle_timeout' => 1800,
        'absolute_timeout' => 28800,
    ];
}

function assertSessionTrue(
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
function assertSessionSame(
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

function resetNativeSession(): void
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

/**
 * @return array{
 *     passed:bool,
 *     name:string,
 *     error:?string
 * }
 */
function runSessionTest(
    string $name,
    callable $test
): array {
    resetNativeSession();

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
        resetNativeSession();
    }
}

$temporarySessionPath =
    sys_get_temp_dir()
    . '/agenda-session-tests-'
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
    'inicia sessão anônima com configuração segura'
] = static function () use (
    $temporarySessionPath
): void {
    $now = 1_000_000;

    $manager = new SessionManager(
        sessionTestConfig(),
        $temporarySessionPath,
        static function () use (
            &$now
        ): int {
            return $now;
        }
    );

    $manager->start();

    assertSessionTrue(
        $manager->isStarted(),
        'A sessão não foi iniciada.'
    );

    assertSessionSame(
        'AGENDA_INTELIGENTE_TEST',
        session_name(),
        'O nome da sessão está incorreto.'
    );

    assertSessionSame(
        '1',
        (string) ini_get(
            'session.use_cookies'
        ),
        'session.use_cookies está incorreto.'
    );

    assertSessionSame(
        '1',
        (string) ini_get(
            'session.use_only_cookies'
        ),
        'session.use_only_cookies está incorreto.'
    );

    assertSessionSame(
        '1',
        (string) ini_get(
            'session.use_strict_mode'
        ),
        'session.use_strict_mode está incorreto.'
    );

    assertSessionSame(
        '0',
        (string) ini_get(
            'session.use_trans_sid'
        ),
        'session.use_trans_sid está incorreto.'
    );

    assertSessionTrue(
        (int) ini_get(
            'session.gc_maxlifetime'
        ) >= 1800,
        'session.gc_maxlifetime é menor que o idle timeout.'
    );

    $cookieParams =
        session_get_cookie_params();

    assertSessionSame(
        '/',
        $cookieParams['path'],
        'O path do cookie está incorreto.'
    );

    assertSessionSame(
        false,
        $cookieParams['secure'],
        'O atributo Secure está incorreto para testes.'
    );

    assertSessionSame(
        true,
        $cookieParams['httponly'],
        'O atributo HttpOnly não foi aplicado.'
    );

    assertSessionSame(
        'Lax',
        $cookieParams['samesite'],
        'O atributo SameSite está incorreto.'
    );

    $security =
        $manager->get('security');

    assertSessionTrue(
        is_array($security),
        'O estado de segurança não foi criado.'
    );

    assertSessionSame(
        $now,
        $security['created_at']
            ?? null,
        'created_at está incorreto.'
    );

    assertSessionSame(
        $now,
        $security['last_activity_at']
            ?? null,
        'last_activity_at está incorreto.'
    );

    assertSessionSame(
        $now + 28800,
        $security['absolute_expires_at']
            ?? null,
        'absolute_expires_at está incorreto.'
    );

    $manager->destroy();
};

$tests[
    'armazena dados e regenera o identificador'
] = static function () use (
    $temporarySessionPath
): void {
    $now = 2_000_000;

    $manager = new SessionManager(
        sessionTestConfig(),
        $temporarySessionPath,
        static function () use (
            &$now
        ): int {
            return $now;
        }
    );

    $manager->start();

    $manager->set(
        'example',
        [
            'value' => 42,
        ]
    );

    assertSessionSame(
        [
            'value' => 42,
        ],
        $manager->get('example'),
        'O valor não foi armazenado na sessão.'
    );

    $oldId = $manager->id();

    $manager->regenerateId();

    $newId = $manager->id();

    assertSessionTrue(
        $oldId !== $newId,
        'O identificador da sessão não foi regenerado.'
    );

    assertSessionSame(
        [
            'value' => 42,
        ],
        $manager->get('example'),
        'Os dados foram perdidos após regenerar a sessão.'
    );

    $manager->forget(
        'example'
    );

    assertSessionSame(
        null,
        $manager->get('example'),
        'O valor não foi removido da sessão.'
    );

    $manager->destroy();
};

$tests[
    'atualiza atividade ao reabrir sessão válida'
] = static function () use (
    $temporarySessionPath
): void {
    $now = 3_000_000;

    $manager = new SessionManager(
        sessionTestConfig(),
        $temporarySessionPath,
        static function () use (
            &$now
        ): int {
            return $now;
        }
    );

    $manager->start();

    $originalId =
        $manager->id();

    $originalSecurity =
        $manager->get('security');

    $manager->close();

    $now += 120;

    $manager->start();

    $security =
        $manager->get('security');

    assertSessionSame(
        $originalId,
        $manager->id(),
        'Uma sessão válida foi regenerada sem necessidade.'
    );

    assertSessionSame(
        $originalSecurity['created_at']
            ?? null,
        $security['created_at']
            ?? null,
        'created_at foi alterado indevidamente.'
    );

    assertSessionSame(
        $now,
        $security['last_activity_at']
            ?? null,
        'A última atividade não foi atualizada.'
    );

    $manager->destroy();
};

$tests[
    'expira sessão por inatividade'
] = static function () use (
    $temporarySessionPath
): void {
    $now = 4_000_000;

    $manager = new SessionManager(
        sessionTestConfig(),
        $temporarySessionPath,
        static function () use (
            &$now
        ): int {
            return $now;
        }
    );

    $manager->start();

    $manager->set(
        'auth',
        [
            'user_id' => 10,
        ]
    );

    $oldId =
        $manager->id();

    $manager->close();

    $now += 1800;

    $manager->start();

    assertSessionTrue(
        $oldId !== $manager->id(),
        'A sessão expirada por inatividade não foi regenerada.'
    );

    assertSessionSame(
        null,
        $manager->get('auth'),
        'Dados autenticados sobreviveram à expiração por inatividade.'
    );

    $security =
        $manager->get('security');

    assertSessionSame(
        $now,
        $security['created_at']
            ?? null,
        'A sessão anônima não foi reinicializada após expiração.'
    );

    $manager->destroy();
};

$tests[
    'expira sessão pelo limite absoluto'
] = static function () use (
    $temporarySessionPath
): void {
    $now = 5_000_000;

    $manager = new SessionManager(
        sessionTestConfig(),
        $temporarySessionPath,
        static function () use (
            &$now
        ): int {
            return $now;
        }
    );

    $manager->start();

    $manager->set(
        'auth',
        [
            'user_id' => 20,
        ]
    );

    $security =
        $manager->get('security');

    $security['absolute_expires_at'] =
        $now + 10;

    $manager->set(
        'security',
        $security
    );

    $oldId =
        $manager->id();

    $manager->close();

    $now += 11;

    $manager->start();

    assertSessionTrue(
        $oldId !== $manager->id(),
        'A sessão expirada pelo limite absoluto não foi regenerada.'
    );

    assertSessionSame(
        null,
        $manager->get('auth'),
        'Dados autenticados sobreviveram à expiração absoluta.'
    );

    $manager->destroy();
};

$tests[
    'rejeita estado de segurança inconsistente'
] = static function () use (
    $temporarySessionPath
): void {
    $now = 6_000_000;

    $manager = new SessionManager(
        sessionTestConfig(),
        $temporarySessionPath,
        static function () use (
            &$now
        ): int {
            return $now;
        }
    );

    $manager->start();

    $manager->set(
        'auth',
        [
            'user_id' => 30,
        ]
    );

    $manager->set(
        'security',
        'estado-invalido'
    );

    $oldId =
        $manager->id();

    $manager->close();

    $now++;

    $manager->start();

    assertSessionTrue(
        $oldId !== $manager->id(),
        'A sessão inconsistente não foi regenerada.'
    );

    assertSessionSame(
        null,
        $manager->get('auth'),
        'Dados sobreviveram a um estado de segurança inconsistente.'
    );

    assertSessionTrue(
        is_array(
            $manager->get('security')
        ),
        'O estado de segurança não foi reconstruído.'
    );

    $manager->destroy();
};

$tests[
    'destrói sessão e limpa dados'
] = static function () use (
    $temporarySessionPath
): void {
    $now = 7_000_000;

    $manager = new SessionManager(
        sessionTestConfig(),
        $temporarySessionPath,
        static function () use (
            &$now
        ): int {
            return $now;
        }
    );

    $manager->start();

    $manager->set(
        'auth',
        [
            'user_id' => 40,
        ]
    );

    $manager->destroy();

    assertSessionSame(
        PHP_SESSION_NONE,
        session_status(),
        'A sessão permaneceu ativa após destroy().'
    );

    assertSessionSame(
        [],
        $_SESSION,
        'Os dados da sessão não foram limpos.'
    );
};

$tests[
    'fecha sessão quando inicialização falha após session_start'
] = static function () use (
    $temporarySessionPath
): void {
    $manager = new SessionManager(
        sessionTestConfig(),
        $temporarySessionPath,
        static function (): int {
            throw new RuntimeException(
                'falha-controlada-do-relogio'
            );
        }
    );

    try {
        $manager->start();
    } catch (RuntimeException $exception) {
        assertSessionSame(
            'falha-controlada-do-relogio',
            $exception->getMessage(),
            'A exceção original foi alterada.'
        );

        assertSessionSame(
            PHP_SESSION_NONE,
            session_status(),
            'A sessão permaneceu ativa após falha durante start().'
        );

        assertSessionSame(
            false,
            $manager->isStarted(),
            'O SessionManager considera a sessão ativa após falha.'
        );

        return;
    }

    throw new RuntimeException(
        'A falha durante a inicialização não foi propagada.'
    );
};

$tests[
    'rejeita diretório de sessão inválido'
] = static function (): void {
    $now = 8_000_000;

    $invalidPath =
        sys_get_temp_dir()
        . '/agenda-session-inexistente-'
        . bin2hex(
            random_bytes(8)
        );

    $manager = new SessionManager(
        sessionTestConfig(),
        $invalidPath,
        static function () use (
            &$now
        ): int {
            return $now;
        }
    );

    try {
        $manager->start();
    } catch (RuntimeException $exception) {
        assertSessionTrue(
            str_contains(
                $exception->getMessage(),
                'não existe ou não é gravável'
            ),
            'A mensagem do diretório inválido está incorreta.'
        );

        return;
    }

    throw new RuntimeException(
        'Um diretório de sessão inválido foi aceito.'
    );
};

ob_start();

$results = [];

try {
    foreach (
        $tests as $name => $test
    ) {
        $results[] =
            runSessionTest(
                $name,
                $test
            );
    }
} finally {
    resetNativeSession();

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

$total = count(
    $results
);

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
