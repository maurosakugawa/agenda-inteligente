<?php

declare(strict_types=1);

use AgendaInteligente\Infrastructure\Database\Migration\MigrationDiscovery;
use AgendaInteligente\Infrastructure\Database\Migration\MigrationException;
use AgendaInteligente\Infrastructure\Database\Migration\MigrationFile;

require_once dirname(__DIR__) . '/autoload.php';

function assertMigrationTrue(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException(
            $message
        );
    }
}

function assertMigrationSame(
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

/**
 * @param callable(): void $callback
 */
function assertMigrationException(
    callable $callback,
    string $expectedMessagePart
): void {
    try {
        $callback();
    } catch (MigrationException $exception) {
        assertMigrationTrue(
            str_contains(
                $exception->getMessage(),
                $expectedMessagePart
            ),
            sprintf(
                'Mensagem inesperada: %s',
                $exception->getMessage()
            )
        );

        return;
    }

    throw new RuntimeException(
        'Era esperada uma MigrationException.'
    );
}

function writeMigrationTestFile(
    string $directory,
    string $filename
): void {
    $written = file_put_contents(
        $directory
        . DIRECTORY_SEPARATOR
        . $filename,
        "-- teste\nSELECT 1;\n"
    );

    if ($written === false) {
        throw new RuntimeException(
            'Não foi possível criar migration temporária.'
        );
    }
}

function createMigrationTestDirectory(
    string $prefix
): string {
    $directory =
        sys_get_temp_dir()
        . DIRECTORY_SEPARATOR
        . $prefix
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

function removeMigrationTestDirectory(
    string $directory
): void {
    if (!is_dir($directory)) {
        return;
    }

    $entries = scandir(
        $directory
    );

    if (is_array($entries)) {
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
    }

    rmdir(
        $directory
    );
}

/**
 * @param callable(): void $test
 */
function runMigrationTest(
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
                "[FALHA] %s: %s\n",
                $name,
                $exception->getMessage()
            )
        );

        return false;
    }
}

$temporaryPath =
    sys_get_temp_dir()
    . '/agenda-migration-tests-'
    . bin2hex(
        random_bytes(8)
    );

if (
    !mkdir(
        $temporaryPath,
        0700,
        true
    )
) {
    throw new RuntimeException(
        'Não foi possível criar diretório temporário.'
    );
}

$tests = [];

$tests[
    'extrai identidade de nome válido'
] = static function (): void {
    $migration =
        new MigrationFile(
            '/tmp/001_initial_schema.sql'
        );

    assertMigrationSame(
        '001_initial_schema.sql',
        $migration->filename(),
        'O nome da migration está incorreto.'
    );

    assertMigrationSame(
        '001_initial_schema',
        $migration->version(),
        'A versão da migration está incorreta.'
    );

    assertMigrationSame(
        1,
        $migration->sequence(),
        'A sequência da migration está incorreta.'
    );
};

$tests[
    'rejeita nomes inválidos'
] = static function (): void {
    $invalidNames = [
        '000_initial_schema.sql',
        '1_initial.sql',
        '001-initial.sql',
        '001_Initial.sql',
        '001_.sql',
        '001.sql',
        'backup.sql',
    ];

    foreach (
        $invalidNames as $filename
    ) {
        assertMigrationException(
            static fn (): MigrationFile =>
                new MigrationFile(
                    '/tmp/' . $filename
                ),
            'Nome de migration inválido'
        );
    }
};

$tests[
    'descobre migrations em ordem determinística'
] = static function () use (
    $temporaryPath
): void {
    $files = [
        '010_create_index.sql',
        '001_initial_schema.sql',
        '002_add_user_field.sql',
    ];

    try {
        foreach ($files as $filename) {
            writeMigrationTestFile(
                $temporaryPath,
                $filename
            );
        }

        $discovery =
            new MigrationDiscovery(
                $temporaryPath
            );

        $versions = array_map(
            static fn (
                MigrationFile $migration
            ): string =>
                $migration->version(),
            $discovery->discover()
        );

        assertMigrationSame(
            [
                '001_initial_schema',
                '002_add_user_field',
                '010_create_index',
            ],
            $versions,
            'As migrations não foram ordenadas corretamente.'
        );
    } finally {
        foreach ($files as $filename) {
            $file =
                $temporaryPath
                . DIRECTORY_SEPARATOR
                . $filename;

            if (is_file($file)) {
                unlink(
                    $file
                );
            }
        }
    }
};

$tests[
    'ignora arquivos que não são SQL'
] = static function (): void {
    $directory =
        sys_get_temp_dir()
        . '/agenda-migration-ignore-tests-'
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

    try {
        writeMigrationTestFile(
            $directory,
            '001_initial_schema.sql'
        );

        file_put_contents(
            $directory
            . '/README.md',
            '# teste'
        );

        $discovery =
            new MigrationDiscovery(
                $directory
            );

        $migrations =
            $discovery->discover();

        assertMigrationSame(
            1,
            count(
                $migrations
            ),
            'Arquivo não SQL foi tratado como migration.'
        );

        assertMigrationSame(
            '001_initial_schema',
            $migrations[0]->version(),
            'A migration SQL esperada não foi encontrada.'
        );
    } finally {
        removeMigrationTestDirectory(
            $directory
        );
    }
};

$tests[
    'rejeita migration SQL com nome inválido'
] = static function () use (
    $temporaryPath
): void {
    $file =
        $temporaryPath
        . DIRECTORY_SEPARATOR
        . 'migration_errada.sql';

    try {
        writeMigrationTestFile(
            $temporaryPath,
            'migration_errada.sql'
        );

        assertMigrationException(
            static fn (): array =>
                (
                    new MigrationDiscovery(
                        $temporaryPath
                    )
                )->discover(),
            'Nome de migration inválido'
        );
    } finally {
        if (is_file($file)) {
            unlink(
                $file
            );
        }
    }
};

$tests[
    'rejeita número de migration duplicado'
] = static function () use (
    $temporaryPath
): void {
    $files = [
        '020_first_change.sql',
        '020_second_change.sql',
    ];

    try {
        foreach ($files as $filename) {
            writeMigrationTestFile(
                $temporaryPath,
                $filename
            );
        }

        assertMigrationException(
            static fn (): array =>
                (
                    new MigrationDiscovery(
                        $temporaryPath
                    )
                )->discover(),
            'Número de migration duplicado'
        );
    } finally {
        foreach ($files as $filename) {
            $file =
                $temporaryPath
                . DIRECTORY_SEPARATOR
                . $filename;

            if (is_file($file)) {
                unlink(
                    $file
                );
            }
        }
    }
};

$tests[
    'lê conteúdo exato e checksum dos mesmos bytes'
] = static function (): void {
    $directory =
        createMigrationTestDirectory(
            'agenda-migration-read-tests-'
        );

    $file =
        $directory
        . DIRECTORY_SEPARATOR
        . '001_read_test.sql';

    $contents =
        "CREATE TABLE read_test (\n"
        . "    id INT NOT NULL\n"
        . ");\n";

    try {
        $written = file_put_contents(
            $file,
            $contents
        );

        if ($written === false) {
            throw new RuntimeException(
                'Não foi possível criar migration para teste de leitura.'
            );
        }

        $migration =
            new MigrationFile(
                $file
            );

        $read =
            $migration->read();

        assertMigrationSame(
            $contents,
            $read['sql'],
            'A leitura deve preservar exatamente os bytes da migration.'
        );

        assertMigrationSame(
            hash(
                'sha256',
                $contents
            ),
            $read['checksum'],
            'O checksum deve corresponder exatamente ao SQL retornado.'
        );

        assertMigrationSame(
            hash(
                'sha256',
                $read['sql']
            ),
            $read['checksum'],
            'SQL e checksum retornados devem pertencer à mesma leitura.'
        );
    } finally {
        removeMigrationTestDirectory(
            $directory
        );
    }
};

$tests[
    'rejeita leitura de arquivo inexistente'
] = static function (): void {
    $directory =
        createMigrationTestDirectory(
            'agenda-migration-read-missing-tests-'
        );

    try {
        $migration =
            new MigrationFile(
                $directory
                . DIRECTORY_SEPARATOR
                . '001_missing_read.sql'
            );

        assertMigrationException(
            static fn (): array =>
                $migration->read(),
            'Arquivo de migration não pode ser lido'
        );
    } finally {
        removeMigrationTestDirectory(
            $directory
        );
    }
};

$tests[
    'calcula checksum SHA-256 do conteúdo exato'
] = static function (): void {
    $directory =
        createMigrationTestDirectory(
            'agenda-migration-checksum-tests-'
        );

    $file =
        $directory
        . DIRECTORY_SEPARATOR
        . '001_checksum_test.sql';

    $contents =
        "CREATE TABLE example (\n"
        . "    id INT NOT NULL\n"
        . ");\n";

    try {
        $written = file_put_contents(
            $file,
            $contents
        );

        if ($written === false) {
            throw new RuntimeException(
                'Não foi possível criar migration para teste de checksum.'
            );
        }

        $migration =
            new MigrationFile(
                $file
            );

        $checksum =
            $migration->checksum();

        assertMigrationSame(
            hash(
                'sha256',
                $contents
            ),
            $checksum,
            'O checksum SHA-256 não corresponde ao conteúdo exato.'
        );

        assertMigrationSame(
            64,
            strlen(
                $checksum
            ),
            'O checksum SHA-256 deve possuir 64 caracteres.'
        );

        assertMigrationSame(
            $checksum,
            $migration->checksum(),
            'O checksum deve permanecer estável sem alteração do arquivo.'
        );
    } finally {
        removeMigrationTestDirectory(
            $directory
        );
    }
};

$tests[
    'checksum muda quando conteúdo da migration muda'
] = static function (): void {
    $directory =
        createMigrationTestDirectory(
            'agenda-migration-checksum-change-tests-'
        );

    $file =
        $directory
        . DIRECTORY_SEPARATOR
        . '001_checksum_change.sql';

    try {
        $written = file_put_contents(
            $file,
            "SELECT 1;\n"
        );

        if ($written === false) {
            throw new RuntimeException(
                'Não foi possível criar migration para teste de checksum.'
            );
        }

        $migration =
            new MigrationFile(
                $file
            );

        $originalChecksum =
            $migration->checksum();

        $written = file_put_contents(
            $file,
            "SELECT 1;\n\n"
        );

        if ($written === false) {
            throw new RuntimeException(
                'Não foi possível alterar migration para teste de checksum.'
            );
        }

        assertMigrationTrue(
            $originalChecksum
                !== $migration->checksum(),
            'O checksum deveria mudar após alteração do conteúdo exato.'
        );
    } finally {
        removeMigrationTestDirectory(
            $directory
        );
    }
};

$tests[
    'rejeita checksum de arquivo inexistente'
] = static function (): void {
    $directory =
        createMigrationTestDirectory(
            'agenda-migration-missing-tests-'
        );

    try {
        $migration =
            new MigrationFile(
                $directory
                . DIRECTORY_SEPARATOR
                . '001_missing.sql'
            );

        assertMigrationException(
            static fn (): string =>
                $migration->checksum(),
            'Arquivo de migration não pode ser lido'
        );
    } finally {
        removeMigrationTestDirectory(
            $directory
        );
    }
};

$tests[
    'rejeita diretório inexistente'
] = static function (): void {
    assertMigrationException(
        static fn (): array =>
            (
                new MigrationDiscovery(
                    '/diretorio/que/nao/existe'
                )
            )->discover(),
        'Diretório de migrations não encontrado'
    );
};

$passed = 0;
$total = count(
    $tests
);

try {
    foreach (
        $tests as $name => $test
    ) {
        if (
            runMigrationTest(
                $name,
                $test
            )
        ) {
            $passed++;
        }
    }
} finally {
    removeMigrationTestDirectory(
        $temporaryPath
    );
}

fwrite(
    STDOUT,
    sprintf(
        "\nResultado migrations: %d/%d testes aprovados.\n",
        $passed,
        $total
    )
);

exit(
    $passed === $total
        ? 0
        : 1
);
