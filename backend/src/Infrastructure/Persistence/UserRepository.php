<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Persistence;

use PDO;
use PDOException;
use RuntimeException;

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

    /**
     * Cria um usuário ativo e retorna seu identificador.
     *
     * @throws DuplicateUsernameException
     */
    public function create(
        string $username,
        string $passwordHash
    ): int {
        $statement = $this->pdo->prepare(
            "
            INSERT INTO users (
                username,
                password_hash,
                active,
                created_at,
                updated_at,
                deleted_at
            )
            VALUES (
                :username,
                :password_hash,
                1,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP(),
                NULL
            )
            "
        );

        try {
            $statement->execute([
                ':username' => $username,
                ':password_hash' => $passwordHash,
            ]);
        } catch (PDOException $exception) {
            if (
                $exception->getCode() === '23000'
                && isset($exception->errorInfo[1])
                && (int) $exception->errorInfo[1] === 1062
            ) {
                throw new DuplicateUsernameException(
                    'Username já cadastrado.',
                    0,
                    $exception
                );
            }

            throw $exception;
        }

        $id = (int) $this->pdo->lastInsertId();

        if ($id <= 0) {
            throw new RuntimeException(
                'Não foi possível obter o identificador do usuário criado.'
            );
        }

        return $id;
    }
}
