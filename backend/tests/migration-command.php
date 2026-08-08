<?php

declare(strict_types=1);

use AgendaInteligente\Infrastructure\Config\ConfigLoader;
use AgendaInteligente\Infrastructure\Console\MigrationCommand;
use AgendaInteligente\Infrastructure\Database\Connection;
use AgendaInteligente\Infrastructure\Database\Migration\SchemaMigrationStore;

require_once dirname(__DIR__) . '/autoload.php';

$configPath =
    getenv(
        'AGENDA_CONFIG_FILE'
    );

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

$loader =
    new ConfigLoader(
        (string) AGENDA_ROOT
    );

$config =
    $loader->load();

$environment =
    (string) (
        $config['app']['environment']
        ?? ''
    );

$databaseConfig =
    $config['database']
    ?? [];

if ($environment !== 'testing') {
    fwrite(
        STDERR,
        "[ERRO] O teste exige app.environment=testing.\n"
    );

    exit(1);
}

if (!is_array($databaseConfig)) {
    fwrite(
        STDERR,
        "[ERRO] Configuração de banco inválida.\n"
    );

    exit(1);
}

$databaseName =
    (string) (
        $databaseConfig['database']
        ?? ''
    );

$databaseHost =
    (string) (
        $databaseConfig['host']
        ?? ''
    );

if (
    $databaseName
        !== 'agenda_inteligente_test'
    || $databaseHost
        !== '127.0.0.1'
) {
    fwrite(
        STDERR,
        "[ERRO] Banco de integração não autorizado.\n"
    );

    exit(1);
}

$pdo =
    Connection::make(
        $databaseConfig
    );

$currentDatabase =
    $pdo->query(
        'SELECT DATABASE()'
    )->fetchColumn();

if (
    $currentDatabase
        !== 'agenda_inteligente_test'
) {
    fwrite(
        STDERR,
        "[ERRO] Conexão não aponta para agenda_inteligente_test.\n"
    );

    exit(1);
}

function assertCommandTrue(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException(
            $message
        );
    }
}

function assertCommandSame(
    mixed $expected,
    mixed $actual,
    string $message
): void {
    if ($expected !== $actual) {
        throw new RuntimeException(
            sprintf(
                '%s Esperado: %s. Obtido: %s.',
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

function createCommandMigrationDirectory(): string
{
    $directory =
        sys_get_temp_dir()
        . '/agenda-migration-command-'
        . bin2hex(
            random_bytes(8)
        );

    if (
        !mkdir(
            $directory,
            0700,
            true
        )
        && !is_dir($directory)
    ) {
        throw new RuntimeException(
            'Não foi possível criar diretório temporário de migrations.'
        );
    }

    return $directory;
}

function removeCommandMigrationDirectory(
    string $directory
): void {
    if (!is_dir($directory)) {
        return;
    }

    $files =
        scandir(
            $directory
        );

    if ($files === false) {
        throw new RuntimeException(
            'Não foi possível listar diretório temporário.'
        );
    }

    foreach ($files as $file) {
        if (
            $file === '.'
            || $file === '..'
        ) {
            continue;
        }

        $path =
            $directory
            . DIRECTORY_SEPARATOR
            . $file;

        if (
            is_file($path)
            && !unlink($path)
        ) {
            throw new RuntimeException(
                sprintf(
                    'Não foi possível remover arquivo temporário: %s.',
                    $file
                )
            );
        }
    }

    if (!rmdir($directory)) {
        throw new RuntimeException(
            'Não foi possível remover diretório temporário.'
        );
    }
}

function writeCommandMigration(
    string $directory,
    string $filename,
    string $sql
): void {
    $path =
        $directory
        . DIRECTORY_SEPARATOR
        . $filename;

    if (
        file_put_contents(
            $path,
            $sql
        ) === false
    ) {
        throw new RuntimeException(
            sprintf(
                'Não foi possível escrever migration temporária: %s.',
                $filename
            )
        );
    }
}

function resetCommandDatabase(
    PDO $pdo
): void {
    $pdo->exec(
        "
        DROP TABLE IF EXISTS
            migration_cli_test,
            schema_migrations
        "
    );
}

function commandTableExists(
    PDO $pdo,
    string $table
): bool {
    $statement =
        $pdo->prepare(
            "
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
            "
        );

    $statement->execute([
        ':table' => $table,
    ]);

    return
        (int) $statement->fetchColumn()
        === 1;
}

/**
 * @return array{
 *     stdout:resource,
 *     stderr:resource
 * }
 */
function createCommandStreams(): array
{
    $stdout =
        fopen(
            'php://temp',
            'w+'
        );

    $stderr =
        fopen(
            'php://temp',
            'w+'
        );

    if (
        $stdout === false
        || $stderr === false
    ) {
        throw new RuntimeException(
            'Não foi possível criar streams temporários.'
        );
    }

    return [
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];
}

function readCommandStream(
    mixed $stream
): string {
    if (!is_resource($stream)) {
        throw new RuntimeException(
            'Stream de teste inválido.'
        );
    }

    rewind(
        $stream
    );

    $content =
        stream_get_contents(
            $stream
        );

    if ($content === false) {
        throw new RuntimeException(
            'Não foi possível ler stream do teste.'
        );
    }

    return $content;
}

function closeCommandStreams(
    array $streams
): void {
    foreach ($streams as $stream) {
        if (is_resource($stream)) {
            fclose(
                $stream
            );
        }
    }
}

function runCommandTest(
    string $name,
    callable $test
): bool {
    try {
        $test();

        fwrite(
            STDOUT,
            sprintf(
                "[OK] %s\n",
                $name
            )
        );

        return true;
    } catch (Throwable $exception) {
        fwrite(
            STDERR,
            sprintf(
                "[FALHA] %s: %s: %s\n",
                $name,
                $exception::class,
                $exception->getMessage()
            )
        );

        return false;
    }
}

$tests = [];

$tests[
    'executa migrations temporárias e informa quantidade aplicada'
] = static function () use ($pdo): void {
    resetCommandDatabase(
        $pdo
    );

    $directory =
        createCommandMigrationDirectory();

    $streams =
        createCommandStreams();

    try {
        $sql =
            "CREATE TABLE migration_cli_test (\n"
            . "    id INT NOT NULL PRIMARY KEY\n"
            . ") ENGINE=InnoDB;\n";

        writeCommandMigration(
            $directory,
            '001_cli_test.sql',
            $sql
        );

        $command =
            new MigrationCommand(
                (string) AGENDA_ROOT,
                $directory,
                $streams['stdout'],
                $streams['stderr']
            );

        assertCommandSame(
            0,
            $command->run(),
            'CLI deveria terminar com sucesso.'
        );

        assertCommandTrue(
            commandTableExists(
                $pdo,
                'migration_cli_test'
            ),
            'Migration temporária deveria criar a tabela.'
        );

        assertCommandTrue(
            str_contains(
                readCommandStream(
                    $streams['stdout']
                ),
                '1 aplicada(s)'
            ),
            'STDOUT deveria informar uma migration aplicada.'
        );

        assertCommandSame(
            '',
            readCommandStream(
                $streams['stderr']
            ),
            'STDERR deveria permanecer vazio no sucesso.'
        );

        $store =
            new SchemaMigrationStore(
                $pdo
            );

        assertCommandSame(
            [
                '001_cli_test'
                    => hash(
                        'sha256',
                        $sql
                    ),
            ],
            $store->applied(),
            'CLI deveria registrar checksum da migration temporária.'
        );
    } finally {
        closeCommandStreams(
            $streams
        );

        resetCommandDatabase(
            $pdo
        );

        removeCommandMigrationDirectory(
            $directory
        );
    }
};

$tests[
    'segunda execução informa zero migrations aplicadas'
] = static function () use ($pdo): void {
    resetCommandDatabase(
        $pdo
    );

    $directory =
        createCommandMigrationDirectory();

    $firstStreams =
        createCommandStreams();

    $secondStreams =
        createCommandStreams();

    try {
        writeCommandMigration(
            $directory,
            '001_cli_test.sql',
            "CREATE TABLE migration_cli_test (\n"
            . "    id INT NOT NULL PRIMARY KEY\n"
            . ") ENGINE=InnoDB;\n"
        );

        $first =
            new MigrationCommand(
                (string) AGENDA_ROOT,
                $directory,
                $firstStreams['stdout'],
                $firstStreams['stderr']
            );

        assertCommandSame(
            0,
            $first->run(),
            'Primeira execução deveria terminar com sucesso.'
        );

        $second =
            new MigrationCommand(
                (string) AGENDA_ROOT,
                $directory,
                $secondStreams['stdout'],
                $secondStreams['stderr']
            );

        assertCommandSame(
            0,
            $second->run(),
            'Segunda execução deveria terminar com sucesso.'
        );

        assertCommandTrue(
            str_contains(
                readCommandStream(
                    $secondStreams['stdout']
                ),
                '0 aplicada(s)'
            ),
            'Segunda execução deveria informar zero migrations.'
        );

        assertCommandSame(
            '',
            readCommandStream(
                $secondStreams['stderr']
            ),
            'Segunda execução não deveria produzir erro.'
        );
    } finally {
        closeCommandStreams(
            $firstStreams
        );

        closeCommandStreams(
            $secondStreams
        );

        resetCommandDatabase(
            $pdo
        );

        removeCommandMigrationDirectory(
            $directory
        );
    }
};

$tests[
    'retorna erro quando migration falha'
] = static function () use ($pdo): void {
    resetCommandDatabase(
        $pdo
    );

    $directory =
        createCommandMigrationDirectory();

    $streams =
        createCommandStreams();

    try {
        writeCommandMigration(
            $directory,
            '001_cli_broken.sql',
            "CREATE TABL migration_cli_test (\n"
            . "    id INT NOT NULL\n"
            . ");\n"
        );

        $command =
            new MigrationCommand(
                (string) AGENDA_ROOT,
                $directory,
                $streams['stdout'],
                $streams['stderr']
            );

        assertCommandSame(
            1,
            $command->run(),
            'CLI deveria retornar erro para migration inválida.'
        );

        assertCommandSame(
            '',
            readCommandStream(
                $streams['stdout']
            ),
            'Falha não deveria produzir mensagem de sucesso.'
        );

        $stderr =
            readCommandStream(
                $streams['stderr']
            );

        assertCommandTrue(
            str_contains(
                $stderr,
                'Falha ao executar migrations'
            ),
            'STDERR deveria informar falha do mecanismo.'
        );

        assertCommandTrue(
            str_contains(
                $stderr,
                '001_cli_broken'
            ),
            'STDERR deveria identificar a migration problemática.'
        );

        assertCommandTrue(
            !commandTableExists(
                $pdo,
                'migration_cli_test'
            ),
            'Migration inválida não deveria criar a tabela.'
        );

        $store =
            new SchemaMigrationStore(
                $pdo
            );

        assertCommandSame(
            [],
            $store->applied(),
            'Migration com falha não deveria ser registrada.'
        );
    } finally {
        closeCommandStreams(
            $streams
        );

        resetCommandDatabase(
            $pdo
        );

        removeCommandMigrationDirectory(
            $directory
        );
    }
};

$tests[
    'retorna erro quando configuração não existe'
] = static function (): void {
    $directory =
        createCommandMigrationDirectory();

    $streams =
        createCommandStreams();

    $originalConfig =
        getenv(
            'AGENDA_CONFIG_FILE'
        );

    try {
        putenv(
            'AGENDA_CONFIG_FILE=/tmp/agenda-config-inexistente.php'
        );

        $command =
            new MigrationCommand(
                (string) AGENDA_ROOT,
                $directory,
                $streams['stdout'],
                $streams['stderr']
            );

        assertCommandSame(
            1,
            $command->run(),
            'CLI deveria retornar erro de configuração.'
        );

        assertCommandSame(
            '',
            readCommandStream(
                $streams['stdout']
            ),
            'Erro de configuração não deveria produzir sucesso.'
        );

        assertCommandTrue(
            str_contains(
                readCommandStream(
                    $streams['stderr']
                ),
                'arquivo efetivo não foi encontrado'
            ),
            'STDERR deveria preservar erro conhecido de configuração.'
        );
    } finally {
        if (
            is_string($originalConfig)
            && $originalConfig !== ''
        ) {
            putenv(
                'AGENDA_CONFIG_FILE='
                . $originalConfig
            );
        } else {
            putenv(
                'AGENDA_CONFIG_FILE'
            );
        }

        closeCommandStreams(
            $streams
        );

        removeCommandMigrationDirectory(
            $directory
        );
    }
};

$passed = 0;
$total =
    count(
        $tests
    );

foreach (
    $tests as $name => $test
) {
    if (
        runCommandTest(
            $name,
            $test
        )
    ) {
        $passed++;
    }
}

resetCommandDatabase(
    $pdo
);

fwrite(
    STDOUT,
    sprintf(
        "\nResultado migration command: %d/%d testes aprovados.\n",
        $passed,
        $total
    )
);

exit(
    $passed === $total
        ? 0
        : 1
);
