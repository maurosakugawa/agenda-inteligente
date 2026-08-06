<?php

declare(strict_types=1);

use AgendaInteligente\Infrastructure\Config\ConfigLoader;

require_once __DIR__ . '/autoload.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

$configLoader = new ConfigLoader(
    (string) AGENDA_ROOT
);

/** @var array<string, mixed> $config */
$config = $configLoader->load();

$timezone = (string) $config['app']['timezone'];
date_default_timezone_set(
    $timezone
);

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
$databaseConfig = $config['database'];

return [
    'config' => $config,
    'database' => $databaseConfig,
];
