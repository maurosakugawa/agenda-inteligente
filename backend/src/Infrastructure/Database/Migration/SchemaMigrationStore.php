<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Database\Migration;

use PDO;

final class SchemaMigrationStore
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    public function ensureTable(): void
    {
        $this->pdo->exec(
            "
            CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(190) NOT NULL,
                checksum CHAR(64)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,
                executed_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,

                PRIMARY KEY (version)
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
            "
        );

        $this->validateTable();
    }

    /**
     * @return array<string, string>
     */
    public function applied(): array
    {
        $statement = $this->pdo->query(
            "
            SELECT
                version,
                checksum
            FROM schema_migrations
            ORDER BY version ASC
            "
        );

        $rows = $statement->fetchAll();
        $applied = [];

        foreach ($rows as $row) {
            $version = $row['version'] ?? null;
            $checksum = $row['checksum'] ?? null;

            if (
                !is_string($version)
                || !is_string($checksum)
            ) {
                throw new MigrationException(
                    'Histórico de migrations contém dados inválidos.'
                );
            }

            $applied[$version] = $checksum;
        }

        return $applied;
    }

    public function record(
        string $version,
        string $checksum
    ): void {
        if (
            $version === ''
            || strlen($version) > 190
        ) {
            throw new MigrationException(
                'Versão de migration inválida para registro.'
            );
        }

        if (
            preg_match(
                '/^[a-f0-9]{64}$/D',
                $checksum
            ) !== 1
        ) {
            throw new MigrationException(
                'Checksum de migration inválido para registro.'
            );
        }

        $statement = $this->pdo->prepare(
            "
            INSERT INTO schema_migrations (
                version,
                checksum
            ) VALUES (
                :version,
                :checksum
            )
            "
        );

        $statement->execute([
            ':version' => $version,
            ':checksum' => $checksum,
        ]);
    }

    private function validateTable(): void
    {
        $statement = $this->pdo->query(
            "
            SELECT
                ENGINE,
                TABLE_COLLATION
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'schema_migrations'
            LIMIT 1
            "
        );

        $table = $statement->fetch();

        if (!is_array($table)) {
            throw new MigrationException(
                'Tabela schema_migrations não foi encontrada após inicialização.'
            );
        }

        $engine = strtolower(
            (string) ($table['ENGINE'] ?? '')
        );

        $collation = strtolower(
            (string) ($table['TABLE_COLLATION'] ?? '')
        );

        if (
            $engine !== 'innodb'
            || $collation !== 'utf8mb4_unicode_ci'
        ) {
            throw new MigrationException(
                'Tabela schema_migrations possui definição incompatível.'
            );
        }

        $statement = $this->pdo->query(
            "
            SELECT
                COLUMN_NAME,
                COLUMN_TYPE,
                IS_NULLABLE,
                COLUMN_DEFAULT,
                COLUMN_KEY,
                CHARACTER_SET_NAME,
                COLLATION_NAME
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'schema_migrations'
            ORDER BY ORDINAL_POSITION ASC
            "
        );

        $columns = $statement->fetchAll();

        if (count($columns) !== 3) {
            throw new MigrationException(
                'Tabela schema_migrations possui colunas incompatíveis.'
            );
        }

        $expected = [
            [
                'name' => 'version',
                'type' => 'varchar(190)',
                'nullable' => 'NO',
                'key' => 'PRI',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ],
            [
                'name' => 'checksum',
                'type' => 'char(64)',
                'nullable' => 'NO',
                'key' => '',
                'charset' => 'ascii',
                'collation' => 'ascii_bin',
            ],
            [
                'name' => 'executed_at',
                'type' => 'datetime',
                'nullable' => 'NO',
                'key' => '',
                'charset' => null,
                'collation' => null,
            ],
        ];

        foreach ($expected as $index => $expectedColumn) {
            $column = $columns[$index] ?? null;

            if (!is_array($column)) {
                throw new MigrationException(
                    'Tabela schema_migrations possui colunas incompatíveis.'
                );
            }

            $actualCharset =
                $column['CHARACTER_SET_NAME'] ?? null;

            $actualCollation =
                $column['COLLATION_NAME'] ?? null;

            if (
                (string) ($column['COLUMN_NAME'] ?? '')
                    !== $expectedColumn['name']
                || strtolower(
                    (string) ($column['COLUMN_TYPE'] ?? '')
                ) !== $expectedColumn['type']
                || (string) ($column['IS_NULLABLE'] ?? '')
                    !== $expectedColumn['nullable']
                || (string) ($column['COLUMN_KEY'] ?? '')
                    !== $expectedColumn['key']
                || $actualCharset
                    !== $expectedColumn['charset']
                || $actualCollation
                    !== $expectedColumn['collation']
            ) {
                throw new MigrationException(
                    sprintf(
                        'Coluna incompatível em schema_migrations: %s.',
                        $expectedColumn['name']
                    )
                );
            }
        }

        $default = strtolower(
            (string) (
                $columns[2]['COLUMN_DEFAULT']
                ?? ''
            )
        );

        $normalizedDefault = str_replace(
            ['(', ')'],
            '',
            $default
        );

        if ($normalizedDefault !== 'current_timestamp') {
            throw new MigrationException(
                'Coluna executed_at possui default incompatível.'
            );
        }
    }
}
