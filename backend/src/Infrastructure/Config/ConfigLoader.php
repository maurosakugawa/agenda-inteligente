<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Config;

use Throwable;

final class ConfigLoader
{
    public function __construct(
        private string $rootPath
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function load(): array
    {
        $configPath = $this->resolvePath();

        if (!is_file($configPath)) {
            throw new ConfigurationException(
                'Configuração inválida: arquivo efetivo não foi encontrado.'
            );
        }

        if (!is_readable($configPath)) {
            throw new ConfigurationException(
                'Configuração inválida: arquivo efetivo não pode ser lido.'
            );
        }

        try {
            $config = (
                static function (string $file): mixed {
                    return require $file;
                }
            )($configPath);
        } catch (Throwable $exception) {
            throw new ConfigurationException(
                'Configuração inválida: não foi possível carregar o arquivo efetivo.',
                0,
                $exception
            );
        }

        if (!is_array($config)) {
            throw new ConfigurationException(
                'Configuração inválida: o arquivo efetivo deve retornar um array.'
            );
        }

        ConfigValidator::validate($config);

        return $config;
    }

    public function resolvePath(): string
    {
        $selectedPath = getenv('AGENDA_CONFIG_FILE');

        if (
            is_string($selectedPath)
            && trim($selectedPath) !== ''
        ) {
            $selectedPath = trim($selectedPath);

            if (!$this->isAbsolutePath($selectedPath)) {
                throw new ConfigurationException(
                    'Configuração inválida: AGENDA_CONFIG_FILE deve conter um caminho absoluto.'
                );
            }

            return $selectedPath;
        }

        return rtrim(
            $this->rootPath,
            DIRECTORY_SEPARATOR
        ) . '/config/app.php';
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if (
            $path[0] === '/'
            || $path[0] === '\\'
        ) {
            return true;
        }

        return preg_match(
            '/^[A-Za-z]:[\\\\\/]/',
            $path
        ) === 1;
    }
}
