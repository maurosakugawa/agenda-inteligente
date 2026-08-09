<?php

declare(strict_types=1);

use AgendaInteligente\Infrastructure\Config\ConfigLoader;
use AgendaInteligente\Infrastructure\Persistence\MySqlRateLimitRepository;

require_once dirname(__DIR__) . '/autoload.php';

if (
    !function_exists('pcntl_fork')
    || !function_exists('pcntl_waitpid')
) {
    fwrite(
        STDERR,
        "[ERRO] Teste requer extensão pcntl no PHP CLI.\n"
    );

    exit(1);
}

$configLoader =
    new ConfigLoader(
        (string) AGENDA_ROOT
    );

$config =
    $configLoader->load();

/**
 * @var array{
 *     host:string,
 *     port:int,
 *     database:string,
 *     username:string,
 *     password:string,
 *     charset:string
 * } $databaseConfig
 */
$databaseConfig =
    $config['database'];

/**
 * @param array{
 *     host:string,
 *     port:int,
 *     database:string,
 *     username:string,
 *     password:string,
 *     charset:string
 * } $databaseConfig
 */
function makeRateLimitConcurrencyConnection(
    array $databaseConfig
): PDO {
    $dsn =
        sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $databaseConfig['host'],
            $databaseConfig['port'],
            $databaseConfig['database'],
            $databaseConfig['charset']
        );

    $pdo =
        new PDO(
            $dsn,
            $databaseConfig['username'],
            $databaseConfig['password'],
            [
                PDO::ATTR_ERRMODE =>
                    PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE =>
                    PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES =>
                    false,
                PDO::ATTR_STRINGIFY_FETCHES =>
                    false,
            ]
        );

    $pdo->exec(
        "SET time_zone = '+00:00'"
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
        throw new RuntimeException(
            'Teste recusado: conexão não está em agenda_inteligente_test.'
        );
    }

    return $pdo;
}

function assertRateLimitConcurrencyTrue(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException(
            $message
        );
    }
}

function assertRateLimitConcurrencySame(
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

function rateLimitConcurrencyKey(
    string $value
): string {
    return hash(
        'sha256',
        $value
    );
}

/**
 * @param array{
 *     host:string,
 *     port:int,
 *     database:string,
 *     username:string,
 *     password:string,
 *     charset:string
 * } $databaseConfig
 */
function clearRateLimitConcurrencyScope(
    array $databaseConfig,
    string $scope
): void {
    $pdo =
        makeRateLimitConcurrencyConnection(
            $databaseConfig
        );

    $statement =
        $pdo->prepare(
            "
            DELETE FROM auth_rate_limits
            WHERE scope = :scope
            "
        );

    $statement->execute([
        ':scope' => $scope,
    ]);
}

/**
 * @param array{
 *     host:string,
 *     port:int,
 *     database:string,
 *     username:string,
 *     password:string,
 *     charset:string
 * } $databaseConfig
 *
 * @return array{
 *     attempts:int,
 *     window_started_at:string,
 *     blocked_until:?string
 * }|null
 */
function fetchRateLimitConcurrencyBucket(
    array $databaseConfig,
    string $scope,
    string $keyHash
): ?array {
    $pdo =
        makeRateLimitConcurrencyConnection(
            $databaseConfig
        );

    $statement =
        $pdo->prepare(
            "
            SELECT
                attempts,
                window_started_at,
                blocked_until
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

    if (!is_array($row)) {
        return null;
    }

    return [
        'attempts' =>
            (int) $row['attempts'],
        'window_started_at' =>
            (string) $row[
                'window_started_at'
            ],
        'blocked_until' =>
            isset($row['blocked_until'])
                ? (string) $row[
                    'blocked_until'
                ]
                : null,
    ];
}

/**
 * @param array{
 *     host:string,
 *     port:int,
 *     database:string,
 *     username:string,
 *     password:string,
 *     charset:string
 * } $databaseConfig
 */
function countRateLimitConcurrencyBuckets(
    array $databaseConfig,
    string $scope,
    string $keyHash
): int {
    $pdo =
        makeRateLimitConcurrencyConnection(
            $databaseConfig
        );

    $statement =
        $pdo->prepare(
            "
            SELECT COUNT(*)
            FROM auth_rate_limits
            WHERE scope = :scope
              AND key_hash = :key_hash
            "
        );

    $statement->execute([
        ':scope' => $scope,
        ':key_hash' => $keyHash,
    ]);

    return (int) $statement
        ->fetchColumn();
}

/**
 * @param array{
 *     host:string,
 *     port:int,
 *     database:string,
 *     username:string,
 *     password:string,
 *     charset:string
 * } $databaseConfig
 *
 * @return list<?int>
 */
function runRateLimitConcurrencyWorkers(
    array $databaseConfig,
    string $scope,
    string $keyHash,
    int $workers,
    int $maxAttempts,
    int $windowSeconds,
    int $blockSeconds,
    int $now
): array {
    $runDirectory =
        sys_get_temp_dir()
        . '/agenda-rate-limit-'
        . bin2hex(
            random_bytes(8)
        );

    if (
        !mkdir(
            $runDirectory,
            0700,
            true
        )
    ) {
        throw new RuntimeException(
            'Não foi possível criar diretório temporário do teste.'
        );
    }

    $startFile =
        $runDirectory
        . '/start';

    /**
     * @var array<int, array{
     *     pid:int,
     *     ready:string,
     *     result:string
     * }>
     */
    $children = [];

    /** @var array<int, bool> $waited */
    $waited = [];

    try {
        for (
            $index = 0;
            $index < $workers;
            ++$index
        ) {
            $readyFile =
                $runDirectory
                . '/ready-'
                . $index;

            $resultFile =
                $runDirectory
                . '/result-'
                . $index
                . '.json';

            $pid =
                pcntl_fork();

            if ($pid === -1) {
                throw new RuntimeException(
                    'Não foi possível criar worker concorrente.'
                );
            }

            if ($pid === 0) {
                try {
                    /*
                     * Cada filho cria seu próprio PDO depois do fork.
                     * Nenhuma conexão é compartilhada entre processos.
                     */
                    $pdo =
                        makeRateLimitConcurrencyConnection(
                            $databaseConfig
                        );

                    $repository =
                        new MySqlRateLimitRepository(
                            $pdo
                        );

                    if (
                        file_put_contents(
                            $readyFile,
                            "ready\n",
                            LOCK_EX
                        ) === false
                    ) {
                        throw new RuntimeException(
                            'Worker não conseguiu sinalizar prontidão.'
                        );
                    }

                    $deadline =
                        microtime(true)
                        + 10.0;

                    while (
                        !is_file(
                            $startFile
                        )
                    ) {
                        if (
                            microtime(true)
                            >= $deadline
                        ) {
                            throw new RuntimeException(
                                'Timeout aguardando início concorrente.'
                            );
                        }

                        usleep(
                            1000
                        );
                    }

                    $result =
                        $repository
                            ->recordAttempt(
                                $scope,
                                $keyHash,
                                $maxAttempts,
                                $windowSeconds,
                                $blockSeconds,
                                $now
                            );

                    $payload =
                        json_encode(
                            [
                                'ok' => true,
                                'result' => $result,
                            ],
                            JSON_THROW_ON_ERROR
                        );

                    if (
                        file_put_contents(
                            $resultFile,
                            $payload,
                            LOCK_EX
                        ) === false
                    ) {
                        throw new RuntimeException(
                            'Worker não conseguiu gravar resultado.'
                        );
                    }

                    exit(0);
                } catch (Throwable $exception) {
                    try {
                        $payload =
                            json_encode(
                                [
                                    'ok' => false,
                                    'class' =>
                                        $exception::class,
                                    'message' =>
                                        $exception
                                            ->getMessage(),
                                ],
                                JSON_THROW_ON_ERROR
                            );

                        file_put_contents(
                            $resultFile,
                            $payload,
                            LOCK_EX
                        );
                    } catch (Throwable) {
                        // Não há recuperação adicional útil no worker.
                    }

                    exit(1);
                }
            }

            $children[$index] = [
                'pid' => $pid,
                'ready' => $readyFile,
                'result' => $resultFile,
            ];
        }

        /*
         * Só libera os workers quando todos já abriram suas
         * conexões independentes e sinalizaram prontidão.
         */
        $deadline =
            microtime(true)
            + 10.0;

        while (true) {
            $readyCount = 0;

            foreach (
                $children as $child
            ) {
                if (
                    is_file(
                        $child['ready']
                    )
                ) {
                    ++$readyCount;
                }
            }

            if (
                $readyCount
                === $workers
            ) {
                break;
            }

            if (
                microtime(true)
                >= $deadline
            ) {
                throw new RuntimeException(
                    'Timeout aguardando prontidão dos workers.'
                );
            }

            usleep(
                1000
            );
        }

        if (
            file_put_contents(
                $startFile,
                "go\n",
                LOCK_EX
            ) === false
        ) {
            throw new RuntimeException(
                'Não foi possível liberar os workers.'
            );
        }

        $results = [];

        foreach (
            $children as $index => $child
        ) {
            $status = 0;

            $waitResult =
                pcntl_waitpid(
                    $child['pid'],
                    $status
                );

            $waited[
                $child['pid']
            ] = true;

            if (
                $waitResult
                !== $child['pid']
            ) {
                throw new RuntimeException(
                    "Falha aguardando worker {$index}."
                );
            }

            if (
                !is_file(
                    $child['result']
                )
            ) {
                throw new RuntimeException(
                    "Worker {$index} não produziu resultado."
                );
            }

            $json =
                file_get_contents(
                    $child['result']
                );

            if ($json === false) {
                throw new RuntimeException(
                    "Resultado do worker {$index} não pôde ser lido."
                );
            }

            $payload =
                json_decode(
                    $json,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

            if (
                !is_array($payload)
                || (
                    $payload['ok']
                    ?? false
                ) !== true
            ) {
                $class =
                    is_array($payload)
                        ? (
                            $payload['class']
                            ?? 'erro'
                        )
                        : 'erro';

                $message =
                    is_array($payload)
                        ? (
                            $payload['message']
                            ?? 'sem mensagem'
                        )
                        : 'payload inválido';

                throw new RuntimeException(
                    "Worker {$index} falhou: {$class}: {$message}"
                );
            }

            if (
                !pcntl_wifexited(
                    $status
                )
                || pcntl_wexitstatus(
                    $status
                ) !== 0
            ) {
                throw new RuntimeException(
                    "Worker {$index} encerrou com status inesperado."
                );
            }

            $result =
                $payload['result']
                ?? null;

            $results[] =
                $result === null
                    ? null
                    : (int) $result;
        }

        return $results;
    } finally {
        /*
         * Em caso de erro antes da liberação, acorda filhos
         * que ainda possam estar aguardando a barreira.
         */
        if (
            !is_file(
                $startFile
            )
        ) {
            @file_put_contents(
                $startFile,
                "abort\n"
            );
        }

        foreach (
            $children as $child
        ) {
            if (
                isset(
                    $waited[
                        $child['pid']
                    ]
                )
            ) {
                continue;
            }

            $status = 0;

            @pcntl_waitpid(
                $child['pid'],
                $status
            );
        }

        $temporaryFiles =
            glob(
                $runDirectory
                . '/*'
            );

        if (
            is_array(
                $temporaryFiles
            )
        ) {
            foreach (
                $temporaryFiles
                as $temporaryFile
            ) {
                @unlink(
                    $temporaryFile
                );
            }
        }

        @rmdir(
            $runDirectory
        );
    }
}

$countScope =
    'rltest:concurrency:count';

$limitScope =
    'rltest:concurrency:limit';

$countKey =
    rateLimitConcurrencyKey(
        'concurrency-count'
    );

$limitKey =
    rateLimitConcurrencyKey(
        'concurrency-limit'
    );

$tests = [];

$tests[
    'concorrência não perde incrementos na criação do bucket'
] = static function () use (
    $databaseConfig,
    $countScope,
    $countKey
): void {
    clearRateLimitConcurrencyScope(
        $databaseConfig,
        $countScope
    );

    $workers = 8;
    $now = 1800001000;

    $results =
        runRateLimitConcurrencyWorkers(
            $databaseConfig,
            $countScope,
            $countKey,
            $workers,
            100,
            300,
            120,
            $now
        );

    assertRateLimitConcurrencySame(
        $workers,
        count(
            $results
        ),
        'Quantidade de resultados concorrentes está incorreta.'
    );

    foreach (
        $results as $result
    ) {
        assertRateLimitConcurrencySame(
            null,
            $result,
            'Todas as tentativas deveriam ser permitidas abaixo do limite.'
        );
    }

    $bucket =
        fetchRateLimitConcurrencyBucket(
            $databaseConfig,
            $countScope,
            $countKey
        );

    assertRateLimitConcurrencyTrue(
        is_array($bucket),
        'Bucket concorrente não foi criado.'
    );

    assertRateLimitConcurrencySame(
        $workers,
        $bucket['attempts']
            ?? null,
        'Algum incremento foi perdido sob concorrência.'
    );

    assertRateLimitConcurrencySame(
        null,
        $bucket['blocked_until']
            ?? null,
        'Bucket não deveria estar bloqueado.'
    );

    assertRateLimitConcurrencySame(
        1,
        countRateLimitConcurrencyBuckets(
            $databaseConfig,
            $countScope,
            $countKey
        ),
        'Criação concorrente deveria produzir exatamente um bucket.'
    );
};

$tests[
    'concorrência respeita limite exato'
] = static function () use (
    $databaseConfig,
    $limitScope,
    $limitKey
): void {
    clearRateLimitConcurrencyScope(
        $databaseConfig,
        $limitScope
    );

    $workers = 10;
    $maxAttempts = 5;
    $blockSeconds = 120;
    $now = 1800002000;

    $results =
        runRateLimitConcurrencyWorkers(
            $databaseConfig,
            $limitScope,
            $limitKey,
            $workers,
            $maxAttempts,
            300,
            $blockSeconds,
            $now
        );

    $allowed = 0;
    $blocked = 0;

    $expectedBlockedUntil =
        $now
        + $blockSeconds;

    foreach (
        $results as $result
    ) {
        if ($result === null) {
            ++$allowed;

            continue;
        }

        ++$blocked;

        assertRateLimitConcurrencySame(
            $expectedBlockedUntil,
            $result,
            'Worker bloqueado retornou instante incorreto.'
        );
    }

    assertRateLimitConcurrencySame(
        $maxAttempts,
        $allowed,
        'Quantidade de tentativas permitidas está incorreta.'
    );

    assertRateLimitConcurrencySame(
        $workers - $maxAttempts,
        $blocked,
        'Quantidade de tentativas bloqueadas está incorreta.'
    );

    $bucket =
        fetchRateLimitConcurrencyBucket(
            $databaseConfig,
            $limitScope,
            $limitKey
        );

    assertRateLimitConcurrencyTrue(
        is_array($bucket),
        'Bucket limitado não foi encontrado.'
    );

    assertRateLimitConcurrencySame(
        $maxAttempts,
        $bucket['attempts']
            ?? null,
        'Tentativas bloqueadas não deveriam incrementar o contador.'
    );

    assertRateLimitConcurrencySame(
        gmdate(
            'Y-m-d H:i:s',
            $expectedBlockedUntil
        ),
        $bucket['blocked_until']
            ?? null,
        'Bloqueio persistido está incorreto.'
    );

    assertRateLimitConcurrencySame(
        1,
        countRateLimitConcurrencyBuckets(
            $databaseConfig,
            $limitScope,
            $limitKey
        ),
        'Concorrência deveria preservar um único bucket.'
    );
};

$passed = 0;
$total = count(
    $tests
);

clearRateLimitConcurrencyScope(
    $databaseConfig,
    $countScope
);

clearRateLimitConcurrencyScope(
    $databaseConfig,
    $limitScope
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
                . $exception
                    ->getMessage()
                . "\n"
            );
        }
    }
} finally {
    clearRateLimitConcurrencyScope(
        $databaseConfig,
        $countScope
    );

    clearRateLimitConcurrencyScope(
        $databaseConfig,
        $limitScope
    );
}

fwrite(
    STDOUT,
    "\nMySqlRateLimitRepository concorrência: {$passed}/{$total} teste(s) aprovado(s).\n"
);

exit(
    $passed === $total
        ? 0
        : 1
);
