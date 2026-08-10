<?php

declare(strict_types=1);

namespace AgendaInteligente\Application\Contacts;

use AgendaInteligente\Infrastructure\Persistence\ContactRepository;
use RuntimeException;

final class ContactCreator
{
    public function __construct(
        private ContactRepository $contacts,
        private ContactValidator $validator
    ) {
    }

    /**
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
     * }
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
    ): array {
        $this->validator->validate(
            $name,
            $phone,
            $email,
            $cep,
            $logradouro,
            $numero,
            $bairro,
            $cidade,
            $uf
        );

        $id =
            $this->contacts->create(
                $userId,
                $name,
                $phone,
                $email,
                $cep,
                $logradouro,
                $numero,
                $bairro,
                $cidade,
                $uf
            );

        $contact =
            $this->contacts->findByIdAndUserId(
                $id,
                $userId
            );

        if ($contact === null) {
            throw new RuntimeException(
                'Contato criado não pôde ser recuperado.'
            );
        }

        return $contact;
    }
}
