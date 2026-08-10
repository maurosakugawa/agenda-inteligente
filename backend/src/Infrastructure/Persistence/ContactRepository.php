<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Persistence;

use PDO;
use RuntimeException;

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

    /**
     * Busca um contato pertencente ao usuário informado.
     *
     * @return array{
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
     * }|null
     */
    public function findByIdAndUserId(
        int $id,
        int $userId
    ): ?array {
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
            WHERE id = :id
              AND user_id = :user_id
            LIMIT 1
            "
        );

        $statement->execute([
            ':id' => $id,
            ':user_id' => $userId,
        ]);

        $contact = $statement->fetch();

        return is_array($contact)
            ? $contact
            : null;
    }

    /**
     * Atualiza um contato pertencente ao usuário informado.
     *
     * Retorna false quando o contato não existe
     * ou pertence a outro usuário.
     */
    public function update(
        int $id,
        int $userId,
        string $name,
        ?string $phone = null,
        ?string $email = null,
        ?string $cep = null,
        ?string $logradouro = null,
        ?string $numero = null,
        ?string $bairro = null,
        ?string $cidade = null,
        ?string $uf = null
    ): bool {
        $statement = $this->pdo->prepare(
            "
            UPDATE contacts
            SET
                name = :name,
                phone = :phone,
                email = :email,
                cep = :cep,
                logradouro = :logradouro,
                numero = :numero,
                bairro = :bairro,
                cidade = :cidade,
                uf = :uf
            WHERE id = :id
              AND user_id = :user_id
            "
        );

        $statement->execute([
            ':id' => $id,
            ':user_id' => $userId,
            ':name' => $name,
            ':phone' => $phone,
            ':email' => $email,
            ':cep' => $cep,
            ':logradouro' => $logradouro,
            ':numero' => $numero,
            ':bairro' => $bairro,
            ':cidade' => $cidade,
            ':uf' => $uf,
        ]);

        if ($statement->rowCount() > 0) {
            return true;
        }

        return $this->findByIdAndUserId(
            $id,
            $userId
        ) !== null;
    }

    /**
     * Cria um contato pertencente ao usuário informado
     * e retorna seu identificador.
     */
    public function create(
        int $userId,
        string $name,
        ?string $phone = null,
        ?string $email = null,
        ?string $cep = null,
        ?string $logradouro = null,
        ?string $numero = null,
        ?string $bairro = null,
        ?string $cidade = null,
        ?string $uf = null
    ): int {
        $statement = $this->pdo->prepare(
            "
            INSERT INTO contacts (
                user_id,
                name,
                phone,
                email,
                cep,
                logradouro,
                numero,
                bairro,
                cidade,
                uf
            )
            VALUES (
                :user_id,
                :name,
                :phone,
                :email,
                :cep,
                :logradouro,
                :numero,
                :bairro,
                :cidade,
                :uf
            )
            "
        );

        $statement->execute([
            ':user_id' => $userId,
            ':name' => $name,
            ':phone' => $phone,
            ':email' => $email,
            ':cep' => $cep,
            ':logradouro' => $logradouro,
            ':numero' => $numero,
            ':bairro' => $bairro,
            ':cidade' => $cidade,
            ':uf' => $uf,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        if ($id <= 0) {
            throw new RuntimeException(
                'Não foi possível obter o identificador do contato criado.'
            );
        }

        return $id;
    }

}
