<?php

declare(strict_types=1);

namespace AgendaInteligente\Application\Auth;

use AgendaInteligente\Infrastructure\Persistence\UserRepository;
use AgendaInteligente\Infrastructure\Security\PasswordHasher;

final class CredentialVerifier
{
    /**
     * Hash conhecido usado somente para executar uma verificação
     * de senha também quando o usuário não existe.
     *
     * Não é segredo e não representa uma credencial real.
     */
    private const DUMMY_PASSWORD_HASH =
        '$2y$10$Couj6hA0MXIG0Lij64Ii/.ZGdUmDFHaXfWmLCgt1YQUVbpI7h9LKO';

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
     * }|null
     */
    public function verify(
        string $username,
        string $password
    ): ?array {
        $this->credentials
            ->validateForLogin(
                $username,
                $password
            );

        $user =
            $this->users->findByUsername(
                $username
            );

        $passwordHash =
            $user['password_hash']
            ?? self::DUMMY_PASSWORD_HASH;

        $passwordMatches =
            $this->passwords->verify(
                $password,
                $passwordHash
            );

        if (
            $user === null
            || $user['active'] !== 1
            || !$passwordMatches
        ) {
            return null;
        }

        return [
            'id' => $user['id'],
            'username' => $user['username'],
        ];
    }
}
