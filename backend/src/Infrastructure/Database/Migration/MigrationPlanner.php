<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Database\Migration;

final class MigrationPlanner
{
    /**
     * @param list<MigrationFile> $migrations
     * @param array<string, string> $applied
     *
     * @return list<MigrationFile>
     */
    public function pending(
        array $migrations,
        array $applied
    ): array {
        $available = [];

        foreach ($migrations as $migration) {
            $available[
                $migration->version()
            ] = $migration;
        }

        foreach ($applied as $version => $checksum) {
            if (
                !array_key_exists(
                    $version,
                    $available
                )
            ) {
                throw new MigrationException(
                    sprintf(
                        'Migration aplicada ausente do conjunto local: %s.',
                        $version
                    )
                );
            }
        }

        $pending = [];
        $foundPending = false;

        foreach ($migrations as $migration) {
            $version =
                $migration->version();

            if (
                !array_key_exists(
                    $version,
                    $applied
                )
            ) {
                $foundPending = true;
                $pending[] = $migration;

                continue;
            }

            if ($foundPending) {
                throw new MigrationException(
                    sprintf(
                        'Histórico de migrations fora de ordem: %s está aplicada após uma migration pendente.',
                        $version
                    )
                );
            }

            $registeredChecksum =
                $applied[$version];

            $currentChecksum =
                $migration->checksum();

            if (
                !hash_equals(
                    $registeredChecksum,
                    $currentChecksum
                )
            ) {
                throw new MigrationException(
                    sprintf(
                        'Checksum divergente para migration aplicada: %s.',
                        $version
                    )
                );
            }
        }

        return $pending;
    }
}
