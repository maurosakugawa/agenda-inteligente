<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Database\Migration;

final class MigrationDiscovery
{
    public function __construct(
        private string $migrationsPath
    ) {
    }

    /**
     * @return list<MigrationFile>
     */
    public function discover(): array
    {
        if (
            !is_dir(
                $this->migrationsPath
            )
        ) {
            throw new MigrationException(
                sprintf(
                    'Diretório de migrations não encontrado: %s.',
                    $this->migrationsPath
                )
            );
        }

        if (
            !is_readable(
                $this->migrationsPath
            )
        ) {
            throw new MigrationException(
                sprintf(
                    'Diretório de migrations não pode ser lido: %s.',
                    $this->migrationsPath
                )
            );
        }

        $entries = scandir(
            $this->migrationsPath
        );

        if ($entries === false) {
            throw new MigrationException(
                'Não foi possível listar o diretório de migrations.'
            );
        }

        $migrations = [];
        $sequences = [];

        foreach ($entries as $entry) {
            if (
                $entry === '.'
                || $entry === '..'
            ) {
                continue;
            }

            $path =
                rtrim(
                    $this->migrationsPath,
                    DIRECTORY_SEPARATOR
                )
                . DIRECTORY_SEPARATOR
                . $entry;

            if (!is_file($path)) {
                continue;
            }

            if (
                strtolower(
                    pathinfo(
                        $entry,
                        PATHINFO_EXTENSION
                    )
                ) !== 'sql'
            ) {
                continue;
            }

            $migration =
                new MigrationFile(
                    $path
                );

            $sequence =
                $migration->sequence();

            if (
                array_key_exists(
                    $sequence,
                    $sequences
                )
            ) {
                throw new MigrationException(
                    sprintf(
                        'Número de migration duplicado: %03d (%s e %s).',
                        $sequence,
                        $sequences[$sequence],
                        $migration->filename()
                    )
                );
            }

            $sequences[$sequence] =
                $migration->filename();

            $migrations[] =
                $migration;
        }

        usort(
            $migrations,
            static fn (
                MigrationFile $left,
                MigrationFile $right
            ): int => strcmp(
                $left->filename(),
                $right->filename()
            )
        );

        return $migrations;
    }
}
