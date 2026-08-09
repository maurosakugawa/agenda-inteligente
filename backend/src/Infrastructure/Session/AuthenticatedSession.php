<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Session;

use AgendaInteligente\Application\Auth\AuthenticationSession;
use AgendaInteligente\Infrastructure\Security\CsrfTokenManager;
use Closure;

final class AuthenticatedSession
    implements AuthenticationSession
{
    private Closure $clock;

    public function __construct(
        private SessionManager $session,
        private CsrfTokenManager $csrf,
        ?Closure $clock = null
    ) {
        $this->clock =
            $clock
            ?? static fn (): int => time();
    }

    public function establish(
        array $identity
    ): string {
        $this->session->regenerateId();

        $this->session->set(
            'auth',
            [
                'user_id' =>
                    $identity['id'],
                'username' =>
                    $identity['username'],
                'authenticated_at' =>
                    ($this->clock)(),
            ]
        );

        $this->session->renewLifetime();

        return $this->csrf->rotate();
    }

    public function terminate(): void
    {
        $this->session->destroy();
    }
}
