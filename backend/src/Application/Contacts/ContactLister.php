<?php

declare(strict_types=1);

namespace AgendaInteligente\Application\Contacts;

use AgendaInteligente\Infrastructure\Persistence\ContactRepository;

final class ContactLister
{
    public function __construct(
        private ContactRepository $contacts
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
    public function list(
        int $userId
    ): array {
        return $this->contacts->findAllByUserId(
            $userId
        );
    }
}
