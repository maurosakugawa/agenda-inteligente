<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Database\Migration;

use PDO;

final class MigrationLock
{
    private const LOCK_PREFIX =
        'agenda-inteligente:migrations:';

    private bool $acquired = false;

    private string $lockName;

    public function __construct(
        private PDO $pdo,
        private int $timeoutSeconds = 5
    ) {
        if ($timeoutSeconds < 0) {
            throw new MigrationException(
                'Timeout do lock de migrations não pode ser negativo.'
            );
        }

        $database =
            $this->pdo->query(
                'SELECT DATABASE()'
            )->fetchColumn();

        if (
            !is_string($database)
            || $database === ''
        ) {
            throw new MigrationException(
                'Não foi possível identificar o banco para o lock de migrations.'
            );
        }

        $this->lockName =
            self::LOCK_PREFIX
            . substr(
                hash(
                    'sha256',
                    $database
                ),
                0,
                32
            );
    }

    public function acquire(): void
    {
        if ($this->acquired) {
            throw new MigrationException(
                'Lock de migrations já foi adquirido por esta instância.'
            );
        }

        $statement =
            $this->pdo->prepare(
                "
                SELECT GET_LOCK(
                    :lock_name,
                    :timeout_seconds
                )
                "
            );

        $statement->bindValue(
            ':lock_name',
            $this->lockName,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':timeout_seconds',
            $this->timeoutSeconds,
            PDO::PARAM_INT
        );

        $statement->execute();

        $result =
            $statement->fetchColumn();

        if (
            $result === 1
            || $result === '1'
        ) {
            $this->acquired = true;

            return;
        }

        if (
            $result === 0
            || $result === '0'
        ) {
            throw new MigrationException(
                sprintf(
                    'Tempo limite excedido ao adquirir lock de migrations após %d segundo(s).',
                    $this->timeoutSeconds
                )
            );
        }

        throw new MigrationException(
            'Falha ao adquirir lock de migrations.'
        );
    }

    public function release(): void
    {
        if (!$this->acquired) {
            throw new MigrationException(
                'Lock de migrations não está adquirido por esta instância.'
            );
        }

        $statement =
            $this->pdo->prepare(
                "
                SELECT RELEASE_LOCK(
                    :lock_name
                )
                "
            );

        $statement->bindValue(
            ':lock_name',
            $this->lockName,
            PDO::PARAM_STR
        );

        $statement->execute();

        $result =
            $statement->fetchColumn();

        if (
            $result !== 1
            && $result !== '1'
        ) {
            throw new MigrationException(
                'Falha ao liberar lock de migrations.'
            );
        }

        $this->acquired = false;
    }
}
