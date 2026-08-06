<?php

declare(strict_types=1);

if (!defined('AGENDA_ROOT')) {
    define('AGENDA_ROOT', __DIR__);
}

if (!defined('AGENDA_AUTOLOADER_REGISTERED')) {
    define('AGENDA_AUTOLOADER_REGISTERED', true);

    spl_autoload_register(
        static function (string $class): void {
            $prefix = 'AgendaInteligente\\';

            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $relativeClass = substr(
                $class,
                strlen($prefix)
            );

            $path = (string) AGENDA_ROOT
                . '/src/'
                . str_replace(
                    '\\',
                    '/',
                    $relativeClass
                )
                . '.php';

            if (is_file($path)) {
                require_once $path;
            }
        }
    );
}
