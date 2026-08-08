<?php

declare(strict_types=1);

use AgendaInteligente\Infrastructure\Console\MigrationCommand;

require_once dirname(__DIR__) . '/autoload.php';

$migrationsPath =
    dirname(
        (string) AGENDA_ROOT
    ) . '/database/migrations';

$command =
    new MigrationCommand(
        (string) AGENDA_ROOT,
        $migrationsPath
    );

exit(
    $command->run()
);
