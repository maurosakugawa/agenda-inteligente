<?php

declare(strict_types=1);

use AgendaInteligente\Application\Auth\LoginRateLimiter;
use AgendaInteligente\Application\Auth\RateLimitRepository;

require_once dirname(__DIR__) . '/autoload.php';

final class FakeLoginRateLimitRepository
    implements RateLimitRepository
{
    /**
     * @var list<array{
     *     scope:string,
     *     key_hash:string,
     *     now:int
     * }>
     */
    public array $blockedUntilCalls = [];

    /**
     * @var list<array{
     *     scope:string,
     *     key_hash:string,
     *     max_attempts:int,
     *     window_seconds:int,
     *     block_seconds:int,
     *     now:int
     * }>
     */
    public array $recordAttemptCalls = [];

    /**
     * @var list<array{
     *     scope:string,
     *     key_hash:string
     * }>
     */
    public array $clearCalls = [];

    /**
     * @var array<string, ?int>
     */
    public array $blockedResponses = [];

    /**
     * @var array<string, ?int>
     */
    public array $recordResponses = [];

    public function blockedUntil(
        string $scope,
        string $keyHash,
        int $now
    ): ?int {
        $this->blockedUntilCalls[] = [
            'scope' => $scope,
            'key_hash' => $keyHash,
            'now' => $now,
        ];

        return $this->blockedResponses[
            $scope
        ] ?? null;
    }

    public function recordAttempt(
        string $scope,
        string $keyHash,
        int $maxAttempts,
        int $windowSeconds,
        int $blockSeconds,
        int $now
    ): ?int {
        $this->recordAttemptCalls[] = [
            'scope' => $scope,
            'key_hash' => $keyHash,
            'max_attempts' =>
                $maxAttempts,
            'window_seconds' =>
                $windowSeconds,
            'block_seconds' =>
                $blockSeconds,
            'now' => $now,
        ];

        return $this->recordResponses[
            $scope
        ] ?? null;
    }

    public function clear(
        string $scope,
        string $keyHash
    ): void {
        $this->clearCalls[] = [
            'scope' => $scope,
            'key_hash' => $keyHash,
        ];
    }
}

function assertLoginRateLimiterTrue(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException(
            $message
        );
    }
}

function assertLoginRateLimiterSame(
    mixed $expected,
    mixed $actual,
    string $message
): void {
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message
            . ' Esperado: '
            . var_export(
                $expected,
                true
            )
            . '; obtido: '
            . var_export(
                $actual,
                true
            )
        );
    }
}

/**
 * @return array{
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
 * }
 */
function loginRateLimiterConfig(): array
{
    return [
        'key_secret' =>
            '0123456789abcdef0123456789abcdef',

        'ip' => [
            'max_attempts' => 20,
            'window_seconds' => 300,
            'block_seconds' => 900,
        ],

        'username_ip' => [
            'max_failures' => 5,
            'window_seconds' => 900,
            'block_seconds' => 1200,
        ],
    ];
}

function loginRateLimiterExpectedIpHash(
    string $ip
): string {
    return hash_hmac(
        'sha256',
        "ip\0{$ip}",
        loginRateLimiterConfig()[
            'key_secret'
        ]
    );
}

function loginRateLimiterExpectedUsernameIpHash(
    string $ip,
    string $username
): string {
    return hash_hmac(
        'sha256',
        "username_ip\0"
        . $username
        . "\0"
        . $ip,
        loginRateLimiterConfig()[
            'key_secret'
        ]
    );
}

$now = 1800010000;

$tests = [];

$tests[
    'consulta os dois buckets com o mesmo instante'
] = static function () use ($now): void {
    $repository =
        new FakeLoginRateLimitRepository();

    $limiter =
        new LoginRateLimiter(
            $repository,
            loginRateLimiterConfig(),
            static fn (): int => $now
        );

    $result =
        $limiter->blockedUntil(
            '203.0.113.10',
            'mauro'
        );

    assertLoginRateLimiterSame(
        null,
        $result,
        'Sem bloqueios ativos deveria retornar null.'
    );

    assertLoginRateLimiterSame(
        2,
        count(
            $repository
                ->blockedUntilCalls
        ),
        'Deveria consultar exatamente dois buckets.'
    );

    assertLoginRateLimiterSame(
        [
            'scope' => 'login_ip',
            'key_hash' =>
                loginRateLimiterExpectedIpHash(
                    '203.0.113.10'
                ),
            'now' => $now,
        ],
        $repository
            ->blockedUntilCalls[0],
        'Consulta do bucket de IP está incorreta.'
    );

    assertLoginRateLimiterSame(
        [
            'scope' =>
                'login_username_ip',
            'key_hash' =>
                loginRateLimiterExpectedUsernameIpHash(
                    '203.0.113.10',
                    'mauro'
                ),
            'now' => $now,
        ],
        $repository
            ->blockedUntilCalls[1],
        'Consulta do bucket username+IP está incorreta.'
    );
};

$tests[
    'retorna o bloqueio ativo mais longo'
] = static function () use ($now): void {
    $repository =
        new FakeLoginRateLimitRepository();

    $repository->blockedResponses = [
        'login_ip' =>
            $now + 100,
        'login_username_ip' =>
            $now + 300,
    ];

    $limiter =
        new LoginRateLimiter(
            $repository,
            loginRateLimiterConfig(),
            static fn (): int => $now
        );

    assertLoginRateLimiterSame(
        $now + 300,
        $limiter->blockedUntil(
            '203.0.113.11',
            'usuario'
        ),
        'Deveria retornar o bloqueio que expira mais tarde.'
    );
};

$tests[
    'registra tentativa geral com política de IP'
] = static function () use ($now): void {
    $repository =
        new FakeLoginRateLimitRepository();

    $repository->recordResponses[
        'login_ip'
    ] = $now + 900;

    $limiter =
        new LoginRateLimiter(
            $repository,
            loginRateLimiterConfig(),
            static fn (): int => $now
        );

    $result =
        $limiter->recordIpAttempt(
            '198.51.100.20'
        );

    assertLoginRateLimiterSame(
        $now + 900,
        $result,
        'Retorno do repository deveria ser preservado.'
    );

    assertLoginRateLimiterSame(
        1,
        count(
            $repository
                ->recordAttemptCalls
        ),
        'Deveria registrar exatamente uma tentativa.'
    );

    assertLoginRateLimiterSame(
        [
            'scope' => 'login_ip',
            'key_hash' =>
                loginRateLimiterExpectedIpHash(
                    '198.51.100.20'
                ),
            'max_attempts' => 20,
            'window_seconds' => 300,
            'block_seconds' => 900,
            'now' => $now,
        ],
        $repository
            ->recordAttemptCalls[0],
        'Política do bucket geral de IP está incorreta.'
    );
};

$tests[
    'registra falha com política de username e IP'
] = static function () use ($now): void {
    $repository =
        new FakeLoginRateLimitRepository();

    $repository->recordResponses[
        'login_username_ip'
    ] = $now + 1200;

    $limiter =
        new LoginRateLimiter(
            $repository,
            loginRateLimiterConfig(),
            static fn (): int => $now
        );

    $result =
        $limiter
            ->recordCredentialFailure(
                '198.51.100.21',
                'Maria Silva'
            );

    assertLoginRateLimiterSame(
        $now + 1200,
        $result,
        'Retorno do bucket de falhas deveria ser preservado.'
    );

    assertLoginRateLimiterSame(
        [
            'scope' =>
                'login_username_ip',
            'key_hash' =>
                loginRateLimiterExpectedUsernameIpHash(
                    '198.51.100.21',
                    'Maria Silva'
                ),
            'max_attempts' => 5,
            'window_seconds' => 900,
            'block_seconds' => 1200,
            'now' => $now,
        ],
        $repository
            ->recordAttemptCalls[0],
        'Política do bucket username+IP está incorreta.'
    );
};

$tests[
    'sucesso limpa somente falhas de username e IP'
] = static function (): void {
    $repository =
        new FakeLoginRateLimitRepository();

    $limiter =
        new LoginRateLimiter(
            $repository,
            loginRateLimiterConfig(),
            static fn (): int => 1800010000
        );

    $limiter
        ->clearCredentialFailures(
            '192.0.2.55',
            'usuario'
        );

    assertLoginRateLimiterSame(
        1,
        count(
            $repository->clearCalls
        ),
        'Deveria limpar exatamente um bucket.'
    );

    assertLoginRateLimiterSame(
        [
            'scope' =>
                'login_username_ip',
            'key_hash' =>
                loginRateLimiterExpectedUsernameIpHash(
                    '192.0.2.55',
                    'usuario'
                ),
        ],
        $repository->clearCalls[0],
        'Somente o bucket username+IP deveria ser limpo.'
    );
};

$tests[
    'HMAC não persiste identificadores em claro'
] = static function () use ($now): void {
    $repository =
        new FakeLoginRateLimitRepository();

    $limiter =
        new LoginRateLimiter(
            $repository,
            loginRateLimiterConfig(),
            static fn (): int => $now
        );

    $ip =
        '203.0.113.250';

    $username =
        'usuario-teste';

    $limiter->recordIpAttempt(
        $ip
    );

    $limiter
        ->recordCredentialFailure(
            $ip,
            $username
        );

    foreach (
        $repository
            ->recordAttemptCalls
        as $call
    ) {
        $hash =
            $call['key_hash'];

        assertLoginRateLimiterTrue(
            preg_match(
                '/^[a-f0-9]{64}$/D',
                $hash
            ) === 1,
            'key_hash deveria ser SHA-256 hexadecimal.'
        );

        assertLoginRateLimiterTrue(
            !str_contains(
                $hash,
                $ip
            ),
            'IP não deveria aparecer em claro no key_hash.'
        );

        assertLoginRateLimiterTrue(
            !str_contains(
                $hash,
                $username
            ),
            'Username não deveria aparecer em claro no key_hash.'
        );
    }
};

$passed = 0;
$total = count(
    $tests
);

foreach (
    $tests as $name => $test
) {
    try {
        $test();

        ++$passed;

        fwrite(
            STDOUT,
            "[OK] {$name}\n"
        );
    } catch (Throwable $exception) {
        fwrite(
            STDERR,
            "[ERRO] {$name}: "
            . $exception
                ->getMessage()
            . "\n"
        );
    }
}

fwrite(
    STDOUT,
    "\nLoginRateLimiter: {$passed}/{$total} teste(s) aprovado(s).\n"
);

exit(
    $passed === $total
        ? 0
        : 1
);
