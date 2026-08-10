<?php

declare(strict_types=1);

use AgendaInteligente\Infrastructure\Config\ConfigLoader;
use AgendaInteligente\Infrastructure\Config\ConfigValidator;
use AgendaInteligente\Infrastructure\Config\ConfigurationException;

require_once dirname(__DIR__) . '/autoload.php';

/**
 * @return array<string, mixed>
 */
function validConfig(): array
{
    return [
        'app' => [
            'environment' => 'development',
            'debug' => true,
            'timezone' => 'America/Sao_Paulo',
            'base_url' => 'http://localhost:8000',
        ],
        'database' => [
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'agenda_inteligente',
            'username' => 'agenda_user',
            'password' => 'senha-de-teste',
            'charset' => 'utf8mb4',
        ],
        'session' => [
            'name' => 'AGENDA_INTELIGENTE_SESSID',
            'secure' => false,
            'same_site' => 'Lax',
            'idle_timeout' => 1800,
            'absolute_timeout' => 28800,
        ],
        'login_rate_limit' => [
            'key_secret' =>
                'segredo-de-rate-limit-para-testes-com-32-bytes',

            'ip' => [
                'max_attempts' => 20,
                'window_seconds' => 300,
                'block_seconds' => 900,
            ],

            'username_ip' => [
                'max_failures' => 5,
                'window_seconds' => 900,
                'block_seconds' => 900,
            ],
        ],

        'weather' => [
            'api_key' => '',
            'base_url' => 'https://api.openweathermap.org',
            'cache_seconds' => 1800,
        ],
    ];
}

/**
 * @param callable(): void $test
 */
function runTest(string $name, callable $test): bool
{
    try {
        $test();

        fwrite(
            STDOUT,
            sprintf("[OK] %s\n", $name)
        );

        return true;
    } catch (Throwable $exception) {
        fwrite(
            STDERR,
            sprintf(
                "[FALHA] %s: %s\n",
                $name,
                $exception->getMessage()
            )
        );

        return false;
    }
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/**
 * @param callable(): void $callback
 */
function assertConfigurationException(
    callable $callback,
    string $expectedMessagePart
): void {
    try {
        $callback();
    } catch (ConfigurationException $exception) {
        assertTrue(
            str_contains(
                $exception->getMessage(),
                $expectedMessagePart
            ),
            sprintf(
                'Mensagem inesperada: %s',
                $exception->getMessage()
            )
        );

        return;
    }

    throw new RuntimeException(
        'Era esperada uma ConfigurationException.'
    );
}

/**
 * @param array<string, mixed> $config
 */
function writeConfigFile(
    string $path,
    array $config
): void {
    $export = var_export($config, true);

    $content = sprintf(
        "<?php\n\ndeclare(strict_types=1);\n\nreturn %s;\n",
        $export
    );

    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException(
            'Não foi possível criar o arquivo temporário.'
        );
    }
}

$temporaryRoot = sys_get_temp_dir()
    . '/agenda-config-tests-'
    . bin2hex(random_bytes(8));

if (!mkdir($temporaryRoot, 0700, true)) {
    throw new RuntimeException(
        'Não foi possível criar o diretório temporário.'
    );
}

$configDirectory = $temporaryRoot . '/config';

if (!mkdir($configDirectory, 0700, true)) {
    throw new RuntimeException(
        'Não foi possível criar o diretório de configuração temporário.'
    );
}

$fallbackConfigPath = $configDirectory . '/app.php';
$externalConfigPath = $temporaryRoot . '/external-app.php';

writeConfigFile(
    $fallbackConfigPath,
    validConfig()
);

$externalConfig = validConfig();
$externalConfig['app']['environment'] = 'testing';

writeConfigFile(
    $externalConfigPath,
    $externalConfig
);

$originalConfigFile = getenv('AGENDA_CONFIG_FILE');

$tests = [];

$tests['aceita configuração válida'] = static function (): void {
    ConfigValidator::validate(
        validConfig()
    );
};

$tests['rejeita segredo curto do rate limiter'] = static function (): void {
    $config = validConfig();
    $config['login_rate_limit']['key_secret'] = 'curto';

    assertConfigurationException(
        static fn (): mixed => ConfigValidator::validate($config),
        'login_rate_limit.key_secret deve possuir pelo menos 32 bytes'
    );
};

$tests['rejeita configuração de IP que não é seção'] = static function (): void {
    $config = validConfig();
    $config['login_rate_limit']['ip'] = 'inválido';

    assertConfigurationException(
        static fn (): mixed => ConfigValidator::validate($config),
        'login_rate_limit.ip deve ser uma seção'
    );
};

$tests['rejeita limite de tentativas por IP não positivo'] = static function (): void {
    $config = validConfig();
    $config['login_rate_limit']['ip']['max_attempts'] = 0;

    assertConfigurationException(
        static fn (): mixed => ConfigValidator::validate($config),
        'login_rate_limit.ip.max_attempts deve ser maior que zero'
    );
};

$tests['rejeita limite de falhas por username e IP não positivo'] = static function (): void {
    $config = validConfig();
    $config['login_rate_limit']['username_ip']['max_failures'] = 0;

    assertConfigurationException(
        static fn (): mixed => ConfigValidator::validate($config),
        'login_rate_limit.username_ip.max_failures deve ser maior que zero'
    );
};

$tests['rejeita nome de sessão somente numérico'] = static function (): void {
    $config = validConfig();
    $config['session']['name'] = '123456';

    assertConfigurationException(
        static fn (): mixed => ConfigValidator::validate($config),
        'session.name não pode conter somente números'
    );
};

$tests['rejeita seção ausente'] = static function (): void {
    $config = validConfig();
    unset($config['database']);

    assertConfigurationException(
        static fn (): mixed => ConfigValidator::validate($config),
        'database está ausente'
    );
};

$tests['rejeita porta fora do intervalo'] = static function (): void {
    $config = validConfig();
    $config['database']['port'] = 70000;

    assertConfigurationException(
        static fn (): mixed => ConfigValidator::validate($config),
        'database.port deve estar entre 1 e 65535'
    );
};

$tests['rejeita SameSite None sem cookie seguro'] = static function (): void {
    $config = validConfig();
    $config['session']['same_site'] = 'None';
    $config['session']['secure'] = false;

    assertConfigurationException(
        static fn (): mixed => ConfigValidator::validate($config),
        'session.secure deve ser true'
    );
};

$tests['rejeita produção com debug ativo'] = static function (): void {
    $config = validConfig();
    $config['app']['environment'] = 'production';
    $config['app']['debug'] = true;
    $config['app']['base_url'] = 'https://agenda.example.com';
    $config['session']['secure'] = true;

    assertConfigurationException(
        static fn (): mixed => ConfigValidator::validate($config),
        'app.debug deve ser false em production'
    );
};

$tests['rejeita produção sem HTTPS'] = static function (): void {
    $config = validConfig();
    $config['app']['environment'] = 'production';
    $config['app']['debug'] = false;
    $config['app']['base_url'] = 'http://agenda.example.com';
    $config['session']['secure'] = true;

    assertConfigurationException(
        static fn (): mixed => ConfigValidator::validate($config),
        'app.base_url deve utilizar HTTPS em production'
    );
};

$tests['carrega fallback local'] = static function () use (
    $temporaryRoot
): void {
    putenv('AGENDA_CONFIG_FILE');

    $loader = new ConfigLoader(
        $temporaryRoot
    );

    $config = $loader->load();

    assertTrue(
        $config['app']['environment'] === 'development',
        'O fallback local não foi carregado.'
    );
};

$tests['prioriza caminho absoluto externo'] = static function () use (
    $temporaryRoot,
    $externalConfigPath
): void {
    putenv(
        'AGENDA_CONFIG_FILE=' . $externalConfigPath
    );

    $loader = new ConfigLoader(
        $temporaryRoot
    );

    $config = $loader->load();

    assertTrue(
        $config['app']['environment'] === 'testing',
        'O arquivo externo não foi priorizado.'
    );
};

$tests['rejeita caminho relativo externo'] = static function () use (
    $temporaryRoot
): void {
    putenv(
        'AGENDA_CONFIG_FILE=backend/config/app.php'
    );

    $loader = new ConfigLoader(
        $temporaryRoot
    );

    assertConfigurationException(
        static fn (): string => $loader->resolvePath(),
        'deve conter um caminho absoluto'
    );
};

$tests['rejeita arquivo que não retorna array'] = static function () use (
    $temporaryRoot
): void {
    $invalidPath = $temporaryRoot . '/invalid-app.php';

    $written = file_put_contents(
        $invalidPath,
        "<?php\n\ndeclare(strict_types=1);\n\nreturn 'inválido';\n"
    );

    assertTrue(
        $written !== false,
        'Não foi possível criar o arquivo inválido.'
    );

    putenv(
        'AGENDA_CONFIG_FILE=' . $invalidPath
    );

    $loader = new ConfigLoader(
        $temporaryRoot
    );

    assertConfigurationException(
        static fn (): array => $loader->load(),
        'deve retornar um array'
    );
};

$passed = 0;
$total = count($tests);

try {
    foreach ($tests as $name => $test) {
        if (runTest($name, $test)) {
            $passed++;
        }
    }
} finally {
    if (
        is_string($originalConfigFile)
        && $originalConfigFile !== ''
    ) {
        putenv(
            'AGENDA_CONFIG_FILE=' . $originalConfigFile
        );
    } else {
        putenv('AGENDA_CONFIG_FILE');
    }

    $files = [
        $fallbackConfigPath,
        $externalConfigPath,
        $temporaryRoot . '/invalid-app.php',
    ];

    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }

    if (is_dir($configDirectory)) {
        rmdir($configDirectory);
    }

    if (is_dir($temporaryRoot)) {
        rmdir($temporaryRoot);
    }
}

fwrite(
    STDOUT,
    sprintf(
        "\nResultado: %d/%d testes aprovados.\n",
        $passed,
        $total
    )
);

exit(
    $passed === $total
        ? 0
        : 1
);
