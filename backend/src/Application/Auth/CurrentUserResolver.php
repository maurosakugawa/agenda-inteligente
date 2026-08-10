<?php

declare(strict_types=1);

namespace AgendaInteligente\Application\Auth;

use AgendaInteligente\Infrastructure\Persistence\UserRepository;

final class CurrentUserResolver
    implements CurrentUserProvider
{
    public function __construct(
        private AuthenticationSession $session,
        private UserRepository $users
    ) {
    }

    /**
     * @return array{
     *     id:int,
     *     username:string
     * }|null
     */
    public function resolve(): ?array
    {
        $identity =
            $this->session->current();

        if ($identity === null) {
            return null;
        }

        $user =
            $this->users->findById(
                $identity['id']
            );

        if (
            $user === null
            || $user['active'] !== 1
        ) {
            $this->session->terminate();

            return null;
        }

        return [
            'id' =>
                $user['id'],
            'username' =>
                $user['username'],
        ];
    }
}
