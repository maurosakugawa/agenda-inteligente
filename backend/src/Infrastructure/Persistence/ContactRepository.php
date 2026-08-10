<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Persistence;

use PDO;

final class ContactRepository
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    /**
     * @return list<array{
     *     id:int,
     *     user_id:int,
     *     name:string,
     *     phone:?string,
     *     email:?string,
     *     cep:?string,
     *     logradouro:?string,
     *     numero:?string,
     *     bairro:?string,
     *     cidade:?string,
     *     uf:?string,
     *     created_at:string,
     *     updated_at:string
     * }>
     */
    public function findAllByUserId(
        int $userId
    ): array {
        $statement = $this->pdo->prepare(
            "
            SELECT
                id,
                user_id,
                name,
                phone,
                email,
                cep,
                logradouro,
                numero,
                bairro,
                cidade,
                uf,
                created_at,
                updated_at
            FROM contacts
            WHERE user_id = :user_id
            ORDER BY created_at DESC
            "
        );

        $statement->execute([
            ':user_id' => $userId,
        ]);

        $contacts = $statement->fetchAll();

        return is_array($contacts)
            ? $contacts
            : [];
    }
}
