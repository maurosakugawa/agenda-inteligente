<?php

declare(strict_types=1);

namespace AgendaInteligente\Application\Auth;

use Closure;

final class LoginRateLimiter
{
    private const IP_SCOPE =
        'login_ip';

    private const USERNAME_IP_SCOPE =
        'login_username_ip';

    private Closure $clock;

    /**
     * @param array{
     *     key_secret:string,
     *     ip:array{
     *         max_attempts:int,
     *         window_seconds:int,
     *         block_seconds:int
     *     },
     *     username_ip:array{
     *         max_failures:int,
     *         window_seconds:int,
     *         block_seconds:int
     *     }
     * } $config
     *
     * @param null|Closure(): int $clock
     */
    public function __construct(
        private RateLimitRepository $repository,
        private array $config,
        private UsernameCanonicalizer $usernameCanonicalizer,
        ?Closure $clock = null
    ) {
        $this->clock =
            $clock
            ?? static fn (): int => time();
    }

    /**
     * Retorna o bloqueio ativo mais longo entre:
     *
     * - origem IP;
     * - username + IP.
     *
     * Retorna null quando nenhum dos dois buckets
     * está bloqueado.
     */
    public function blockedUntil(
        string $ip,
        string $username
    ): ?int {
        $now =
            ($this->clock)();

        $ipBlockedUntil =
            $this->repository
                ->blockedUntil(
                    self::IP_SCOPE,
                    $this->ipKeyHash(
                        $ip
                    ),
                    $now
                );

        $usernameIpBlockedUntil =
            $this->repository
                ->blockedUntil(
                    self::USERNAME_IP_SCOPE,
                    $this->usernameIpKeyHash(
                        $ip,
                        $username
                    ),
                    $now
                );

        if ($ipBlockedUntil === null) {
            return $usernameIpBlockedUntil;
        }

        if (
            $usernameIpBlockedUntil === null
        ) {
            return $ipBlockedUntil;
        }

        return max(
            $ipBlockedUntil,
            $usernameIpBlockedUntil
        );
    }

    /**
     * Registra uma tentativa de login estruturalmente válida
     * no bucket geral da origem IP.
     *
     * Retorna blocked_until quando a tentativa atual
     * já deve ser impedida.
     */
    public function recordIpAttempt(
        string $ip
    ): ?int {
        $policy =
            $this->config['ip'];

        return $this->repository
            ->recordAttempt(
                self::IP_SCOPE,
                $this->ipKeyHash(
                    $ip
                ),
                $policy['max_attempts'],
                $policy['window_seconds'],
                $policy['block_seconds'],
                ($this->clock)()
            );
    }

    /**
     * Registra uma falha de credenciais para
     * a combinação username + IP.
     *
     * Deve ser chamado somente quando a verificação
     * das credenciais falhar.
     */
    public function recordCredentialFailure(
        string $ip,
        string $username
    ): ?int {
        $policy =
            $this->config[
                'username_ip'
            ];

        return $this->repository
            ->recordAttempt(
                self::USERNAME_IP_SCOPE,
                $this->usernameIpKeyHash(
                    $ip,
                    $username
                ),
                $policy['max_failures'],
                $policy['window_seconds'],
                $policy['block_seconds'],
                ($this->clock)()
            );
    }

    /**
     * Um login bem-sucedido remove somente as falhas
     * da combinação username + IP.
     *
     * O bucket geral do IP permanece intacto.
     */
    public function clearCredentialFailures(
        string $ip,
        string $username
    ): void {
        $this->repository
            ->clear(
                self::USERNAME_IP_SCOPE,
                $this->usernameIpKeyHash(
                    $ip,
                    $username
                )
            );
    }

    private function ipKeyHash(
        string $ip
    ): string {
        return $this->keyHash(
            "ip\0{$ip}"
        );
    }

    private function usernameIpKeyHash(
        string $ip,
        string $username
    ): string {
        $canonicalUsername =
            $this->usernameCanonicalizer
                ->canonicalize(
                    $username
                );

        return $this->keyHash(
            "username_ip\0"
            . $canonicalUsername
            . "\0"
            . $ip
        );
    }

    private function keyHash(
        string $material
    ): string {
        return hash_hmac(
            'sha256',
            $material,
            $this->config[
                'key_secret'
            ]
        );
    }
}
