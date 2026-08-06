<?php

declare(strict_types=1);

use AgendaInteligente\Infrastructure\Config\ConfigLoader;
use AgendaInteligente\Infrastructure\Config\ConfigurationException;

require_once dirname(__DIR__) . '/autoload.php';

try {
    $loader = new ConfigLoader(
        (string) AGENDA_ROOT
    );

    $config = $loader->load();

    $environment = (string) $config['app']['environment'];

    fwrite(
        STDOUT,
        sprintf(
            "Configuração válida para o ambiente %s.\n",
            $environment
        )
    );

    exit(0);
} catch (ConfigurationException $exception) {
    fwrite(
        STDERR,
        $exception->getMessage() . PHP_EOL
    );

    exit(1);
} catch (Throwable $exception) {
    error_log(
        sprintf(
            '[AGENDA][CONFIG] %s: %s',
            $exception::class,
            $exception->getMessage()
        )
    );

    fwrite(
        STDERR,
        "Falha inesperada ao validar a configuração.\n"
    );

    exit(1);
}
