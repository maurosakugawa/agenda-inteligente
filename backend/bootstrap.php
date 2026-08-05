<?php

declare(strict_types=1);

use AgendaInteligente\Infrastructure\Database\Connection;

const AGENDA_ROOT = __DIR__;

spl_autoload_register(
    static function (string $class): void {
        $prefix = 'AgendaInteligente\\';

        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $path = AGENDA_ROOT
            . '/src/'
            . str_replace('\\', '/', $relativeClass)
            . '.php';

        if (is_file($path)) {
            require_once $path;
        }
    }
);

$configPath = AGENDA_ROOT . '/config/app.php';

if (!is_file($configPath)) {
    throw new RuntimeException(
        'Configuração ausente. Copie backend/config/app.example.php para backend/config/app.php.'
    );
}

/** @var array<string, mixed> $config */
$config = require $configPath;

$timezone = (string) ($config['app']['timezone'] ?? 'America/Sao_Paulo');
date_default_timezone_set($timezone);

$debug = (bool) ($config['app']['debug'] ?? false);
ini_set('display_errors', $debug ? '1' : '0');
error_reporting(E_ALL);

/** @var array{host:string,port:int,database:string,username:string,password:string,charset:string} $databaseConfig */
$databaseConfig = $config['database'];
$pdo = Connection::make($databaseConfig);

return [
    'config' => $config,
    'pdo' => $pdo,
];
