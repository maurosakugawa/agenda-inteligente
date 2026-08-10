<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Database\Migration;

use PDO;
use Throwable;

final class MigrationRunner
{
    private MigrationDiscovery $discovery;

    private MigrationPlanner $planner;

    private SchemaMigrationStore $store;

    private MigrationLock $lock;

    public function __construct(
        private PDO $pdo,
        string $migrationsPath,
        int $lockTimeoutSeconds = 5
    ) {
        $this->discovery =
            new MigrationDiscovery(
                $migrationsPath
            );

        $this->planner =
            new MigrationPlanner();

        $this->store =
            new SchemaMigrationStore(
                $pdo
            );

        $this->lock =
            new MigrationLock(
                $pdo,
                $lockTimeoutSeconds
            );
    }

    public function run(): int
    {
        $this->lock->acquire();

        $appliedCount = 0;
        $failure = null;

        try {
            $appliedCount =
                $this->runLocked();
        } catch (Throwable $exception) {
            $failure = $exception;
        }

        try {
            $this->lock->release();
        } catch (Throwable $releaseException) {
            if ($failure !== null) {
                throw new MigrationException(
                    sprintf(
                        'Falha durante migrations e também ao liberar lock: %s',
                        $releaseException->getMessage()
                    ),
                    0,
                    $failure
                );
            }

            throw $releaseException;
        }

        if ($failure !== null) {
            throw $failure;
        }

        return $appliedCount;
    }

    private function runLocked(): int
    {
        $this->store->ensureTable();

        $migrations =
            $this->discovery->discover();

        $pending =
            $this->planner->pending(
                $migrations,
                $this->store->applied()
            );

        $appliedCount = 0;

        foreach ($pending as $migration) {
            try {
                $read =
                    $migration->read();

                $result =
                    $this->pdo->exec(
                        $read['sql']
                    );

                if ($result === false) {
                    throw new MigrationException(
                        sprintf(
                            'PDO não executou a migration %s.',
                            $migration->version()
                        )
                    );
                }

                $this->store->record(
                    $migration->version(),
                    $read['checksum']
                );

                $appliedCount++;
            } catch (Throwable $exception) {
                throw new MigrationException(
                    sprintf(
                        'Falha ao aplicar migration %s.',
                        $migration->version()
                    ),
                    0,
                    $exception
                );
            }
        }

        return $appliedCount;
    }
}
