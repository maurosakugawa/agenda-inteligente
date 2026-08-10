<?php

declare(strict_types=1);

use AgendaInteligente\Infrastructure\Config\ConfigLoader;
use AgendaInteligente\Infrastructure\Database\Connection;
use AgendaInteligente\Infrastructure\Database\Migration\MigrationException;
use AgendaInteligente\Infrastructure\Database\Migration\MigrationLock;

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
        "[ERRO] Conexão principal não aponta para agenda_inteligente_test.\n"
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
function makeIndependentLockConnection(
    array $databaseConfig
): \PDO {
    $dsn =
        sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $databaseConfig['host'],
            $databaseConfig['port'],
            $databaseConfig['database'],
            $databaseConfig['charset']
        );

    return new \PDO(
        $dsn,
        $databaseConfig['username'],
        $databaseConfig['password'],
        [
            \PDO::ATTR_ERRMODE
                => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE
                => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES
                => false,
            \PDO::ATTR_STRINGIFY_FETCHES
                => false,
        ]
    );
}

function assertLockTrue(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException(
            $message
        );
    }
}

function assertLockException(
    callable $callback,
    string $expectedMessage
): MigrationException {
    try {
        $callback();
    } catch (MigrationException $exception) {
        assertLockTrue(
            str_contains(
                $exception->getMessage(),
                $expectedMessage
            ),
            sprintf(
                'Mensagem inesperada: %s',
                $exception->getMessage()
            )
        );

        return $exception;
    }

    throw new RuntimeException(
        sprintf(
            'Era esperada MigrationException contendo: %s',
            $expectedMessage
        )
    );
}

function runLockTest(
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
    'adquire e libera lock'
] = static function () use ($pdo): void {
    $lock =
        new MigrationLock(
            $pdo,
            0
        );

    $lock->acquire();
    $lock->release();
};

$tests[
    'bloqueia segunda conexão e permite após liberação'
] = static function () use (
    $pdo,
    $databaseConfig
): void {
    $secondPdo =
        makeIndependentLockConnection(
            $databaseConfig
        );

    assertLockTrue(
        $pdo->query(
            'SELECT CONNECTION_ID()'
        )->fetchColumn()
            !== $secondPdo->query(
                'SELECT CONNECTION_ID()'
            )->fetchColumn(),
        'O teste exige duas sessões de banco independentes.'
    );

    $firstLock =
        new MigrationLock(
            $pdo,
            0
        );

    $secondLock =
        new MigrationLock(
            $secondPdo,
            0
        );

    $firstAcquired = false;
    $secondAcquired = false;

    try {
        $firstLock->acquire();
        $firstAcquired = true;

        assertLockException(
            static function () use (
                $secondLock
            ): void {
                $secondLock->acquire();
            },
            'Tempo limite excedido'
        );

        $firstLock->release();
        $firstAcquired = false;

        $secondLock->acquire();
        $secondAcquired = true;

        $secondLock->release();
        $secondAcquired = false;
    } finally {
        if ($secondAcquired) {
            try {
                $secondLock->release();
            } catch (Throwable) {
            }
        }

        if ($firstAcquired) {
            try {
                $firstLock->release();
            } catch (Throwable) {
            }
        }
    }
};

$tests[
    'rejeita segunda aquisição pela mesma instância'
] = static function () use ($pdo): void {
    $lock =
        new MigrationLock(
            $pdo,
            0
        );

    $acquired = false;

    try {
        $lock->acquire();
        $acquired = true;

        assertLockException(
            static function () use (
                $lock
            ): void {
                $lock->acquire();
            },
            'já foi adquirido'
        );
    } finally {
        if ($acquired) {
            $lock->release();
        }
    }
};

$tests[
    'rejeita timeout negativo'
] = static function () use ($pdo): void {
    assertLockException(
        static function () use (
            $pdo
        ): void {
            new MigrationLock(
                $pdo,
                -1
            );
        },
        'não pode ser negativo'
    );
};

$tests[
    'rejeita liberação sem aquisição'
] = static function () use ($pdo): void {
    $lock =
        new MigrationLock(
            $pdo,
            0
        );

    assertLockException(
        static function () use (
            $lock
        ): void {
            $lock->release();
        },
        'não está adquirido'
    );
};

$passed = 0;
$total = count(
    $tests
);

foreach (
    $tests as $name => $test
) {
    if (
        runLockTest(
            $name,
            $test
        )
    ) {
        $passed++;
    }
}

fwrite(
    STDOUT,
    sprintf(
        "\nResultado migration lock: %d/%d testes aprovados.\n",
        $passed,
        $total
    )
);

exit(
    $passed === $total
        ? 0
        : 1
);
