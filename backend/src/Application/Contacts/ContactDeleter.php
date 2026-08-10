<?php

declare(strict_types=1);

namespace AgendaInteligente\Application\Contacts;

use AgendaInteligente\Infrastructure\Persistence\ContactRepository;

final class ContactDeleter
{
    public function __construct(
        private ContactRepository $contacts
    ) {
    }

    public function delete(
        int $id,
        int $userId
    ): void {
        $deleted =
            $this->contacts->delete(
                $id,
                $userId
            );

        if (!$deleted) {
            throw new ContactNotFoundException(
                'Contato não encontrado.'
            );
        }
    }
}
