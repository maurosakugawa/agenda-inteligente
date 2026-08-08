<?php

declare(strict_types=1);

use AgendaInteligente\Infrastructure\Security\CsrfTokenManager;
use AgendaInteligente\Infrastructure\Session\AuthenticatedSession;
use AgendaInteligente\Infrastructure\Session\SessionManager;

require_once dirname(__DIR__) . '/autoload.php';

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function assertAuthenticatedSessionSame(
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

function assertAuthenticatedSessionTrue(
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
 * @return array{
 *     name:string,
 *     secure:bool,
 *     same_site:string,
 *     idle_timeout:int,
 *     absolute_timeout:int
 * }
 */
function authenticatedSessionConfig(): array
{
    return [
        'name' =>
            'AGENDA_INTELIGENTE_AUTH_SESSION_TEST',
        'secure' => false,
        'same_site' => 'Lax',
        'idle_timeout' => 1800,
        'absolute_timeout' => 28800,
    ];
}

function resetAuthenticatedSessionNativeState(): void
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

function removeAuthenticatedSessionDirectory(
    string $sessionPath
): void {
    if (!is_dir($sessionPath)) {
        return;
    }

    $files =
        glob(
            $sessionPath . '/*'
        );

    if (is_array($files)) {
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink(
                    $file
                );
            }
        }
    }

    if (is_dir($sessionPath)) {
        rmdir(
            $sessionPath
        );
    }
}

/**
 * @param callable(): void $test
 *
 * @return array{
 *     passed:bool,
 *     output:string
 * }
 */
function runAuthenticatedSessionTest(
    string $name,
    callable $test
): array {
    resetAuthenticatedSessionNativeState();

    try {
        $test();

        return [
            'passed' => true,
            'output' =>
                "[OK] {$name}",
        ];
    } catch (Throwable $exception) {
        return [
            'passed' => false,
            'output' =>
                "[FALHA] {$name}: "
                . $exception->getMessage(),
        ];
    } finally {
        resetAuthenticatedSessionNativeState();
    }
}

$tests = [];

$tests[
    'regenera id e grava identidade autenticada'
] = static function (): void {
    $sessionPath =
        sys_get_temp_dir()
        . '/agenda-authenticated-session-'
        . bin2hex(
            random_bytes(8)
        );

    if (
        !mkdir(
            $sessionPath,
            0700,
            true
        )
    ) {
        throw new RuntimeException(
            'Não foi possível criar diretório temporário de sessão.'
        );
    }

    try {
        $session =
            new SessionManager(
                authenticatedSessionConfig(),
                $sessionPath,
                static fn (): int =>
                    2_000_000
            );

        $csrf =
            new CsrfTokenManager(
                $session,
                static fn (
                    int $length
                ): string => str_repeat(
                    "\x11",
                    $length
                )
            );

        $authenticatedSession =
            new AuthenticatedSession(
                $session,
                $csrf,
                static fn (): int =>
                    2_000_123
            );

        $session->start();

        $oldId =
            $session->id();

        $authenticatedSession->establish(
            [
                'id' => 42,
                'username' =>
                    'authenticated_session_test',
            ]
        );

        $newId =
            $session->id();

        assertAuthenticatedSessionTrue(
            $oldId !== $newId,
            'O identificador da sessão não foi regenerado.'
        );

        assertAuthenticatedSessionSame(
            [
                'user_id' => 42,
                'username' =>
                    'authenticated_session_test',
                'authenticated_at' =>
                    2_000_123,
            ],
            $session->get(
                'auth'
            ),
            'O estado autenticado está incorreto.'
        );

        $session->destroy();
    } finally {
        removeAuthenticatedSessionDirectory(
            $sessionPath
        );
    }
};

$tests[
    'rotaciona csrf e invalida token anterior'
] = static function (): void {
    $sessionPath =
        sys_get_temp_dir()
        . '/agenda-authenticated-session-'
        . bin2hex(
            random_bytes(8)
        );

    if (
        !mkdir(
            $sessionPath,
            0700,
            true
        )
    ) {
        throw new RuntimeException(
            'Não foi possível criar diretório temporário de sessão.'
        );
    }

    try {
        $randomCall = 0;

        $session =
            new SessionManager(
                authenticatedSessionConfig(),
                $sessionPath,
                static fn (): int =>
                    2_000_000
            );

        $csrf =
            new CsrfTokenManager(
                $session,
                static function (
                    int $length
                ) use (
                    &$randomCall
                ): string {
                    ++$randomCall;

                    return str_repeat(
                        $randomCall === 1
                            ? "\x11"
                            : "\x22",
                        $length
                    );
                }
            );

        $authenticatedSession =
            new AuthenticatedSession(
                $session,
                $csrf,
                static fn (): int =>
                    2_000_123
            );

        $session->start();

        $oldToken =
            $csrf->token();

        $newToken =
            $authenticatedSession->establish(
                [
                    'id' => 42,
                    'username' =>
                        'authenticated_session_test',
                ]
            );

        assertAuthenticatedSessionTrue(
            $oldToken !== $newToken,
            'O token CSRF não foi rotacionado.'
        );

        assertAuthenticatedSessionSame(
            str_repeat(
                '22',
                32
            ),
            $newToken,
            'O novo token CSRF está incorreto.'
        );

        assertAuthenticatedSessionSame(
            $newToken,
            $csrf->currentToken(),
            'O token retornado não é o token persistido.'
        );

        assertAuthenticatedSessionSame(
            false,
            $csrf->validate(
                $oldToken
            ),
            'O token CSRF anterior permaneceu válido.'
        );

        assertAuthenticatedSessionSame(
            true,
            $csrf->validate(
                $newToken
            ),
            'O novo token CSRF não é válido.'
        );

        $session->destroy();
    } finally {
        removeAuthenticatedSessionDirectory(
            $sessionPath
        );
    }
};

$tests[
    'renova tempos de segurança durante autenticação'
] = static function (): void {
    $sessionPath =
        sys_get_temp_dir()
        . '/agenda-authenticated-session-'
        . bin2hex(
            random_bytes(8)
        );

    if (
        !mkdir(
            $sessionPath,
            0700,
            true
        )
    ) {
        throw new RuntimeException(
            'Não foi possível criar diretório temporário de sessão.'
        );
    }

    try {
        $now = 2_000_000;

        $session =
            new SessionManager(
                authenticatedSessionConfig(),
                $sessionPath,
                static function () use (
                    &$now
                ): int {
                    return $now;
                }
            );

        $randomCall = 0;

        $csrf =
            new CsrfTokenManager(
                $session,
                static function (
                    int $length
                ) use (
                    &$randomCall
                ): string {
                    ++$randomCall;

                    return str_repeat(
                        $randomCall === 1
                            ? "\x11"
                            : "\x22",
                        $length
                    );
                }
            );

        $authenticatedSession =
            new AuthenticatedSession(
                $session,
                $csrf,
                static fn (): int =>
                    2_000_123
            );

        $session->start();

        $oldToken =
            $csrf->token();

        $securityBefore =
            $session->get(
                'security'
            );

        $now = 2_000_123;

        $newToken =
            $authenticatedSession->establish(
                [
                    'id' => 42,
                    'username' =>
                        'authenticated_session_test',
                ]
            );

        $securityAfter =
            $session->get(
                'security'
            );

        assertAuthenticatedSessionSame(
            2_000_000,
            $securityBefore['created_at']
                ?? null,
            'created_at inicial está incorreto.'
        );

        assertAuthenticatedSessionSame(
            2_000_123,
            $securityAfter['created_at']
                ?? null,
            'created_at não foi renovado no login.'
        );

        assertAuthenticatedSessionSame(
            2_000_123,
            $securityAfter['last_activity_at']
                ?? null,
            'last_activity_at não foi renovado no login.'
        );

        assertAuthenticatedSessionSame(
            2_028_923,
            $securityAfter['absolute_expires_at']
                ?? null,
            'absolute_expires_at não foi renovado no login.'
        );

        assertAuthenticatedSessionSame(
            false,
            $csrf->validate(
                $oldToken
            ),
            'O token CSRF anônimo permaneceu válido.'
        );

        assertAuthenticatedSessionSame(
            true,
            $csrf->validate(
                $newToken
            ),
            'O novo token CSRF não é válido.'
        );

        assertAuthenticatedSessionSame(
            $newToken,
            $securityAfter['csrf_token']
                ?? null,
            'O novo token não foi persistido.'
        );

        $session->destroy();
    } finally {
        removeAuthenticatedSessionDirectory(
            $sessionPath
        );
    }
};

$results = [];
$passed = 0;
$total = count(
    $tests
);

foreach ($tests as $name => $test) {
    $result =
        runAuthenticatedSessionTest(
            $name,
            $test
        );

    $results[] =
        $result['output'];

    if ($result['passed']) {
        ++$passed;
    }
}

foreach ($results as $result) {
    fwrite(
        STDOUT,
        $result . "\n"
    );
}

fwrite(
    STDOUT,
    "\nAuthenticatedSession: "
    . "{$passed}/{$total} teste(s) aprovado(s).\n"
);

exit(
    $passed === $total
        ? 0
        : 1
);
