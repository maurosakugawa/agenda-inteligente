<?php

declare(strict_types=1);

use AgendaInteligente\Infrastructure\Database\Connection;
use AgendaInteligente\Infrastructure\Persistence\MySqlUsernameCanonicalizer;

require_once dirname(__DIR__) . '/autoload.php';

$application =
    require dirname(__DIR__)
        . '/bootstrap.php';

$pdo =
    Connection::make(
        $application['database']
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
    fwrite(
        STDERR,
        "[ERRO] Teste recusado: conexão não está em agenda_inteligente_test.\n"
    );

    exit(1);
}

function assertUsernameCanonicalizerTrue(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException(
            $message
        );
    }
}

function assertUsernameCanonicalizerSame(
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

$canonicalizer =
    new MySqlUsernameCanonicalizer(
        $pdo
    );

$tests = [];

$tests[
    'canonicalizador acompanha collation de users.username'
] = static function () use ($pdo): void {
    $statement =
        $pdo->query(
            "
            SELECT COLLATION_NAME
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'users'
              AND COLUMN_NAME = 'username'
            LIMIT 1
            "
        );

    $collation =
        $statement->fetchColumn();

    assertUsernameCanonicalizerSame(
        'utf8mb4_unicode_ci',
        $collation,
        'A collation de users.username divergiu da usada pelo canonicalizador.'
    );
};

$tests[
    'variações de caixa possuem representação idêntica'
] = static function () use (
    $canonicalizer
): void {
    $expected =
        $canonicalizer
            ->canonicalize(
                'Alice'
            );

    foreach (
        [
            'alice',
            'ALICE',
        ] as $username
    ) {
        assertUsernameCanonicalizerSame(
            $expected,
            $canonicalizer
                ->canonicalize(
                    $username
                ),
            'Variações de caixa deveriam ser equivalentes.'
        );
    }
};

$tests[
    'variações de acento possuem representação idêntica'
] = static function () use (
    $canonicalizer
): void {
    assertUsernameCanonicalizerSame(
        $canonicalizer
            ->canonicalize(
                'José'
            ),
        $canonicalizer
            ->canonicalize(
                'Jose'
            ),
        'Variações equivalentes por acento deveriam compartilhar a representação.'
    );
};

$tests[
    'usernames distintos possuem representações distintas'
] = static function () use (
    $canonicalizer
): void {
    assertUsernameCanonicalizerTrue(
        $canonicalizer
            ->canonicalize(
                'Alice'
            )
        !==
        $canonicalizer
            ->canonicalize(
                'Bob'
            ),
        'Usernames não equivalentes não deveriam compartilhar a representação.'
    );
};

$passed = 0;

$total =
    count(
        $tests
    );

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

fwrite(
    STDOUT,
    "\nMySqlUsernameCanonicalizer: {$passed}/{$total} teste(s) aprovado(s).\n"
);

exit(
    $passed === $total
        ? 0
        : 1
);
