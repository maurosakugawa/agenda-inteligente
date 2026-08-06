<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Config;

use DateTimeZone;
use Exception;

final class ConfigValidator
{
    /**
     * @param array<string, mixed> $config
     */
    public static function validate(array $config): void
    {
        $app = self::section($config, 'app');

        $environment = self::string(
            $app,
            'environment',
            'app.environment'
        );

        if (
            !in_array(
                $environment,
                [
                    'development',
                    'testing',
                    'production',
                ],
                true
            )
        ) {
            self::invalid(
                'app.environment',
                'deve ser development, testing ou production.'
            );
        }

        $debug = self::boolean(
            $app,
            'debug',
            'app.debug'
        );

        $timezone = self::string(
            $app,
            'timezone',
            'app.timezone'
        );

        try {
            new DateTimeZone($timezone);
        } catch (Exception) {
            self::invalid(
                'app.timezone',
                'deve identificar um fuso horário válido.'
            );
        }

        $baseUrl = self::url(
            $app,
            'base_url',
            'app.base_url',
            [
                'http',
                'https',
            ]
        );

        $database = self::section(
            $config,
            'database'
        );

        self::string(
            $database,
            'host',
            'database.host'
        );

        $databasePort = self::integer(
            $database,
            'port',
            'database.port'
        );

        if (
            $databasePort < 1
            || $databasePort > 65535
        ) {
            self::invalid(
                'database.port',
                'deve estar entre 1 e 65535.'
            );
        }

        self::string(
            $database,
            'database',
            'database.database'
        );

        self::string(
            $database,
            'username',
            'database.username'
        );

        self::string(
            $database,
            'password',
            'database.password'
        );

        $charset = self::string(
            $database,
            'charset',
            'database.charset'
        );

        if ($charset !== 'utf8mb4') {
            self::invalid(
                'database.charset',
                'deve ser utf8mb4.'
            );
        }

        $session = self::section(
            $config,
            'session'
        );

        $sessionName = self::string(
            $session,
            'name',
            'session.name'
        );

        if (
            preg_match(
                '/^[A-Za-z0-9_-]+$/',
                $sessionName
            ) !== 1
        ) {
            self::invalid(
                'session.name',
                'deve conter apenas letras, números, hífen ou sublinhado.'
            );
        }

        $sessionSecure = self::boolean(
            $session,
            'secure',
            'session.secure'
        );

        $sameSite = self::string(
            $session,
            'same_site',
            'session.same_site'
        );

        if (
            !in_array(
                $sameSite,
                [
                    'Lax',
                    'Strict',
                    'None',
                ],
                true
            )
        ) {
            self::invalid(
                'session.same_site',
                'deve ser Lax, Strict ou None.'
            );
        }

        if (
            $sameSite === 'None'
            && !$sessionSecure
        ) {
            self::invalid(
                'session.secure',
                'deve ser true quando session.same_site for None.'
            );
        }

        $idleTimeout = self::positiveInteger(
            $session,
            'idle_timeout',
            'session.idle_timeout'
        );

        $absoluteTimeout = self::positiveInteger(
            $session,
            'absolute_timeout',
            'session.absolute_timeout'
        );

        if ($absoluteTimeout < $idleTimeout) {
            self::invalid(
                'session.absolute_timeout',
                'não pode ser menor que session.idle_timeout.'
            );
        }

        $weather = self::section(
            $config,
            'weather'
        );

        self::string(
            $weather,
            'api_key',
            'weather.api_key',
            true
        );

        self::url(
            $weather,
            'base_url',
            'weather.base_url',
            [
                'https',
            ]
        );

        self::positiveInteger(
            $weather,
            'cache_seconds',
            'weather.cache_seconds'
        );

        if ($environment === 'production') {
            if ($debug) {
                self::invalid(
                    'app.debug',
                    'deve ser false em production.'
                );
            }

            if (
                parse_url(
                    $baseUrl,
                    PHP_URL_SCHEME
                ) !== 'https'
            ) {
                self::invalid(
                    'app.base_url',
                    'deve utilizar HTTPS em production.'
                );
            }

            if (!$sessionSecure) {
                self::invalid(
                    'session.secure',
                    'deve ser true em production.'
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private static function section(
        array $config,
        string $key
    ): array {
        if (!array_key_exists($key, $config)) {
            self::invalid(
                $key,
                'está ausente.'
            );
        }

        $value = $config[$key];

        if (!is_array($value)) {
            self::invalid(
                $key,
                'deve ser uma seção.'
            );
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $section
     */
    private static function string(
        array $section,
        string $key,
        string $path,
        bool $allowEmpty = false
    ): string {
        $value = self::value(
            $section,
            $key,
            $path
        );

        if (!is_string($value)) {
            self::invalid(
                $path,
                'deve ser uma string.'
            );
        }

        if (
            !$allowEmpty
            && trim($value) === ''
        ) {
            self::invalid(
                $path,
                'está ausente.'
            );
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $section
     */
    private static function boolean(
        array $section,
        string $key,
        string $path
    ): bool {
        $value = self::value(
            $section,
            $key,
            $path
        );

        if (!is_bool($value)) {
            self::invalid(
                $path,
                'deve ser booleano.'
            );
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $section
     */
    private static function integer(
        array $section,
        string $key,
        string $path
    ): int {
        $value = self::value(
            $section,
            $key,
            $path
        );

        if (!is_int($value)) {
            self::invalid(
                $path,
                'deve ser um número inteiro.'
            );
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $section
     */
    private static function positiveInteger(
        array $section,
        string $key,
        string $path
    ): int {
        $value = self::integer(
            $section,
            $key,
            $path
        );

        if ($value < 1) {
            self::invalid(
                $path,
                'deve ser maior que zero.'
            );
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $section
     * @param list<string> $allowedSchemes
     */
    private static function url(
        array $section,
        string $key,
        string $path,
        array $allowedSchemes
    ): string {
        $url = trim(
            self::string(
                $section,
                $key,
                $path
            )
        );

        if (
            filter_var(
                $url,
                FILTER_VALIDATE_URL
            ) === false
        ) {
            self::invalid(
                $path,
                'deve ser uma URL válida.'
            );
        }

        $scheme = strtolower(
            (string) parse_url(
                $url,
                PHP_URL_SCHEME
            )
        );

        if (
            !in_array(
                $scheme,
                $allowedSchemes,
                true
            )
        ) {
            self::invalid(
                $path,
                'utiliza um protocolo não permitido.'
            );
        }

        return $url;
    }

    /**
     * @param array<string, mixed> $section
     */
    private static function value(
        array $section,
        string $key,
        string $path
    ): mixed {
        if (!array_key_exists($key, $section)) {
            self::invalid(
                $path,
                'está ausente.'
            );
        }

        return $section[$key];
    }

    private static function invalid(
        string $path,
        string $reason
    ): never {
        throw new ConfigurationException(
            sprintf(
                'Configuração inválida: %s %s',
                $path,
                $reason
            )
        );
    }
}
