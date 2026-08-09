<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Persistence;

use AgendaInteligente\Application\Auth\RateLimitRepository;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class MySqlRateLimitRepository
    implements RateLimitRepository
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    public function blockedUntil(
        string $scope,
        string $keyHash,
        int $now
    ): ?int {
        $statement =
            $this->pdo->prepare(
                "
                SELECT
                    blocked_until
                FROM auth_rate_limits
                WHERE scope = :scope
                  AND key_hash = :key_hash
                  AND blocked_until > :now
                LIMIT 1
                "
            );

        $statement->execute([
            ':scope' => $scope,
            ':key_hash' => $keyHash,
            ':now' => self::formatTimestamp(
                $now
            ),
        ]);

        $blockedUntil =
            $statement->fetchColumn();

        if ($blockedUntil === false) {
            return null;
        }

        return self::parseTimestamp(
            (string) $blockedUntil
        );
    }

    public function recordAttempt(
        string $scope,
        string $keyHash,
        int $maxAttempts,
        int $windowSeconds,
        int $blockSeconds,
        int $now
    ): ?int {
        self::validatePolicy(
            $maxAttempts,
            $windowSeconds,
            $blockSeconds
        );

        if ($this->pdo->inTransaction()) {
            throw new RuntimeException(
                'Rate limiting não pode ser atualizado dentro de uma transação externa.'
            );
        }

        $this->pdo->beginTransaction();

        try {
            $nowSql =
                self::formatTimestamp(
                    $now
                );

            $this->ensureBucketExists(
                $scope,
                $keyHash,
                $nowSql
            );

            $state =
                $this->lockBucket(
                    $scope,
                    $keyHash
                );

            $blockedUntil =
                isset($state['blocked_until'])
                && is_string(
                    $state['blocked_until']
                )
                    ? self::parseTimestamp(
                        $state['blocked_until']
                    )
                    : null;

            if (
                $blockedUntil !== null
                && $blockedUntil > $now
            ) {
                $this->pdo->commit();

                return $blockedUntil;
            }

            $windowStartedAt =
                self::parseTimestamp(
                    (string) $state[
                        'window_started_at'
                    ]
                );

            $restartWindow =
                $blockedUntil !== null
                || (
                    $windowStartedAt
                    + $windowSeconds
                    <= $now
                );

            $attempts =
                $restartWindow
                    ? 1
                    : (
                        (int) $state['attempts']
                        + 1
                    );

            $newWindowStartedAt =
                $restartWindow
                    ? $now
                    : $windowStartedAt;

            $newBlockedUntil =
                $attempts >= $maxAttempts
                    ? $now + $blockSeconds
                    : null;

            $this->updateBucket(
                $scope,
                $keyHash,
                $attempts,
                $newWindowStartedAt,
                $newBlockedUntil,
                $now
            );

            $this->pdo->commit();

            /*
             * A tentativa que atinge o limite ainda é permitida.
             * O blocked_until gravado acima impedirá as seguintes.
             */
            return null;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function clear(
        string $scope,
        string $keyHash
    ): void {
        $statement =
            $this->pdo->prepare(
                "
                DELETE FROM auth_rate_limits
                WHERE scope = :scope
                  AND key_hash = :key_hash
                "
            );

        $statement->execute([
            ':scope' => $scope,
            ':key_hash' => $keyHash,
        ]);
    }

    private function ensureBucketExists(
        string $scope,
        string $keyHash,
        string $now
    ): void {
        $statement =
            $this->pdo->prepare(
                "
                INSERT INTO auth_rate_limits (
                    scope,
                    key_hash,
                    attempts,
                    window_started_at,
                    blocked_until,
                    updated_at
                )
                VALUES (
                    :scope,
                    :key_hash,
                    0,
                    :window_started_at,
                    NULL,
                    :updated_at
                )
                ON DUPLICATE KEY UPDATE
                    updated_at = updated_at
                "
            );

        $statement->execute([
            ':scope' => $scope,
            ':key_hash' => $keyHash,
            ':window_started_at' => $now,
            ':updated_at' => $now,
        ]);
    }

    /**
     * @return array{
     *     attempts:int,
     *     window_started_at:string,
     *     blocked_until:?string
     * }
     */
    private function lockBucket(
        string $scope,
        string $keyHash
    ): array {
        $statement =
            $this->pdo->prepare(
                "
                SELECT
                    attempts,
                    window_started_at,
                    blocked_until
                FROM auth_rate_limits
                WHERE scope = :scope
                  AND key_hash = :key_hash
                LIMIT 1
                FOR UPDATE
                "
            );

        $statement->execute([
            ':scope' => $scope,
            ':key_hash' => $keyHash,
        ]);

        $state =
            $statement->fetch();

        if (!is_array($state)) {
            throw new RuntimeException(
                'Bucket de rate limiting não foi encontrado após sua criação.'
            );
        }

        return $state;
    }

    private function updateBucket(
        string $scope,
        string $keyHash,
        int $attempts,
        int $windowStartedAt,
        ?int $blockedUntil,
        int $updatedAt
    ): void {
        $statement =
            $this->pdo->prepare(
                "
                UPDATE auth_rate_limits
                SET
                    attempts = :attempts,
                    window_started_at = :window_started_at,
                    blocked_until = :blocked_until,
                    updated_at = :updated_at
                WHERE scope = :scope
                  AND key_hash = :key_hash
                "
            );

        $statement->execute([
            ':attempts' => $attempts,
            ':window_started_at' =>
                self::formatTimestamp(
                    $windowStartedAt
                ),
            ':blocked_until' =>
                $blockedUntil === null
                    ? null
                    : self::formatTimestamp(
                        $blockedUntil
                    ),
            ':updated_at' =>
                self::formatTimestamp(
                    $updatedAt
                ),
            ':scope' => $scope,
            ':key_hash' => $keyHash,
        ]);
    }

    private static function validatePolicy(
        int $maxAttempts,
        int $windowSeconds,
        int $blockSeconds
    ): void {
        if (
            $maxAttempts <= 0
            || $windowSeconds <= 0
            || $blockSeconds <= 0
        ) {
            throw new InvalidArgumentException(
                'Parâmetros de rate limiting devem ser maiores que zero.'
            );
        }
    }

    private static function formatTimestamp(
        int $timestamp
    ): string {
        return gmdate(
            'Y-m-d H:i:s',
            $timestamp
        );
    }

    private static function parseTimestamp(
        string $value
    ): int {
        $timestamp =
            strtotime(
                $value . ' UTC'
            );

        if ($timestamp === false) {
            throw new RuntimeException(
                'Timestamp inválido no estado de rate limiting.'
            );
        }

        return $timestamp;
    }
}
