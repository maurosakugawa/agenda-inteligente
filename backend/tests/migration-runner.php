<?php

declare(strict_types=1);

use AgendaInteligente\Infrastructure\Config\ConfigLoader;
use AgendaInteligente\Infrastructure\Database\Connection;
use AgendaInteligente\Infrastructure\Database\Migration\MigrationException;
use AgendaInteligente\Infrastructure\Database\Migration\MigrationLock;
use AgendaInteligente\Infrastructure\Database\Migration\MigrationRunner;
use AgendaInteligente\Infrastructure\Database\Migration\SchemaMigrationStore;

require_once dirname(__DIR__) . '/autoload.php';

$configPath = getenv(
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

/**
 * @param array{
 *     host:string,
 *     port:int,
 *     database:string,
 *     username:string,
 *     password:string,
 *     charset:string
 * } $databaseConfig
 */
function makeIndependentRunnerConnection(
    array $databaseConfig
): PDO {
    $dsn =
        sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $databaseConfig['host'],
            $databaseConfig['port'],
            $databaseConfig['database'],
            $databaseConfig['charset']
        );

    return new PDO(
        $dsn,
        $databaseConfig['username'],
        $databaseConfig['password'],
        [
            PDO::ATTR_ERRMODE
                => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE
                => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES
                => false,
            PDO::ATTR_STRINGIFY_FETCHES
                => false,
        ]
    );
}

function runnerMigrationLockName(
    PDO $pdo
): string {
    $database =
        $pdo->query(
            'SELECT DATABASE()'
        )->fetchColumn();

    if (
        !is_string($database)
        || $database === ''
    ) {
        throw new RuntimeException(
            'Não foi possível identificar banco para teste do lock.'
        );
    }

    return
        'agenda-inteligente:migrations:'
        . substr(
            hash(
                'sha256',
                $database
            ),
            0,
            32
        );
}

function createRunnerMigrationDirectory(): string
{
    $directory =
        sys_get_temp_dir()
        . DIRECTORY_SEPARATOR
        . 'agenda-migration-runner-'
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

function removeRunnerMigrationDirectory(
    string $directory
): void {
    if (!is_dir($directory)) {
        return;
    }

    $files =
        scandir(
            $directory
        );

    if (is_array($files)) {
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

            if (is_file($path)) {
                unlink(
                    $path
                );
            }
        }
    }

    rmdir(
        $directory
    );
}

function writeRunnerMigration(
    string $directory,
    string $filename,
    string $contents
): void {
    $written =
        file_put_contents(
            $directory
            . DIRECTORY_SEPARATOR
            . $filename,
            $contents
        );

    if ($written === false) {
        throw new RuntimeException(
            sprintf(
                'Não foi possível criar migration de teste: %s.',
                $filename
            )
        );
    }
}

function resetRunnerDatabase(
    PDO $pdo
): void {
    $pdo->exec(
        "
        DROP TABLE IF EXISTS
            runner_double_failure,
            runner_lock_success,
            runner_lock_blocked,
            runner_partial_created,
            runner_after_failure,
            runner_before_failure,
            runner_multi_second,
            runner_multi_first,
            runner_second,
            runner_first,
            runner_once,
            schema_migrations
        "
    );
}

function runnerTableExists(
    PDO $pdo,
    string $tableName
): bool {
    $statement =
        $pdo->prepare(
            "
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
            "
        );

    $statement->execute([
        ':table_name' => $tableName,
    ]);

    return (int) $statement->fetchColumn()
        === 1;
}

function assertRunnerSame(
    mixed $expected,
    mixed $actual,
    string $message
): void {
    if ($expected !== $actual) {
        throw new RuntimeException(
            sprintf(
                "%s\nEsperado: %s\nObtido: %s",
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

function assertRunnerTrue(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException(
            $message
        );
    }
}

function runRunnerTest(
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
    'preserva falha da migration quando liberação do lock também falha'
] = static function () use ($pdo): void {
    resetRunnerDatabase(
        $pdo
    );

    $directory =
        createRunnerMigrationDirectory();

    $lockName =
        runnerMigrationLockName(
            $pdo
        );

    $quotedLockName =
        $pdo->quote(
            $lockName
        );

    $sql =
        sprintf(
            "DO RELEASE_LOCK(%s);\n"
            . "CREATE TABL runner_double_failure (\n"
            . "    id INT NOT NULL\n"
            . ");\n",
            $quotedLockName
        );

    try {
        writeRunnerMigration(
            $directory,
            '001_double_failure.sql',
            $sql
        );

        $runner =
            new MigrationRunner(
                $pdo,
                $directory,
                0
            );

        $failure = null;

        try {
            $runner->run();
        } catch (MigrationException $exception) {
            $failure = $exception;
        }

        assertRunnerTrue(
            $failure instanceof MigrationException,
            'Runner deveria reportar a falha dupla.'
        );

        assertRunnerTrue(
            str_contains(
                $failure->getMessage(),
                'também ao liberar lock'
            ),
            'Erro final deveria informar também a falha de liberação.'
        );

        $migrationFailure =
            $failure->getPrevious();

        assertRunnerTrue(
            $migrationFailure
                instanceof MigrationException,
            'Falha original da migration deveria ser preservada.'
        );

        assertRunnerTrue(
            str_contains(
                $migrationFailure->getMessage(),
                '001_double_failure'
            ),
            'Falha preservada deveria identificar a migration original.'
        );

        assertRunnerTrue(
            $migrationFailure->getPrevious()
                instanceof PDOException,
            'PDOException original deveria permanecer na cadeia.'
        );

        assertRunnerTrue(
            !runnerTableExists(
                $pdo,
                'runner_double_failure'
            ),
            'Tabela inválida não deveria existir.'
        );
    } finally {
        resetRunnerDatabase(
            $pdo
        );

        removeRunnerMigrationDirectory(
            $directory
        );
    }
};

$tests[
    'não inicia migrations quando lock está ocupado'
] = static function () use (
    $pdo,
    $databaseConfig
): void {
    resetRunnerDatabase(
        $pdo
    );

    $directory =
        createRunnerMigrationDirectory();

    $sql =
        "CREATE TABLE runner_lock_blocked (\n"
        . "    id INT NOT NULL PRIMARY KEY\n"
        . ") ENGINE=InnoDB;\n";

    $secondPdo =
        makeIndependentRunnerConnection(
            $databaseConfig
        );

    $blockingLock =
        new MigrationLock(
            $secondPdo,
            0
        );

    $blockingAcquired = false;

    try {
        writeRunnerMigration(
            $directory,
            '001_lock_blocked.sql',
            $sql
        );

        $blockingLock->acquire();
        $blockingAcquired = true;

        $runner =
            new MigrationRunner(
                $pdo,
                $directory,
                0
            );

        $failure = null;

        try {
            $runner->run();
        } catch (MigrationException $exception) {
            $failure = $exception;
        }

        assertRunnerTrue(
            $failure instanceof MigrationException,
            'Runner deveria falhar quando outro processo possui o lock.'
        );

        assertRunnerTrue(
            str_contains(
                $failure->getMessage(),
                'Tempo limite excedido'
            ),
            'Falha deveria indicar timeout ao adquirir o lock.'
        );

        assertRunnerTrue(
            !runnerTableExists(
                $pdo,
                'schema_migrations'
            ),
            'Runner bloqueado não deveria preparar schema_migrations.'
        );

        assertRunnerTrue(
            !runnerTableExists(
                $pdo,
                'runner_lock_blocked'
            ),
            'Runner bloqueado não deveria executar migrations.'
        );
    } finally {
        if ($blockingAcquired) {
            $blockingLock->release();
        }

        resetRunnerDatabase(
            $pdo
        );

        removeRunnerMigrationDirectory(
            $directory
        );
    }
};

$tests[
    'libera lock após execução bem-sucedida'
] = static function () use (
    $pdo,
    $databaseConfig
): void {
    resetRunnerDatabase(
        $pdo
    );

    $directory =
        createRunnerMigrationDirectory();

    $sql =
        "CREATE TABLE runner_lock_success (\n"
        . "    id INT NOT NULL PRIMARY KEY\n"
        . ") ENGINE=InnoDB;\n";

    try {
        writeRunnerMigration(
            $directory,
            '001_lock_success.sql',
            $sql
        );

        $runner =
            new MigrationRunner(
                $pdo,
                $directory,
                0
            );

        assertRunnerSame(
            1,
            $runner->run(),
            'Runner deveria aplicar a migration protegida pelo lock.'
        );

        $secondPdo =
            makeIndependentRunnerConnection(
                $databaseConfig
            );

        $secondLock =
            new MigrationLock(
                $secondPdo,
                0
            );

        $secondAcquired = false;

        try {
            $secondLock->acquire();
            $secondAcquired = true;

            assertRunnerTrue(
                $secondAcquired,
                'Outra conexão deveria adquirir o lock após sucesso do Runner.'
            );
        } finally {
            if ($secondAcquired) {
                $secondLock->release();
            }
        }
    } finally {
        resetRunnerDatabase(
            $pdo
        );

        removeRunnerMigrationDirectory(
            $directory
        );
    }
};

$tests[
    'libera lock após falha de migration'
] = static function () use (
    $pdo,
    $databaseConfig
): void {
    resetRunnerDatabase(
        $pdo
    );

    $directory =
        createRunnerMigrationDirectory();

    $sql =
        "CREATE TABL runner_lock_failure (\n"
        . "    id INT NOT NULL\n"
        . ");\n";

    try {
        writeRunnerMigration(
            $directory,
            '001_lock_failure.sql',
            $sql
        );

        $runner =
            new MigrationRunner(
                $pdo,
                $directory,
                0
            );

        $failure = null;

        try {
            $runner->run();
        } catch (MigrationException $exception) {
            $failure = $exception;
        }

        assertRunnerTrue(
            $failure instanceof MigrationException,
            'Runner deveria propagar a falha da migration.'
        );

        assertRunnerTrue(
            str_contains(
                $failure->getMessage(),
                '001_lock_failure'
            ),
            'Falha deveria identificar a migration problemática.'
        );

        $secondPdo =
            makeIndependentRunnerConnection(
                $databaseConfig
            );

        $secondLock =
            new MigrationLock(
                $secondPdo,
                0
            );

        $secondAcquired = false;

        try {
            $secondLock->acquire();
            $secondAcquired = true;

            assertRunnerTrue(
                $secondAcquired,
                'Outra conexão deveria adquirir o lock após falha do Runner.'
            );
        } finally {
            if ($secondAcquired) {
                $secondLock->release();
            }
        }
    } finally {
        resetRunnerDatabase(
            $pdo
        );

        removeRunnerMigrationDirectory(
            $directory
        );
    }
};

$tests[
    'aplica migrations pendentes e registra checksums'
] = static function () use ($pdo): void {
    resetRunnerDatabase(
        $pdo
    );

    $directory =
        createRunnerMigrationDirectory();

    $firstSql =
        "CREATE TABLE runner_first (\n"
        . "    id INT NOT NULL PRIMARY KEY\n"
        . ") ENGINE=InnoDB;\n";

    $secondSql =
        "CREATE TABLE runner_second (\n"
        . "    id INT NOT NULL PRIMARY KEY\n"
        . ") ENGINE=InnoDB;\n";

    try {
        writeRunnerMigration(
            $directory,
            '001_create_first.sql',
            $firstSql
        );

        writeRunnerMigration(
            $directory,
            '002_create_second.sql',
            $secondSql
        );

        $runner =
            new MigrationRunner(
                $pdo,
                $directory
            );

        assertRunnerSame(
            2,
            $runner->run(),
            'Duas migrations deveriam ser aplicadas.'
        );

        assertRunnerTrue(
            runnerTableExists(
                $pdo,
                'runner_first'
            ),
            'A primeira tabela deveria existir.'
        );

        assertRunnerTrue(
            runnerTableExists(
                $pdo,
                'runner_second'
            ),
            'A segunda tabela deveria existir.'
        );

        $store =
            new SchemaMigrationStore(
                $pdo
            );

        assertRunnerSame(
            [
                '001_create_first'
                    => hash(
                        'sha256',
                        $firstSql
                    ),
                '002_create_second'
                    => hash(
                        'sha256',
                        $secondSql
                    ),
            ],
            $store->applied(),
            'Histórico deveria conter os checksums exatos aplicados.'
        );
    } finally {
        resetRunnerDatabase(
            $pdo
        );

        removeRunnerMigrationDirectory(
            $directory
        );
    }
};

$tests[
    'não reexecuta migration já aplicada'
] = static function () use ($pdo): void {
    resetRunnerDatabase(
        $pdo
    );

    $directory =
        createRunnerMigrationDirectory();

    $sql =
        "CREATE TABLE runner_once (\n"
        . "    id INT NOT NULL PRIMARY KEY\n"
        . ") ENGINE=InnoDB;\n";

    try {
        writeRunnerMigration(
            $directory,
            '001_create_once.sql',
            $sql
        );

        $runner =
            new MigrationRunner(
                $pdo,
                $directory
            );

        assertRunnerSame(
            1,
            $runner->run(),
            'Primeira execução deveria aplicar uma migration.'
        );

        assertRunnerSame(
            0,
            $runner->run(),
            'Segunda execução não deveria reaplicar migration registrada.'
        );

        assertRunnerTrue(
            runnerTableExists(
                $pdo,
                'runner_once'
            ),
            'Tabela criada na primeira execução deveria permanecer.'
        );
    } finally {
        resetRunnerDatabase(
            $pdo
        );

        removeRunnerMigrationDirectory(
            $directory
        );
    }
};

$tests[
    'executa arquivo com múltiplas instruções SQL'
] = static function () use ($pdo): void {
    resetRunnerDatabase(
        $pdo
    );

    $directory =
        createRunnerMigrationDirectory();

    $sql =
        "CREATE TABLE runner_multi_first (\n"
        . "    id INT NOT NULL PRIMARY KEY\n"
        . ") ENGINE=InnoDB;\n"
        . "\n"
        . "CREATE TABLE runner_multi_second (\n"
        . "    id INT NOT NULL PRIMARY KEY\n"
        . ") ENGINE=InnoDB;\n";

    try {
        writeRunnerMigration(
            $directory,
            '001_create_multiple.sql',
            $sql
        );

        $runner =
            new MigrationRunner(
                $pdo,
                $directory
            );

        assertRunnerSame(
            1,
            $runner->run(),
            'Arquivo deveria ser tratado como uma única migration.'
        );

        assertRunnerTrue(
            runnerTableExists(
                $pdo,
                'runner_multi_first'
            ),
            'Primeira instrução SQL deveria ter sido executada.'
        );

        assertRunnerTrue(
            runnerTableExists(
                $pdo,
                'runner_multi_second'
            ),
            'Segunda instrução SQL deveria ter sido executada.'
        );

        $store =
            new SchemaMigrationStore(
                $pdo
            );

        assertRunnerSame(
            [
                '001_create_multiple'
                    => hash(
                        'sha256',
                        $sql
                    ),
            ],
            $store->applied(),
            'Checksum deve representar o arquivo SQL completo executado.'
        );
    } finally {
        resetRunnerDatabase(
            $pdo
        );

        removeRunnerMigrationDirectory(
            $directory
        );
    }
};

$tests[
    'não registra migration parcialmente executada'
] = static function () use ($pdo): void {
    resetRunnerDatabase(
        $pdo
    );

    $directory =
        createRunnerMigrationDirectory();

    $sql =
        "CREATE TABLE runner_partial_created (\n"
        . "    id INT NOT NULL PRIMARY KEY\n"
        . ") ENGINE=InnoDB;\n"
        . "\n"
        . "CREATE TABL runner_partial_broken (\n"
        . "    id INT NOT NULL\n"
        . ");\n";

    try {
        writeRunnerMigration(
            $directory,
            '001_partial_failure.sql',
            $sql
        );

        $runner =
            new MigrationRunner(
                $pdo,
                $directory
            );

        $failure = null;

        try {
            $runner->run();
        } catch (MigrationException $exception) {
            $failure = $exception;
        }

        assertRunnerTrue(
            $failure instanceof MigrationException,
            'Runner deveria informar falha da migration parcial.'
        );

        assertRunnerTrue(
            str_contains(
                $failure->getMessage(),
                '001_partial_failure'
            ),
            'Erro deveria identificar a migration parcialmente executada.'
        );

        assertRunnerTrue(
            $failure->getPrevious()
                instanceof PDOException,
            'Falha técnica do PDO deveria ser preservada.'
        );

        assertRunnerTrue(
            runnerTableExists(
                $pdo,
                'runner_partial_created'
            ),
            'DDL concluído antes da falha deveria permanecer materializado.'
        );

        $store =
            new SchemaMigrationStore(
                $pdo
            );

        assertRunnerSame(
            [],
            $store->applied(),
            'Migration parcialmente executada não pode ser registrada.'
        );
    } finally {
        resetRunnerDatabase(
            $pdo
        );

        removeRunnerMigrationDirectory(
            $directory
        );
    }
};

$tests[
    'interrompe na primeira falha e não registra migration com erro'
] = static function () use ($pdo): void {
    resetRunnerDatabase(
        $pdo
    );

    $directory =
        createRunnerMigrationDirectory();

    $firstSql =
        "CREATE TABLE runner_before_failure (\n"
        . "    id INT NOT NULL PRIMARY KEY\n"
        . ") ENGINE=InnoDB;\n";

    $brokenSql =
        "CREATE TABL runner_broken (\n"
        . "    id INT NOT NULL\n"
        . ");\n";

    $thirdSql =
        "CREATE TABLE runner_after_failure (\n"
        . "    id INT NOT NULL PRIMARY KEY\n"
        . ") ENGINE=InnoDB;\n";

    try {
        writeRunnerMigration(
            $directory,
            '001_before_failure.sql',
            $firstSql
        );

        writeRunnerMigration(
            $directory,
            '002_broken.sql',
            $brokenSql
        );

        writeRunnerMigration(
            $directory,
            '003_after_failure.sql',
            $thirdSql
        );

        $runner =
            new MigrationRunner(
                $pdo,
                $directory
            );

        $failure = null;

        try {
            $runner->run();
        } catch (MigrationException $exception) {
            $failure = $exception;
        }

        assertRunnerTrue(
            $failure instanceof MigrationException,
            'Runner deveria interromper com MigrationException.'
        );

        assertRunnerTrue(
            str_contains(
                $failure->getMessage(),
                '002_broken'
            ),
            'Erro deveria identificar a migration que falhou.'
        );

        assertRunnerTrue(
            $failure->getPrevious()
                instanceof PDOException,
            'Exceção técnica PDO deveria ser preservada.'
        );

        assertRunnerTrue(
            runnerTableExists(
                $pdo,
                'runner_before_failure'
            ),
            'Migration anterior bem-sucedida deveria permanecer aplicada.'
        );

        assertRunnerTrue(
            !runnerTableExists(
                $pdo,
                'runner_after_failure'
            ),
            'Migration posterior à falha não deveria ser executada.'
        );

        $store =
            new SchemaMigrationStore(
                $pdo
            );

        assertRunnerSame(
            [
                '001_before_failure'
                    => hash(
                        'sha256',
                        $firstSql
                    ),
            ],
            $store->applied(),
            'Somente migration concluída antes da falha deveria estar registrada.'
        );
    } finally {
        resetRunnerDatabase(
            $pdo
        );

        removeRunnerMigrationDirectory(
            $directory
        );
    }
};

$passed = 0;
$total = count(
    $tests
);

try {
    resetRunnerDatabase(
        $pdo
    );

    foreach (
        $tests as $name => $test
    ) {
        if (
            runRunnerTest(
                $name,
                $test
            )
        ) {
            $passed++;
        }
    }
} finally {
    resetRunnerDatabase(
        $pdo
    );
}

fwrite(
    STDOUT,
    sprintf(
        "\nResultado migration runner: %d/%d testes aprovados.\n",
        $passed,
        $total
    )
);

exit(
    $passed === $total
        ? 0
        : 1
);
