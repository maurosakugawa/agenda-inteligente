<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Security;

use AgendaInteligente\Infrastructure\Session\SessionManager;
use Closure;
use RuntimeException;

final class CsrfTokenManager
{
    private const TOKEN_BYTES = 32;
    private const TOKEN_HEX_LENGTH =
        self::TOKEN_BYTES * 2;

    private Closure $randomBytes;

    /**
     * @param null|Closure(int): string $randomBytes
     */
    public function __construct(
        private SessionManager $session,
        ?Closure $randomBytes = null
    ) {
        $this->randomBytes =
            $randomBytes
            ?? static fn (int $length): string =>
                random_bytes($length);
    }

    public function token(): string
    {
        $token = $this->currentToken();

        if ($token !== null) {
            return $token;
        }

        return $this->rotate();
    }

    public function rotate(): string
    {
        $security = $this->securityState();

        $randomBytes =
            ($this->randomBytes)(
                self::TOKEN_BYTES
            );

        if (
            strlen($randomBytes)
            !== self::TOKEN_BYTES
        ) {
            throw new RuntimeException(
                'A fonte aleatória não retornou a quantidade esperada de bytes.'
            );
        }

        $token = bin2hex(
            $randomBytes
        );

        $security['csrf_token'] =
            $token;

        $this->session->set(
            'security',
            $security
        );

        return $token;
    }

    public function currentToken(): ?string
    {
        $security =
            $this->securityState();

        $token =
            $security['csrf_token']
            ?? null;

        if (
            !is_string($token)
            || !$this->isValidTokenFormat(
                $token
            )
        ) {
            return null;
        }

        return $token;
    }

    public function validate(
        ?string $providedToken
    ): bool {
        if (
            $providedToken === null
            || $providedToken === ''
        ) {
            return false;
        }

        $sessionToken =
            $this->currentToken();

        if ($sessionToken === null) {
            return false;
        }

        return hash_equals(
            $sessionToken,
            $providedToken
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function securityState(): array
    {
        $security =
            $this->session->get(
                'security'
            );

        if (!is_array($security)) {
            throw new RuntimeException(
                'O estado de segurança da sessão não está disponível.'
            );
        }

        return $security;
    }

    private function isValidTokenFormat(
        string $token
    ): bool {
        if (
            strlen($token)
            !== self::TOKEN_HEX_LENGTH
        ) {
            return false;
        }

        return ctype_xdigit(
            $token
        );
    }
}
