<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Database\Migration;

use PDO;

final class LegacyBaselineUpgrader
{
    /**
     * Estrutura exata das tabelas de domínio existentes
     * na baseline histórica anterior aos ADRs atuais.
     *
     * @var array<string, list<string>>
     */
    private const LEGACY_COLUMNS = [
        'users' => [
            'id',
            'name',
            'username',
            'email',
            'password_hash',
            'status',
            'failed_login_attempts',
            'locked_until',
            'last_login_at',
            'created_at',
            'updated_at',
        ],
        'contacts' => [
            'id',
            'user_id',
            'name',
            'email',
            'phone',
            'mobile_phone',
            'company',
            'postal_code',
            'street',
            'number',
            'complement',
            'district',
            'city',
            'state',
            'birthday',
            'notes',
            'created_at',
            'updated_at',
            'deleted_at',
        ],
        'events' => [
            'id',
            'user_id',
            'title',
            'description',
            'location',
            'category',
            'priority',
            'starts_at',
            'ends_at',
            'all_day',
            'status',
            'reminder_minutes',
            'created_at',
            'updated_at',
            'deleted_at',
        ],
        'event_participants' => [
            'event_id',
            'contact_id',
            'response_status',
            'created_at',
        ],
        'weather_cache' => [
            'cache_key',
            'response_json',
            'expires_at',
            'created_at',
            'updated_at',
        ],
    ];

    /**
     * Estrutura de domínio esperada após executar
     * o 001_initial_schema atual.
     *
     * @var array<string, list<string>>
     */
    private const CURRENT_COLUMNS = [
        'users' => [
            'id',
            'username',
            'password_hash',
            'active',
            'created_at',
            'updated_at',
            'deleted_at',
        ],
        'contacts' => [
            'id',
            'user_id',
            'name',
            'phone',
            'email',
            'cep',
            'logradouro',
            'numero',
            'bairro',
            'cidade',
            'uf',
            'created_at',
            'updated_at',
        ],
        'events' => [
            'id',
            'user_id',
            'title',
            'description',
            'event_date',
            'event_time',
            'category',
            'priority',
            'location',
            'reminder_minutes',
            'created_at',
            'updated_at',
        ],
        'event_contacts' => [
            'event_id',
            'contact_id',
            'created_at',
        ],
        'weather_cache' => [
            'cache_key',
            'cache_type',
            'provider',
            'schema_version',
            'city_key',
            'city_query',
            'payload_json',
            'fetched_at',
            'expires_at',
            'created_at',
            'updated_at',
        ],
    ];

    public function __construct(
        private PDO $pdo
    ) {
    }

    /**
     * Atualiza somente uma instalação realmente criada
     * pela baseline histórica conhecida.
     *
     * Instalações legadas com dados não são modificadas
     * automaticamente, porque a conversão seria destrutiva
     * e não existe política segura para todos os campos.
     */
    public function upgradeIfNeeded(
        ?MigrationFile $baseline
    ): void {
        if (
            $baseline === null
            || $baseline->version()
                !== '001_initial_schema'
        ) {
            throw new MigrationException(
                'Schema legado detectado, mas 001_initial_schema não está disponível.'
            );
        }

        $this->assertKnownLegacyDomainSchema();

        if ($this->legacyDomainHasData()) {
            throw new MigrationException(
                'Schema legado contém dados de domínio e exige migração manual antes do upgrade automático.'
            );
        }

        /*
        * A partir daqui sabemos:
         *
         * - que o schema corresponde exatamente à baseline conhecida;
         * - que nenhuma tabela de domínio possui registros.
         *
         * Portanto é seguro reconstruir as tabelas pelo 001 atual.
         */
        $this->dropLegacyDomainSchema();

        $read =
            $baseline->read();

        $result =
            $this->pdo->exec(
                $read['sql']
            );

        if ($result === false) {
            throw new MigrationException(
                'Não foi possível reconstruir o schema atual a partir da baseline legada.'
            );
        }

        $this->assertCurrentDomainSchema();
    }

    private function assertKnownLegacyDomainSchema(): void
    {
        foreach (
            self::LEGACY_COLUMNS
            as $tableName => $expectedColumns
        ) {
            if (
                !$this->tableExists(
                    $tableName
                )
            ) {
                throw new MigrationException(
                    sprintf(
                        'Schema legado incompatível: tabela %s não foi encontrada.',
                        $tableName
                    )
                );
            }

            $actualColumns =
                $this->tableColumns(
                    $tableName
                );

            if (
                $actualColumns
                !== $expectedColumns
            ) {
                throw new MigrationException(
                    sprintf(
                        'Schema legado incompatível: tabela %s possui definição inesperada.',
                        $tableName
                    )
                );
            }
        }

        if (
            $this->tableExists(
                'event_contacts'
            )
        ) {
            throw new MigrationException(
                'Schema legado incompatível: event_contacts já existe.'
            );
        }
    }

    private function legacyDomainHasData(): bool
    {
        foreach (
            array_keys(
                self::LEGACY_COLUMNS
            ) as $tableName
        ) {
            $statement =
                $this->pdo->query(
                    sprintf(
                        'SELECT 1 FROM `%s` LIMIT 1',
                        $tableName
                    )
                );

            if (
                $statement->fetchColumn()
                !== false
            ) {
                return true;
            }
        }

        return false;
    }

    private function dropLegacyDomainSchema(): void
    {
        /*
         * Ordem respeita as dependências de chave estrangeira.
         *
         * Não usamos FOREIGN_KEY_CHECKS=0 porque o schema
         * conhecido permite uma remoção ordenada e explícita.
         */
        $tables = [
            'event_participants',
            'events',
            'contacts',
            'weather_cache',
            'users',
        ];

        foreach ($tables as $tableName) {
            $this->pdo->exec(
                sprintf(
                    'DROP TABLE `%s`',
                    $tableName
                )
            );
        }
    }

    private function assertCurrentDomainSchema(): void
    {
        foreach (
            self::CURRENT_COLUMNS
            as $tableName => $expectedColumns
        ) {
            if (
                !$this->tableExists(
                    $tableName
                )
            ) {
                throw new MigrationException(
                    sprintf(
                        'Upgrade legado incompleto: tabela atual %s não foi criada.',
                        $tableName
                    )
                );
            }

            $actualColumns =
                $this->tableColumns(
                    $tableName
                );

            if (
                $actualColumns
                !== $expectedColumns
            ) {
                throw new MigrationException(
                    sprintf(
                        'Upgrade legado incompleto: tabela %s possui definição inesperada.',
                        $tableName
                    )
                );
            }
        }

        if (
            $this->tableExists(
                'event_participants'
            )
        ) {
            throw new MigrationException(
                'Upgrade legado incompleto: event_participants ainda existe.'
            );
        }
    }

    private function tableExists(
        string $tableName
    ): bool {
        $statement =
            $this->pdo->prepare(
                "
                SELECT COUNT(*)
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
                "
            );

        $statement->execute([
            ':table_name' =>
                $tableName,
        ]);

        return
            (int) $statement->fetchColumn()
            === 1;
    }

    /**
     * @return list<string>
     */
    private function tableColumns(
        string $tableName
    ): array {
        $statement =
            $this->pdo->prepare(
                "
                SELECT COLUMN_NAME
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
                ORDER BY ORDINAL_POSITION ASC
                "
            );

        $statement->execute([
            ':table_name' =>
                $tableName,
        ]);

        $rows =
            $statement->fetchAll();

        $columns = [];

        foreach ($rows as $row) {
            $columnName =
                $row['COLUMN_NAME']
                ?? null;

            if (!is_string($columnName)) {
                throw new MigrationException(
                    sprintf(
                        'Não foi possível inspecionar colunas da tabela %s.',
                        $tableName
                    )
                );
            }

            $columns[] =
                $columnName;
        }

        return $columns;
    }
}
