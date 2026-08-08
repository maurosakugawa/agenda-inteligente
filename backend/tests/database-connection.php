<?php

declare(strict_types=1);

use AgendaInteligente\Infrastructure\Config\ConfigLoader;
use AgendaInteligente\Infrastructure\Database\Connection;

require_once dirname(__DIR__) . '/autoload.php';

function assertConnectionSame(
    mixed $expected,
    mixed $actual,
    string $message
): void {
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message
            . PHP_EOL
            . 'Esperado: '
            . var_export($expected, true)
            . PHP_EOL
            . 'Recebido: '
            . var_export($actual, true)
        );
    }
}

function assertConnectionTrue(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException(
            $message
        );
    }
}

$config =
    (
        new ConfigLoader(
            (string) AGENDA_ROOT
        )
    )->load();

$appConfig =
    $config['app']
    ?? null;

$databaseConfig =
    $config['database']
    ?? null;

if (!is_array($appConfig)) {
    throw new RuntimeException(
        'Configuração app inválida para o teste.'
    );
}

if (!is_array($databaseConfig)) {
    throw new RuntimeException(
        'Configuração database inválida para o teste.'
    );
}

assertConnectionSame(
    'testing',
    $appConfig['environment'] ?? null,
    'O teste de Connection somente pode usar o ambiente testing.'
);

assertConnectionSame(
    'agenda_inteligente_test',
    $databaseConfig['database'] ?? null,
    'O teste de Connection somente pode usar o banco de teste dedicado.'
);

assertConnectionSame(
    '127.0.0.1',
    $databaseConfig['host'] ?? null,
    'O teste de Connection somente pode usar o host local de teste.'
);

$tests = [];

$tests[
    'configura a sessão PDO em UTC'
] = static function () use (
    $databaseConfig
): void {
    $pdo =
        Connection::make(
            $databaseConfig
        );

    $database =
        $pdo
            ->query(
                'SELECT DATABASE()'
            )
            ->fetchColumn();

    assertConnectionSame(
        'agenda_inteligente_test',
        $database,
        'A Connection apontou para um banco inesperado.'
    );

    $row =
        $pdo
            ->query(
                "
                SELECT
                    @@session.time_zone
                        AS session_time_zone,
                    NOW()
                        AS session_now,
                    UTC_TIMESTAMP()
                        AS utc_now
                "
            )
            ->fetch();

    assertConnectionTrue(
        is_array($row),
        'Não foi possível consultar o timezone da sessão PDO.'
    );

    assertConnectionSame(
        '+00:00',
        $row['session_time_zone']
            ?? null,
        'A sessão PDO não foi configurada para UTC.'
    );

    assertConnectionSame(
        $row['utc_now']
            ?? null,
        $row['session_now']
            ?? null,
        'NOW() e UTC_TIMESTAMP() divergem na sessão PDO.'
    );
};

$tests[
    'reutiliza a instância já inicializada'
] = static function () use (
    $databaseConfig
): void {
    $first =
        Connection::make(
            $databaseConfig
        );

    $second =
        Connection::make(
            $databaseConfig
        );

    assertConnectionTrue(
        $first === $second,
        'Connection::make() não reutilizou a instância PDO.'
    );

    $timezone =
        $second
            ->query(
                'SELECT @@session.time_zone'
            )
            ->fetchColumn();

    assertConnectionSame(
        '+00:00',
        $timezone,
        'A instância reutilizada perdeu a configuração UTC.'
    );
};

$passed = 0;
$total = count($tests);

foreach ($tests as $name => $test) {
    try {
        $test();

        $passed++;

        echo '[OK] '
            . $name
            . PHP_EOL;
    } catch (Throwable $exception) {
        fwrite(
            STDERR,
            '[FALHA] '
            . $name
            . ': '
            . $exception->getMessage()
            . PHP_EOL
        );
    }
}

echo sprintf(
    'Connection: %d/%d teste(s) aprovado(s).%s',
    $passed,
    $total,
    PHP_EOL
);

exit(
    $passed === $total
        ? 0
        : 1
);
