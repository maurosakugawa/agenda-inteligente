<?php

declare(strict_types=1);

use AgendaInteligente\Infrastructure\Database\Connection;
use AgendaInteligente\Infrastructure\Persistence\MySqlRateLimitRepository;

require_once dirname(__DIR__) . '/autoload.php';

$application =
    require dirname(__DIR__)
        . '/bootstrap.php';

$pdo =
    Connection::make(
        $application['database']
    );

$currentDatabase =
    $pdo
        ->query(
            'SELECT DATABASE()'
        )
        ->fetchColumn();

if (
    $currentDatabase
    !== 'agenda_inteligente_test'
) {
    fwrite(
        STDERR,
        "[ERRO] Teste recusado: conexão não está em agenda_inteligente_test.\n"
    );

    exit(1);
}

function assertRateLimitTrue(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException(
            $message
        );
    }
}

function assertRateLimitSame(
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
 *     attempts:int,
 *     window_started_at:string,
 *     blocked_until:?string,
 *     updated_at:string
 * }|null
 */
function fetchRateLimitBucket(
    PDO $pdo,
    string $scope,
    string $keyHash
): ?array {
    $statement =
        $pdo->prepare(
            "
            SELECT
                attempts,
                window_started_at,
                blocked_until,
                updated_at
            FROM auth_rate_limits
            WHERE scope = :scope
              AND key_hash = :key_hash
            LIMIT 1
            "
        );

    $statement->execute([
        ':scope' => $scope,
        ':key_hash' => $keyHash,
    ]);

    $row =
        $statement->fetch();

    return is_array($row)
        ? $row
        : null;
}

function clearRateLimitTestFixtures(
    PDO $pdo
): void {
    $pdo->exec(
        "
        DELETE FROM auth_rate_limits
        WHERE scope LIKE 'rltest:%'
        "
    );
}

function rateLimitTestKey(
    string $value
): string {
    return hash(
        'sha256',
        $value
    );
}

$repository =
    new MySqlRateLimitRepository(
        $pdo
    );

$baseNow = 1800000000;

$tests = [];

$tests[
    'primeira tentativa cria bucket'
] = static function () use (
    $repository,
    $pdo,
    $baseNow
): void {
    $scope =
        'rltest:first';

    $keyHash =
        rateLimitTestKey(
            'first'
        );

    $result =
        $repository->recordAttempt(
            $scope,
            $keyHash,
            3,
            60,
            120,
            $baseNow
        );

    assertRateLimitSame(
        null,
        $result,
        'Primeira tentativa deveria ser permitida.'
    );

    $bucket =
        fetchRateLimitBucket(
            $pdo,
            $scope,
            $keyHash
        );

    assertRateLimitTrue(
        is_array($bucket),
        'Bucket deveria ter sido criado.'
    );

    assertRateLimitSame(
        1,
        $bucket['attempts'] ?? null,
        'Primeira tentativa deveria gerar attempts=1.'
    );

    assertRateLimitSame(
        gmdate(
            'Y-m-d H:i:s',
            $baseNow
        ),
        $bucket['window_started_at']
            ?? null,
        'Início da janela está incorreto.'
    );

    assertRateLimitSame(
        null,
        $bucket['blocked_until']
            ?? null,
        'Primeira tentativa não deveria bloquear.'
    );
};

$tests[
    'atingir limite arma bloqueio sem rejeitar tentativa atual'
] = static function () use (
    $repository,
    $pdo,
    $baseNow
): void {
    $scope =
        'rltest:limit';

    $keyHash =
        rateLimitTestKey(
            'limit'
        );

    $repository->recordAttempt(
        $scope,
        $keyHash,
        3,
        60,
        120,
        $baseNow
    );

    $repository->recordAttempt(
        $scope,
        $keyHash,
        3,
        60,
        120,
        $baseNow + 1
    );

    $result =
        $repository->recordAttempt(
            $scope,
            $keyHash,
            3,
            60,
            120,
            $baseNow + 2
        );

    assertRateLimitSame(
        null,
        $result,
        'Tentativa que atinge o limite ainda deveria ser permitida.'
    );

    $expectedBlockedUntil =
        $baseNow
        + 2
        + 120;

    $bucket =
        fetchRateLimitBucket(
            $pdo,
            $scope,
            $keyHash
        );

    assertRateLimitTrue(
        is_array($bucket),
        'Bucket do limite não foi encontrado.'
    );

    assertRateLimitSame(
        3,
        $bucket['attempts'] ?? null,
        'Contador deveria atingir o limite configurado.'
    );

    assertRateLimitSame(
        gmdate(
            'Y-m-d H:i:s',
            $expectedBlockedUntil
        ),
        $bucket['blocked_until']
            ?? null,
        'blocked_until foi gravado incorretamente.'
    );

    assertRateLimitSame(
        $expectedBlockedUntil,
        $repository->blockedUntil(
            $scope,
            $keyHash,
            $baseNow + 3
        ),
        'Consulta deveria retornar bloqueio ativo.'
    );
};

$tests[
    'tentativa posterior ao limite é bloqueada sem incrementar'
] = static function () use (
    $repository,
    $pdo,
    $baseNow
): void {
    $scope =
        'rltest:blocked';

    $keyHash =
        rateLimitTestKey(
            'blocked'
        );

    $repository->recordAttempt(
        $scope,
        $keyHash,
        2,
        60,
        90,
        $baseNow
    );

    $repository->recordAttempt(
        $scope,
        $keyHash,
        2,
        60,
        90,
        $baseNow + 1
    );

    $expectedBlockedUntil =
        $baseNow
        + 1
        + 90;

    $result =
        $repository->recordAttempt(
            $scope,
            $keyHash,
            2,
            60,
            90,
            $baseNow + 2
        );

    assertRateLimitSame(
        $expectedBlockedUntil,
        $result,
        'Tentativa durante bloqueio deveria retornar blocked_until.'
    );

    $bucket =
        fetchRateLimitBucket(
            $pdo,
            $scope,
            $keyHash
        );

    assertRateLimitTrue(
        is_array($bucket),
        'Bucket bloqueado não foi encontrado.'
    );

    assertRateLimitSame(
        2,
        $bucket['attempts'] ?? null,
        'Tentativa bloqueada não deveria incrementar attempts.'
    );
};

$tests[
    'janela expirada reinicia contador'
] = static function () use (
    $repository,
    $pdo,
    $baseNow
): void {
    $scope =
        'rltest:window';

    $keyHash =
        rateLimitTestKey(
            'window'
        );

    $repository->recordAttempt(
        $scope,
        $keyHash,
        5,
        60,
        120,
        $baseNow
    );

    $repository->recordAttempt(
        $scope,
        $keyHash,
        5,
        60,
        120,
        $baseNow + 10
    );

    $repository->recordAttempt(
        $scope,
        $keyHash,
        5,
        60,
        120,
        $baseNow + 60
    );

    $bucket =
        fetchRateLimitBucket(
            $pdo,
            $scope,
            $keyHash
        );

    assertRateLimitTrue(
        is_array($bucket),
        'Bucket da janela não foi encontrado.'
    );

    assertRateLimitSame(
        1,
        $bucket['attempts'] ?? null,
        'Janela expirada deveria reiniciar attempts em 1.'
    );

    assertRateLimitSame(
        gmdate(
            'Y-m-d H:i:s',
            $baseNow + 60
        ),
        $bucket['window_started_at']
            ?? null,
        'Nova janela deveria começar no instante da nova tentativa.'
    );

    assertRateLimitSame(
        null,
        $bucket['blocked_until']
            ?? null,
        'Nova janela não deveria permanecer bloqueada.'
    );
};

$tests[
    'bloqueio expirado inicia nova janela'
] = static function () use (
    $repository,
    $pdo,
    $baseNow
): void {
    $scope =
        'rltest:expired';

    $keyHash =
        rateLimitTestKey(
            'expired'
        );

    $repository->recordAttempt(
        $scope,
        $keyHash,
        2,
        300,
        30,
        $baseNow
    );

    $repository->recordAttempt(
        $scope,
        $keyHash,
        2,
        300,
        30,
        $baseNow + 1
    );

    $blockedUntil =
        $baseNow
        + 1
        + 30;

    assertRateLimitSame(
        null,
        $repository->blockedUntil(
            $scope,
            $keyHash,
            $blockedUntil
        ),
        'Bloqueio deveria estar expirado exatamente em blocked_until.'
    );

    $result =
        $repository->recordAttempt(
            $scope,
            $keyHash,
            2,
            300,
            30,
            $blockedUntil
        );

    assertRateLimitSame(
        null,
        $result,
        'Primeira tentativa após expiração deveria ser permitida.'
    );

    $bucket =
        fetchRateLimitBucket(
            $pdo,
            $scope,
            $keyHash
        );

    assertRateLimitTrue(
        is_array($bucket),
        'Bucket após expiração não foi encontrado.'
    );

    assertRateLimitSame(
        1,
        $bucket['attempts'] ?? null,
        'Bloqueio expirado deveria reiniciar attempts em 1.'
    );

    assertRateLimitSame(
        gmdate(
            'Y-m-d H:i:s',
            $blockedUntil
        ),
        $bucket['window_started_at']
            ?? null,
        'Nova janela deveria começar após o bloqueio expirado.'
    );

    assertRateLimitSame(
        null,
        $bucket['blocked_until']
            ?? null,
        'blocked_until deveria ser limpo na nova janela.'
    );
};

$tests[
    'clear remove somente o bucket informado'
] = static function () use (
    $repository,
    $pdo,
    $baseNow
): void {
    $scope =
        'rltest:clear';

    $firstKey =
        rateLimitTestKey(
            'clear-first'
        );

    $secondKey =
        rateLimitTestKey(
            'clear-second'
        );

    $repository->recordAttempt(
        $scope,
        $firstKey,
        3,
        60,
        120,
        $baseNow
    );

    $repository->recordAttempt(
        $scope,
        $secondKey,
        3,
        60,
        120,
        $baseNow
    );

    $repository->clear(
        $scope,
        $firstKey
    );

    assertRateLimitSame(
        null,
        fetchRateLimitBucket(
            $pdo,
            $scope,
            $firstKey
        ),
        'Bucket informado deveria ser removido.'
    );

    assertRateLimitTrue(
        is_array(
            fetchRateLimitBucket(
                $pdo,
                $scope,
                $secondKey
            )
        ),
        'clear não deveria remover outro bucket.'
    );
};

$tests[
    'parâmetros inválidos são rejeitados antes da transação'
] = static function () use (
    $repository,
    $pdo,
    $baseNow
): void {
    try {
        $repository->recordAttempt(
            'rltest:invalid',
            rateLimitTestKey(
                'invalid'
            ),
            0,
            60,
            120,
            $baseNow
        );
    } catch (InvalidArgumentException) {
        assertRateLimitSame(
            false,
            $pdo->inTransaction(),
            'Validação inválida não deveria deixar transação aberta.'
        );

        return;
    }

    throw new RuntimeException(
        'Era esperada InvalidArgumentException.'
    );
};

$tests[
    'transação externa é rejeitada sem ser encerrada'
] = static function () use (
    $repository,
    $pdo,
    $baseNow
): void {
    $scope =
        'rltest:external';

    $keyHash =
        rateLimitTestKey(
            'external'
        );

    $pdo->beginTransaction();

    try {
        $repository->recordAttempt(
            $scope,
            $keyHash,
            3,
            60,
            120,
            $baseNow
        );

        throw new RuntimeException(
            'Era esperada RuntimeException para transação externa.'
        );
    } catch (RuntimeException $exception) {
        assertRateLimitSame(
            'Rate limiting não pode ser atualizado dentro de uma transação externa.',
            $exception->getMessage(),
            'Mensagem da rejeição da transação externa está incorreta.'
        );

        assertRateLimitTrue(
            $pdo->inTransaction(),
            'Repository não deveria encerrar a transação pertencente ao chamador.'
        );
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }

    assertRateLimitSame(
        null,
        fetchRateLimitBucket(
            $pdo,
            $scope,
            $keyHash
        ),
        'Transação externa rejeitada não deveria criar bucket.'
    );
};

$tests[
    'scopes diferentes mantêm estados independentes'
] = static function () use (
    $repository,
    $baseNow
): void {
    $keyHash =
        rateLimitTestKey(
            'same-key'
        );

    $repository->recordAttempt(
        'rltest:scope-a',
        $keyHash,
        1,
        60,
        120,
        $baseNow
    );

    assertRateLimitSame(
        $baseNow + 120,
        $repository->blockedUntil(
            'rltest:scope-a',
            $keyHash,
            $baseNow + 1
        ),
        'Primeiro scope deveria estar bloqueado.'
    );

    assertRateLimitSame(
        null,
        $repository->blockedUntil(
            'rltest:scope-b',
            $keyHash,
            $baseNow + 1
        ),
        'Segundo scope deveria permanecer independente.'
    );
};

$passed = 0;
$total = count($tests);

clearRateLimitTestFixtures(
    $pdo
);

try {
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
                . $exception->getMessage()
                . "\n"
            );
        }
    }
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    clearRateLimitTestFixtures(
        $pdo
    );
}

fwrite(
    STDOUT,
    "\nMySqlRateLimitRepository: {$passed}/{$total} teste(s) aprovado(s).\n"
);

exit(
    $passed === $total
        ? 0
        : 1
);
