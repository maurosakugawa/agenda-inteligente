<?php

declare(strict_types=1);

use AgendaInteligente\Application\Auth\AuthenticationSession;
use AgendaInteligente\Application\Auth\LogoutController;

require_once dirname(__DIR__) . '/autoload.php';

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function assertLogoutControllerSame(
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

/**
 * @param callable(): void $test
 */
function runLogoutControllerTest(
    string $name,
    callable $test
): bool {
    try {
        $test();

        fwrite(
            STDOUT,
            "[OK] {$name}\n"
        );

        return true;
    } catch (Throwable $exception) {
        fwrite(
            STDERR,
            "[FALHA] {$name}: "
            . $exception->getMessage()
            . "\n"
        );

        return false;
    }
}

final class LogoutControllerAuthenticationSessionFake
    implements AuthenticationSession
{
    public int $establishCalls = 0;

    public int $terminateCalls = 0;

    public function establish(
        array $identity
    ): string {
        ++$this->establishCalls;

        return 'csrf-nao-utilizado';
    }

    public function current(): ?array
    {
        return null;
    }

    public function terminate(): void
    {
        ++$this->terminateCalls;
    }
}

$tests = [];

$tests[
    'encerra sessão e retorna confirmação'
] = static function (): void {
    $session =
        new LogoutControllerAuthenticationSessionFake();

    $controller =
        new LogoutController(
            $session
        );

    $response =
        $controller->handle();

    assertLogoutControllerSame(
        200,
        $response->statusCode(),
        'Logout não retornou HTTP 200.'
    );

    assertLogoutControllerSame(
        true,
        $response
            ->payload()['success']
            ?? null,
        'Logout não retornou success=true.'
    );

    assertLogoutControllerSame(
        'Logout realizado',
        $response
            ->payload()['data']['message']
            ?? null,
        'Mensagem de logout está incorreta.'
    );

    assertLogoutControllerSame(
        1,
        $session->terminateCalls,
        'Sessão não foi encerrada exatamente uma vez.'
    );

    assertLogoutControllerSame(
        0,
        $session->establishCalls,
        'Logout tentou estabelecer uma nova sessão.'
    );
};

$passed = 0;
$total = count(
    $tests
);

foreach ($tests as $name => $test) {
    if (
        runLogoutControllerTest(
            $name,
            $test
        )
    ) {
        ++$passed;
    }
}

fwrite(
    STDOUT,
    "\nLogoutController: "
    . "{$passed}/{$total} teste(s) aprovado(s).\n"
);

exit(
    $passed === $total
        ? 0
        : 1
);
