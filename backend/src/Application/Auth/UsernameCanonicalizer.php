<?php

declare(strict_types=1);

namespace AgendaInteligente\Application\Auth;

/**
 * Produz uma representação estável para usernames
 * considerados equivalentes pela política de
 * identidade adotada pela persistência.
 *
 * A representação é usada somente para derivação
 * de chaves internas, como buckets de rate limiting.
 */
interface UsernameCanonicalizer
{
    public function canonicalize(
        string $username
    ): string;
}
