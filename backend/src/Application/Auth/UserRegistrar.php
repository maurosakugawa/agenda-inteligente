<?php

declare(strict_types=1);

namespace AgendaInteligente\Application\Auth;

use AgendaInteligente\Infrastructure\Persistence\UserRepository;
use AgendaInteligente\Infrastructure\Security\PasswordHasher;

final class UserRegistrar
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwords,
        private CredentialValidator $credentials
    ) {
    }

    /**
     * @return array{
     *     id:int,
     *     username:string
     * }
     */
    public function register(
        string $username,
        string $password
    ): array {
        $this->credentials
            ->validateForRegistration(
                $username,
                $password
            );

        $passwordHash =
            $this->passwords->hash(
                $password
            );

        $id =
            $this->users->create(
                $username,
                $passwordHash
            );

        return [
            'id' => $id,
            'username' => $username,
        ];
    }
}
