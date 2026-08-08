<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Persistence;

use PDO;

final class UserRepository
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    /**
     * @return array{
     *     id:int,
     *     username:string,
     *     password_hash:string,
     *     active:int,
     *     created_at:string,
     *     updated_at:string,
     *     deleted_at:?string
     * }|null
     */
    public function findByUsername(
        string $username
    ): ?array {
        $statement = $this->pdo->prepare(
            "
            SELECT
                id,
                username,
                password_hash,
                active,
                created_at,
                updated_at,
                deleted_at
            FROM users
            WHERE username = :username
              AND deleted_at IS NULL
            LIMIT 1
            "
        );

        $statement->execute([
            ':username' => $username,
        ]);

        $user = $statement->fetch();

        return is_array($user)
            ? $user
            : null;
    }

    /**
     * @return array{
     *     id:int,
     *     username:string,
     *     password_hash:string,
     *     active:int,
     *     created_at:string,
     *     updated_at:string,
     *     deleted_at:?string
     * }|null
     */
    public function findById(
        int $id
    ): ?array {
        $statement = $this->pdo->prepare(
            "
            SELECT
                id,
                username,
                password_hash,
                active,
                created_at,
                updated_at,
                deleted_at
            FROM users
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
            "
        );

        $statement->execute([
            ':id' => $id,
        ]);

        $user = $statement->fetch();

        return is_array($user)
            ? $user
            : null;
    }
}
