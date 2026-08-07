<?php

declare(strict_types=1);

use AgendaInteligente\Application\Auth\CsrfController;
use AgendaInteligente\Infrastructure\Security\CsrfTokenManager;
use AgendaInteligente\Infrastructure\Session\SessionManager;

require_once dirname(__DIR__) . '/autoload.php';

function assertCsrfControllerSame(
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

$sessionPath =
    sys_get_temp_dir()
    . '/agenda-csrf-controller-test-'
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
        'Não foi possível criar o diretório temporário.'
    );
}

$result = 1;

try {
    $session = new SessionManager(
        [
            'name' => 'AGENDA_CSRF_CONTROLLER_TEST',
            'secure' => false,
            'same_site' => 'Lax',
            'idle_timeout' => 1800,
            'absolute_timeout' => 28800,
        ],
        $sessionPath,
        static fn (): int => 1_000_000
    );

    $session->start();

    $csrf = new CsrfTokenManager(
        $session,
        static fn (
            int $length
        ): string => str_repeat(
            "\xAB",
            $length
        )
    );

    $controller =
        new CsrfController(
            $csrf
        );

    $response =
        $controller->handle();

    $expectedToken =
        str_repeat(
            'ab',
            32
        );

    assertCsrfControllerSame(
        200,
        $response->statusCode(),
        'O status do endpoint CSRF está incorreto.'
    );

    assertCsrfControllerSame(
        [
            'csrf_token' => $expectedToken,
        ],
        $response->payload(),
        'O contrato JSON do endpoint CSRF está incorreto.'
    );

    assertCsrfControllerSame(
        'no-store',
        $response->headers()['Cache-Control']
            ?? null,
        'O endpoint CSRF não desabilitou cache.'
    );

    assertCsrfControllerSame(
        $expectedToken,
        $session->get(
            'security'
        )['csrf_token']
            ?? null,
        'O token retornado não foi armazenado na sessão.'
    );

    fwrite(
        STDOUT,
        "[OK] controller retorna token CSRF no contrato esperado\n"
    );

    $result = 0;
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        sprintf(
            "[FALHA] %s\n",
            $exception->getMessage()
        )
    );
} finally {
    if (
        session_status()
        === PHP_SESSION_ACTIVE
    ) {
        $_SESSION = [];
        session_destroy();
    }

    $files =
        glob(
            $sessionPath . '/*'
        );

    if (is_array($files)) {
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    if (is_dir($sessionPath)) {
        rmdir($sessionPath);
    }
}

exit($result);
