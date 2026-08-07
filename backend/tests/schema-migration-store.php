<?php

declare(strict_types=1);

use AgendaInteligente\Infrastructure\Config\ConfigLoader;
use AgendaInteligente\Infrastructure\Database\Connection;
use AgendaInteligente\Infrastructure\Database\Migration\MigrationException;
use AgendaInteligente\Infrastructure\Database\Migration\SchemaMigrationStore;

require_once dirname(__DIR__) . '/autoload.php';

$configPath = getenv('AGENDA_CONFIG_FILE');

if (
    !is_string($configPath)
    || trim($configPath) === ''
) {
    fwrite(
        STDERR,
        "[ERRO] AGENDA_CONFIG_FILE é obrigatório para este teste de integração.\n"
    );

    exit(1);
}

$loader = new ConfigLoader(
    (string) AGENDA_ROOT
);

$config = $loader->load();

$environment =
    (string) ($config['app']['environment'] ?? '');

$databaseConfig =
    $config['database'] ?? [];

$databaseName =
    is_array($databaseConfig)
        ? (string) ($databaseConfig['database'] ?? '')
        : '';

$databaseHost =
    is_array($databaseConfig)
        ? (string) ($databaseConfig['host'] ?? '')
        : '';

if (
    $environment !== 'testing'
    || $databaseName !== 'agenda_inteligente_test'
    || $databaseHost !== '127.0.0.1'
) {
    fwrite(
        STDERR,
        "[ERRO] Teste recusado: configuração não corresponde ao banco local de integração esperado.\n"
    );

    exit(1);
}

/** @var array{
 *     host:string,
 *     port:int,
 *     database:string,
 *     username:string,
 *     password:string,
 *     charset:string
 * } $databaseConfig
 */
$pdo = Connection::make(
    $databaseConfig
);

$currentDatabase =
    $pdo
        ->query('SELECT DATABASE()')
        ->fetchColumn();

if ($currentDatabase !== 'agenda_inteligente_test') {
    fwrite(
        STDERR,
        "[ERRO] Teste recusado: conexão não está no banco de integração esperado.\n"
    );

    exit(1);
}

function resetMigrationTable(PDO $pdo): void
{
    $pdo->exec(
        'DROP TABLE IF EXISTS schema_migrations'
    );
}

function assertSameValue(
    mixed $expected,
    mixed $actual,
    string $message
): void {
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message
            . ' Esperado: '
            . var_export($expected, true)
            . '; obtido: '
            . var_export($actual, true)
        );
    }
}

/**
 * @param callable(): void $callback
 */
function assertMigrationException(
    callable $callback,
    string $message
): void {
    try {
        $callback();
    } catch (MigrationException) {
        return;
    }

    throw new RuntimeException(
        $message
    );
}

$tests = [];

$tests[
    'cria schema_migrations no banco vazio'
] = static function () use ($pdo): void {
    resetMigrationTable($pdo);

    $store = new SchemaMigrationStore(
        $pdo
    );

    $store->ensureTable();

    $count = $pdo
        ->query(
            "
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'schema_migrations'
            "
        )
        ->fetchColumn();

    assertSameValue(
        1,
        (int) $count,
        'schema_migrations deveria existir.'
    );
};

$tests[
    'ensureTable é idempotente'
] = static function () use ($pdo): void {
    resetMigrationTable($pdo);

    $store = new SchemaMigrationStore(
        $pdo
    );

    $store->ensureTable();
    $store->ensureTable();

    assertSameValue(
        [],
        $store->applied(),
        'Histórico deveria permanecer vazio.'
    );
};

$tests[
    'retorna histórico vazio inicialmente'
] = static function () use ($pdo): void {
    resetMigrationTable($pdo);

    $store = new SchemaMigrationStore(
        $pdo
    );

    $store->ensureTable();

    assertSameValue(
        [],
        $store->applied(),
        'Histórico inicial deveria estar vazio.'
    );
};

$tests[
    'registra e recupera versão e checksum'
] = static function () use ($pdo): void {
    resetMigrationTable($pdo);

    $store = new SchemaMigrationStore(
        $pdo
    );

    $store->ensureTable();

    $checksum = str_repeat(
        'a',
        64
    );

    $store->record(
        '001_initial_schema',
        $checksum
    );

    assertSameValue(
        [
            '001_initial_schema'
                => $checksum,
        ],
        $store->applied(),
        'Migration registrada deveria ser recuperada.'
    );
};

$tests[
    'não sobrescreve migration já registrada'
] = static function () use ($pdo): void {
    resetMigrationTable($pdo);

    $store = new SchemaMigrationStore(
        $pdo
    );

    $store->ensureTable();

    $originalChecksum = str_repeat(
        'a',
        64
    );

    $newChecksum = str_repeat(
        'b',
        64
    );

    $store->record(
        '001_initial_schema',
        $originalChecksum
    );

    $duplicateRejected = false;

    try {
        $store->record(
            '001_initial_schema',
            $newChecksum
        );
    } catch (\PDOException) {
        $duplicateRejected = true;
    }

    assertSameValue(
        true,
        $duplicateRejected,
        'Registrar novamente a mesma versão deveria falhar por duplicidade.'
    );

    assertSameValue(
        [
            '001_initial_schema'
                => $originalChecksum,
        ],
        $store->applied(),
        'Registro original não deveria ser alterado.'
    );
};

$tests[
    'retorna histórico em ordem determinística'
] = static function () use ($pdo): void {
    resetMigrationTable($pdo);

    $store = new SchemaMigrationStore(
        $pdo
    );

    $store->ensureTable();

    $store->record(
        '002_second',
        str_repeat('b', 64)
    );

    $store->record(
        '001_first',
        str_repeat('a', 64)
    );

    assertSameValue(
        [
            '001_first' => str_repeat('a', 64),
            '002_second' => str_repeat('b', 64),
        ],
        $store->applied(),
        'Histórico deveria ser ordenado por versão.'
    );
};

$tests[
    'rejeita checksum inválido antes de persistir'
] = static function () use ($pdo): void {
    resetMigrationTable($pdo);

    $store = new SchemaMigrationStore(
        $pdo
    );

    $store->ensureTable();

    assertMigrationException(
        static function () use ($store): void {
            $store->record(
                '001_invalid_checksum',
                'invalido'
            );
        },
        'Checksum inválido deveria ser rejeitado.'
    );

    assertSameValue(
        [],
        $store->applied(),
        'Registro inválido não deveria ser persistido.'
    );
};

$tests[
    'rejeita schema_migrations incompatível'
] = static function () use ($pdo): void {
    resetMigrationTable($pdo);

    $pdo->exec(
        "
        CREATE TABLE schema_migrations (
            version VARCHAR(190) NOT NULL,
            PRIMARY KEY (version)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci
        "
    );

    $store = new SchemaMigrationStore(
        $pdo
    );

    assertMigrationException(
        static function () use ($store): void {
            $store->ensureTable();
        },
        'Tabela incompatível deveria ser rejeitada.'
    );
};

$approved = 0;
$total = count($tests);

try {
    foreach ($tests as $name => $test) {
        try {
            $test();
            $approved++;

            echo '[OK] '
                . $name
                . PHP_EOL;
        } catch (Throwable $exception) {
            echo '[FALHOU] '
                . $name
                . PHP_EOL;

            echo '  '
                . $exception->getMessage()
                . PHP_EOL;
        }
    }
} finally {
    resetMigrationTable($pdo);
}

echo PHP_EOL;
echo sprintf(
    'Resultado schema migration store: %d/%d testes aprovados.',
    $approved,
    $total
);
echo PHP_EOL;

exit(
    $approved === $total
        ? 0
        : 1
);
