<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Security;

final class PasswordHasher
{
    private const COST = 10;

    public function hash(
        string $password
    ): string {
        return password_hash(
            $password,
            PASSWORD_BCRYPT,
            [
                'cost' => self::COST,
            ]
        );
    }

    public function verify(
        string $password,
        string $hash
    ): bool {
        return password_verify(
            $password,
            $hash
        );
    }
}
