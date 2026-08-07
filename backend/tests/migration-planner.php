<?php

declare(strict_types=1);

use AgendaInteligente\Infrastructure\Database\Migration\MigrationException;
use AgendaInteligente\Infrastructure\Database\Migration\MigrationFile;
use AgendaInteligente\Infrastructure\Database\Migration\MigrationPlanner;

require_once dirname(__DIR__) . '/autoload.php';

function assertPlannerSame(
    mixed $expected,
    mixed $actual,
    string $message
): void {
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message
        );
    }
}

function assertPlannerException(
    callable $callback,
    string $expectedMessage
): void {
    try {
        $callback();
    } catch (MigrationException $exception) {
        if (
            !str_contains(
                $exception->getMessage(),
                $expectedMessage
            )
        ) {
            throw new RuntimeException(
                sprintf(
                    'Mensagem inesperada: %s',
                    $exception->getMessage()
                )
            );
        }

        return;
    }

    throw new RuntimeException(
        sprintf(
            'Era esperada MigrationException contendo: %s',
            $expectedMessage
        )
    );
}

function createPlannerTestDirectory(): string
{
    $directory =
        sys_get_temp_dir()
        . DIRECTORY_SEPARATOR
        . 'agenda-migration-planner-'
        . bin2hex(
            random_bytes(8)
        );

    if (
        !mkdir(
            $directory,
            0700,
            true
        )
    ) {
        throw new RuntimeException(
            'Não foi possível criar diretório temporário.'
        );
    }

    return $directory;
}

function removePlannerTestDirectory(
    string $directory
): void {
    if (!is_dir($directory)) {
        return;
    }

    $entries =
        scandir(
            $directory
        );

    if ($entries === false) {
        return;
    }

    foreach ($entries as $entry) {
        if (
            $entry === '.'
            || $entry === '..'
        ) {
            continue;
        }

        $path =
            $directory
            . DIRECTORY_SEPARATOR
            . $entry;

        if (is_file($path)) {
            unlink(
                $path
            );
        }
    }

    rmdir(
        $directory
    );
}

/**
 * @param array<string, string> $files
 *
 * @return list<MigrationFile>
 */
function createPlannerMigrations(
    string $directory,
    array $files
): array {
    $migrations = [];

    foreach ($files as $filename => $contents) {
        $path =
            $directory
            . DIRECTORY_SEPARATOR
            . $filename;

        $written =
            file_put_contents(
                $path,
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

        $migrations[] =
            new MigrationFile(
                $path
            );
    }

    return $migrations;
}

/**
 * @param list<MigrationFile> $migrations
 *
 * @return list<string>
 */
function plannerVersions(
    array $migrations
): array {
    return array_map(
        static fn (
            MigrationFile $migration
        ): string =>
            $migration->version(),
        $migrations
    );
}

$tests = [];

$tests[
    'considera todas as migrations pendentes sem histórico aplicado'
] = static function (): void {
    $directory =
        createPlannerTestDirectory();

    try {
        $migrations =
            createPlannerMigrations(
                $directory,
                [
                    '001_initial_schema.sql' =>
                        "SELECT 1;\n",
                    '002_add_example.sql' =>
                        "SELECT 2;\n",
                ]
            );

        $planner =
            new MigrationPlanner();

        assertPlannerSame(
            [
                '001_initial_schema',
                '002_add_example',
            ],
            plannerVersions(
                $planner->pending(
                    $migrations,
                    []
                )
            ),
            'Todas as migrations deveriam estar pendentes.'
        );
    } finally {
        removePlannerTestDirectory(
            $directory
        );
    }
};

$tests[
    'não reexecuta migration aplicada com checksum válido'
] = static function (): void {
    $directory =
        createPlannerTestDirectory();

    try {
        $migrations =
            createPlannerMigrations(
                $directory,
                [
                    '001_initial_schema.sql' =>
                        "SELECT 1;\n",
                    '002_add_example.sql' =>
                        "SELECT 2;\n",
                ]
            );

        $planner =
            new MigrationPlanner();

        $applied = [
            $migrations[0]->version() =>
                $migrations[0]->checksum(),
        ];

        assertPlannerSame(
            [
                '002_add_example',
            ],
            plannerVersions(
                $planner->pending(
                    $migrations,
                    $applied
                )
            ),
            'Migration já aplicada não deveria voltar como pendente.'
        );
    } finally {
        removePlannerTestDirectory(
            $directory
        );
    }
};

$tests[
    'rejeita checksum divergente'
] = static function (): void {
    $directory =
        createPlannerTestDirectory();

    try {
        $migrations =
            createPlannerMigrations(
                $directory,
                [
                    '001_initial_schema.sql' =>
                        "SELECT 1;\n",
                ]
            );

        $planner =
            new MigrationPlanner();

        assertPlannerException(
            static fn (): array =>
                $planner->pending(
                    $migrations,
                    [
                        '001_initial_schema' =>
                            str_repeat(
                                '0',
                                64
                            ),
                    ]
                ),
            'Checksum divergente'
        );
    } finally {
        removePlannerTestDirectory(
            $directory
        );
    }
};

$tests[
    'rejeita migration aplicada ausente do conjunto local'
] = static function (): void {
    $directory =
        createPlannerTestDirectory();

    try {
        $migrations =
            createPlannerMigrations(
                $directory,
                [
                    '001_initial_schema.sql' =>
                        "SELECT 1;\n",
                ]
            );

        $planner =
            new MigrationPlanner();

        assertPlannerException(
            static fn (): array =>
                $planner->pending(
                    $migrations,
                    [
                        '001_initial_schema' =>
                            $migrations[0]->checksum(),
                        '002_missing_migration' =>
                            str_repeat(
                                'a',
                                64
                            ),
                    ]
                ),
            'Migration aplicada ausente do conjunto local'
        );
    } finally {
        removePlannerTestDirectory(
            $directory
        );
    }
};

$tests[
    'rejeita migration aplicada após migration pendente'
] = static function (): void {
    $directory =
        createPlannerTestDirectory();

    try {
        $migrations =
            createPlannerMigrations(
                $directory,
                [
                    '001_initial_schema.sql' =>
                        "SELECT 1;\n",
                    '002_pending_change.sql' =>
                        "SELECT 2;\n",
                    '003_later_change.sql' =>
                        "SELECT 3;\n",
                ]
            );

        $planner =
            new MigrationPlanner();

        assertPlannerException(
            static fn (): array =>
                $planner->pending(
                    $migrations,
                    [
                        '001_initial_schema' =>
                            $migrations[0]->checksum(),
                        '003_later_change' =>
                            $migrations[2]->checksum(),
                    ]
                ),
            'Histórico de migrations fora de ordem'
        );
    } finally {
        removePlannerTestDirectory(
            $directory
        );
    }
};

$passed = 0;
$total = count(
    $tests
);

foreach ($tests as $name => $test) {
    try {
        $test();

        $passed++;

        echo sprintf(
            "[OK] %s%s",
            $name,
            PHP_EOL
        );
    } catch (Throwable $exception) {
        echo sprintf(
            "[FALHA] %s: %s%s",
            $name,
            $exception->getMessage(),
            PHP_EOL
        );
    }
}

echo PHP_EOL;

echo sprintf(
    'Resultado planner: %d/%d testes aprovados.%s',
    $passed,
    $total,
    PHP_EOL
);

if ($passed !== $total) {
    exit(1);
}
